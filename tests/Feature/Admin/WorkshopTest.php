<?php

declare(strict_types=1);

use App\Enums\DocumentType;
use App\Enums\EnrollmentStatus;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Module;
use App\Models\ScheduledCourse;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function createWorkshopStaffUser(string $username, string $position, ?int $branchId): User
{
    $user = User::create([
        'first_name' => $username,
        'paternal_surname' => 'Test',
        'document' => (string) rand(10000000, 99999999),
        'document_type' => DocumentType::DNI,
        'username' => $username,
        'password' => bcrypt('password'),
    ]);

    Staff::create([
        'user_id' => $user->id,
        'position' => $position,
        'branch_id' => $branchId,
    ]);

    return $user;
}

test('director general can create, update, and delete a free workshop', function () {
    $admin = createWorkshopStaffUser('admin_user', 'admin', null);

    $this->actingAs($admin)->post(route('admin.workshops.store'), [
        'name' => 'Taller de Resina Epóxica',
        'duration_in_weeks' => 5,
        'description' => 'Manejo de moldes, acabados transparentes y accesorios.',
    ])->assertSessionHasNoErrors();

    $workshop = Course::where('name', 'Taller de Resina Epóxica')->firstOrFail();
    expect($workshop->is_workshop)->toBeTrue();
    expect($workshop->module_id)->toBeNull();

    $this->actingAs($admin)->put(route('admin.workshops.update', $workshop), [
        'name' => 'Taller de Resina Avanzado',
        'duration_in_weeks' => 6,
        'description' => 'Nueva descripción ampliada.',
    ])->assertSessionHasNoErrors();

    expect($workshop->fresh()->name)->toBe('Taller de Resina Avanzado');
    expect($workshop->fresh()->duration_in_weeks)->toBe(6);

    $this->actingAs($admin)->delete(route('admin.workshops.destroy', $workshop))
        ->assertSessionHasNoErrors();
    $this->assertSoftDeleted('courses', ['id' => $workshop->id]);
});

test('branch coordinator and secretary cannot create, update, or delete workshops', function () {
    $branch = Branch::create(['name' => 'Sede Comas', 'code' => 'COM']);
    $coord = createWorkshopStaffUser('coord_user', 'coord', $branch->id);
    $sec = createWorkshopStaffUser('sec_user', 'secre', $branch->id);
    $workshop = Course::create(['name' => 'Taller Existente', 'duration_in_weeks' => 4, 'is_workshop' => true]);

    foreach ([$coord, $sec] as $user) {
        $this->actingAs($user)->post(route('admin.workshops.store'), [
            'name' => 'Taller No Autorizado',
            'duration_in_weeks' => 4,
        ])->assertForbidden();

        $this->actingAs($user)->put(route('admin.workshops.update', $workshop), [
            'name' => 'Intento Modificar Taller',
            'duration_in_weeks' => 8,
        ])->assertForbidden();

        $this->actingAs($user)->delete(route('admin.workshops.destroy', $workshop))
            ->assertForbidden();
    }

    expect($workshop->fresh()->name)->toBe('Taller Existente');
});

test('cannot delete a workshop if it has scheduled courses', function () {
    $branch = Branch::create(['name' => 'Sede Comas', 'code' => 'COM']);
    $admin = createWorkshopStaffUser('admin_user', 'admin', null);
    $workshop = Course::create(['name' => 'Taller Con Clases', 'duration_in_weeks' => 4, 'is_workshop' => true]);

    ScheduledCourse::create([
        'branch_id' => $branch->id,
        'course_id' => $workshop->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addWeeks(4)->toDateString(),
    ]);

    $this->actingAs($admin)->delete(route('admin.workshops.destroy', $workshop))
        ->assertSessionHasErrors('workshop');
    $this->assertDatabaseHas('courses', ['id' => $workshop->id, 'deleted_at' => null]);
});

test('creating a workshop automatically unlinks module_id while modular course keeps it', function () {
    $admin = createWorkshopStaffUser('admin_user_mod', 'admin', null);
    $module = Module::create(['name' => 'Módulo Contable', 'duration_in_weeks' => 12]);

    $this->actingAs($admin)->post(route('admin.workshops.store'), [
        'name' => 'Contabilidad Básica I',
        'duration_in_weeks' => 4,
        'description' => 'Libros contables y asientos.',
        'is_workshop' => false,
        'module_id' => $module->id,
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('courses', [
        'name' => 'Contabilidad Básica I',
        'module_id' => $module->id,
        'is_workshop' => false,
    ]);

    // If is_workshop=true even with module_id sent, module_id is automatically unlinked to null
    $this->actingAs($admin)->post(route('admin.workshops.store'), [
        'name' => 'Taller Libre Auto-Desvinculado',
        'duration_in_weeks' => 4,
        'is_workshop' => true,
        'module_id' => $module->id,
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('courses', [
        'name' => 'Taller Libre Auto-Desvinculado',
        'module_id' => null,
        'is_workshop' => true,
    ]);
});

test('updating a course without active activity can switch modules directly and auto-unlink when marked as workshop', function () {
    $admin = createWorkshopStaffUser('admin_move_course', 'admin', null);
    $moduleA = Module::create(['name' => 'Módulo A', 'duration_in_weeks' => 8]);
    $moduleB = Module::create(['name' => 'Módulo B', 'duration_in_weeks' => 8]);
    $course = Course::create([
        'name' => 'Curso Modular',
        'duration_in_weeks' => 4,
        'is_workshop' => false,
        'module_id' => $moduleA->id,
    ]);

    // Switch directly from Module A to Module B
    $this->actingAs($admin)->put(route('admin.workshops.update', $course), [
        'name' => 'Curso Modular',
        'duration_in_weeks' => 4,
        'is_workshop' => false,
        'module_id' => $moduleB->id,
    ])->assertSessionHasNoErrors();

    expect($course->fresh()->module_id)->toBe($moduleB->id);
    expect($course->fresh()->is_workshop)->toBeFalse();

    // Mark as free workshop: automatically unlinks from Module B
    $this->actingAs($admin)->put(route('admin.workshops.update', $course), [
        'name' => 'Curso Modular',
        'duration_in_weeks' => 4,
        'is_workshop' => true,
        'module_id' => $moduleB->id,
    ])->assertSessionHasNoErrors();

    expect($course->fresh()->module_id)->toBeNull();
    expect($course->fresh()->is_workshop)->toBeTrue();
});

test('cannot change module or convert to workshop while course has active scheduled courses or in-progress enrollments', function () {
    $branch = Branch::create(['name' => 'Sede Comas', 'code' => 'COM']);
    $admin = createWorkshopStaffUser('admin_lock_course', 'admin', null);
    $moduleA = Module::create(['name' => 'Módulo Activo A', 'duration_in_weeks' => 8]);
    $moduleB = Module::create(['name' => 'Módulo Activo B', 'duration_in_weeks' => 8]);

    // Case 1: Active scheduled course
    $activeCourse = Course::create([
        'name' => 'Curso En Dictado',
        'duration_in_weeks' => 4,
        'is_workshop' => false,
        'module_id' => $moduleA->id,
    ]);
    ScheduledCourse::create([
        'branch_id' => $branch->id,
        'course_id' => $activeCourse->id,
        'start_date' => Carbon::now('America/Lima')->subWeek()->toDateString(),
        'end_date' => Carbon::now('America/Lima')->addWeeks(2)->toDateString(),
    ]);

    $this->actingAs($admin)->put(route('admin.workshops.update', $activeCourse), [
        'name' => 'Curso En Dictado',
        'duration_in_weeks' => 4,
        'is_workshop' => true,
        'module_id' => null,
    ])->assertSessionHasErrors('module_id');
    expect($activeCourse->fresh()->module_id)->toBe($moduleA->id);

    // Editing name/description without changing module/type is still allowed
    $this->actingAs($admin)->put(route('admin.workshops.update', $activeCourse), [
        'name' => 'Curso En Dictado Editado',
        'duration_in_weeks' => 4,
        'is_workshop' => false,
        'module_id' => $moduleA->id,
    ])->assertSessionHasNoErrors();

    // Case 2: Past scheduled course with still-active enrollment
    $courseWithEnrollment = Course::create([
        'name' => 'Curso Con Matricula Abierta',
        'duration_in_weeks' => 4,
        'is_workshop' => false,
        'module_id' => $moduleA->id,
    ]);
    $pastSc = ScheduledCourse::create([
        'branch_id' => $branch->id,
        'course_id' => $courseWithEnrollment->id,
        'start_date' => Carbon::now('America/Lima')->subMonths(2)->toDateString(),
        'end_date' => Carbon::now('America/Lima')->subWeek()->toDateString(),
    ]);
    $studentUser = User::create([
        'first_name' => 'Luis',
        'paternal_surname' => 'Perez',
        'document' => '75000001',
        'document_type' => DocumentType::DNI,
        'username' => 'lperez_ws',
        'password' => bcrypt('password'),
    ]);
    $student = Student::create(['user_id' => $studentUser->id]);
    Enrollment::create([
        'scheduled_course_id' => $pastSc->id,
        'student_id' => $student->id,
        'module_id' => $moduleA->id,
        'enrollment_date' => Carbon::now('America/Lima')->subMonths(2)->toDateString(),
        'status' => EnrollmentStatus::ACTIVO,
    ]);

    $this->actingAs($admin)->put(route('admin.workshops.update', $courseWithEnrollment), [
        'name' => 'Curso Con Matricula Abierta',
        'duration_in_weeks' => 4,
        'is_workshop' => false,
        'module_id' => $moduleB->id,
    ])->assertSessionHasErrors('module_id');
    expect($courseWithEnrollment->fresh()->module_id)->toBe($moduleA->id);
});
