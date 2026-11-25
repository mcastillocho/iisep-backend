<?php

declare(strict_types=1);

use App\Enums\DocumentType;
use App\Enums\EnrollmentStatus;
use App\Enums\StudentCareerStatus;
use App\Models\Branch;
use App\Models\Career;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ScheduledCourse;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentCareer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function createStaffUser(string $username, string $position, ?int $branchId): User
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

test('admin can update any branch name and address', function () {
    $branch = Branch::create(['name' => 'Sede Original', 'code' => 'ORI']);
    $admin = createStaffUser('admin_user', 'admin', null);

    $response = $this->actingAs($admin)->put(route('admin.sedes.update', $branch), [
        'name' => 'Sede Actualizada',
        'code' => 'ACT',
        'address' => 'Av. Principal 123',
        'phone' => '999888777',
    ]);

    $response->assertSessionHasNoErrors();
    $branch->refresh();
    expect($branch->name)->toBe('Sede Actualizada');
    expect($branch->code)->toBe('ACT');
    expect($branch->address)->toBe('Av. Principal 123');
});

test('branch coordinator can update their branch address but cannot change name', function () {
    $branch = Branch::create(['name' => 'Sede Norte', 'code' => 'NOR', 'address' => 'Vieja Direccion']);
    $coord = createStaffUser('coord_user', 'coord', $branch->id);

    $response = $this->actingAs($coord)->put(route('admin.sedes.update', $branch), [
        'name' => 'Intento Cambio Sede',
        'code' => 'INT',
        'address' => 'Nueva Direccion 456',
        'phone' => '987654321',
    ]);

    $response->assertSessionHasNoErrors();
    $branch->refresh();
    // Address is updated
    expect($branch->address)->toBe('Nueva Direccion 456');
    // Name and code remain unchanged
    expect($branch->name)->toBe('Sede Norte');
    expect($branch->code)->toBe('NOR');
});

test('branch coordinator cannot update other branches', function () {
    $branch1 = Branch::create(['name' => 'Sede 1', 'code' => 'S1']);
    $branch2 = Branch::create(['name' => 'Sede 2', 'code' => 'S2']);
    $coord = createStaffUser('coord_user', 'coord', $branch1->id);

    $response = $this->actingAs($coord)->put(route('admin.sedes.update', $branch2), [
        'address' => 'Hackeando Direccion',
    ]);

    $response->assertStatus(403);
});

test('admin can view lightweight branches summary at /admin/sedes', function () {
    $branch = Branch::create(['name' => 'Sede Comas', 'code' => 'COM', 'address' => 'Av. Principal 100', 'phone' => '999000111']);
    $admin = createStaffUser('admin_summary', 'admin', null);

    $response = $this->actingAs($admin)->get(route('admin.sedes.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/SedesAulas')
        ->where('selectedBranch', null)
        ->has('branches', 1)
        ->where('branches.0.id', $branch->id)
        ->where('branches.0.classrooms_count', 0)
    );
});

test('branch staff visiting /admin/sedes is redirected to their assigned branch', function () {
    $branch = Branch::create(['name' => 'Sede Puente Piedra', 'code' => 'PUP']);
    $coord = createStaffUser('coord_redirect', 'coord', $branch->id);

    $response = $this->actingAs($coord)->get(route('admin.sedes.index'));

    $response->assertRedirect(route('admin.sedes.show', $branch));
});

test('branch staff can view their own branch detail but cannot view another branch', function () {
    $branch1 = Branch::create(['name' => 'Sede 1', 'code' => 'S1']);
    $branch2 = Branch::create(['name' => 'Sede 2', 'code' => 'S2']);
    $secre = createStaffUser('secre_show', 'secre', $branch1->id);

    $this->actingAs($secre)->get(route('admin.sedes.show', $branch1))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/SedesAulas')
            ->where('selectedBranch.id', $branch1->id)
            ->has('classrooms')
        );

    $this->actingAs($secre)->get(route('admin.sedes.show', $branch2))
        ->assertStatus(403);
});

test('non-staff user cannot access branches endpoints', function () {
    $branch = Branch::create(['name' => 'Sede Protegida', 'code' => 'PRO']);
    $studentUser = User::create([
        'first_name' => 'Alumno',
        'paternal_surname' => 'Externo',
        'document' => '70000001',
        'document_type' => DocumentType::DNI,
        'username' => 'alumno_ext',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($studentUser)->get(route('admin.sedes.index'))->assertStatus(403);
    $this->actingAs($studentUser)->get(route('admin.sedes.show', $branch))->assertStatus(403);
});

test('admin can deactivate a branch without blockers and cascades soft delete to active classrooms', function () {
    Branch::create(['name' => 'Sede Principal', 'code' => 'PRI']);
    $branch = Branch::create(['name' => 'Sede Temporal', 'code' => 'TMP']);
    $activeRoom = Classroom::create(['branch_id' => $branch->id, 'name' => 'Aula 101', 'capacity' => 25]);
    $manuallyDeletedRoom = Classroom::create(['branch_id' => $branch->id, 'name' => 'Aula Antigua', 'capacity' => 15]);
    $manuallyDeletedRoom->delete();

    $admin = createStaffUser('admin_del_ok', 'admin', null);

    $response = $this->actingAs($admin)->delete(route('admin.sedes.destroy', $branch));

    $response->assertRedirect(route('admin.sedes.index'));
    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('success');

    $branch->refresh();
    $activeRoom->refresh();
    $manuallyDeletedRoom->refresh();

    expect($branch->is_active)->toBeFalse();
    expect($branch->trashed())->toBeTrue();
    expect($activeRoom->trashed())->toBeTrue();
    expect($activeRoom->deleted_with_branch)->toBeTrue();
    expect($manuallyDeletedRoom->trashed())->toBeTrue();
    expect($manuallyDeletedRoom->deleted_with_branch)->toBeFalse();
});

test('admin cannot deactivate the last active branch', function () {
    $onlyBranch = Branch::create(['name' => 'Unica Sede', 'code' => 'UNI']);
    $admin = createStaffUser('admin_del_last', 'admin', null);

    $response = $this->actingAs($admin)->delete(route('admin.sedes.destroy', $onlyBranch));

    $response->assertSessionHasErrors('branch');
    $onlyBranch->refresh();
    expect($onlyBranch->is_active)->toBeTrue();
    expect($onlyBranch->trashed())->toBeFalse();
});

test('admin cannot deactivate a branch with assigned staff', function () {
    Branch::create(['name' => 'Sede Principal', 'code' => 'PRI']);
    $branch = Branch::create(['name' => 'Sede Con Staff', 'code' => 'STF']);
    createStaffUser('coord_assigned', 'coord', $branch->id);
    $admin = createStaffUser('admin_del_staff', 'admin', null);

    $response = $this->actingAs($admin)->delete(route('admin.sedes.destroy', $branch));

    $response->assertSessionHasErrors('branch');
    $branch->refresh();
    expect($branch->is_active)->toBeTrue();
    expect($branch->trashed())->toBeFalse();
});

test('admin cannot deactivate a branch with active or reserved career students', function (StudentCareerStatus $status) {
    Branch::create(['name' => 'Sede Principal', 'code' => 'PRI']);
    $branch = Branch::create(['name' => 'Sede Con Alumnos', 'code' => 'ALU-'.$status->value]);
    $career = Career::create(['name' => 'Estilismo', 'duration_in_weeks' => 48]);
    $admin = createStaffUser('admin_del_sc_'.$status->value, 'admin', null);

    $studentUser = User::create([
        'first_name' => 'Alumno',
        'paternal_surname' => 'Carrera',
        'document' => (string) rand(74000000, 74999999),
        'document_type' => DocumentType::DNI,
        'username' => 'alumno_sc_'.$status->value,
        'password' => bcrypt('password'),
    ]);
    $student = Student::create(['user_id' => $studentUser->id]);

    StudentCareer::create([
        'student_id' => $student->id,
        'career_id' => $career->id,
        'branch_id' => $branch->id,
        'admission_date' => Carbon::now('America/Lima')->subMonth()->toDateString(),
        'status' => $status,
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.sedes.destroy', $branch));

    $response->assertSessionHasErrors('branch');
    $branch->refresh();
    expect($branch->is_active)->toBeTrue();
    expect($branch->trashed())->toBeFalse();
})->with([
    'activo' => StudentCareerStatus::ACTIVO,
    'en_reserva' => StudentCareerStatus::EN_RESERVA,
]);

test('admin can deactivate a branch when all courses ended, enrollments are closed, and career students graduated', function () {
    Branch::create(['name' => 'Sede Principal', 'code' => 'PRI']);
    $branch = Branch::create(['name' => 'Sede Historica', 'code' => 'HIS']);
    $admin = createStaffUser('admin_del_hist', 'admin', null);

    $career = Career::create(['name' => 'Barberia', 'duration_in_weeks' => 24]);
    $course = Course::create([
        'name' => 'Curso Concluido',
        'duration_in_weeks' => 4,
        'is_workshop' => true,
    ]);

    $pastCourse = ScheduledCourse::create([
        'branch_id' => $branch->id,
        'course_id' => $course->id,
        'start_date' => Carbon::now('America/Lima')->subMonths(3)->toDateString(),
        'end_date' => Carbon::now('America/Lima')->subMonth()->toDateString(),
    ]);

    $studentUser = User::create([
        'first_name' => 'Alumno',
        'paternal_surname' => 'Egresado',
        'document' => '71000001',
        'document_type' => DocumentType::DNI,
        'username' => 'alumno_hist_1',
        'password' => bcrypt('password'),
    ]);
    $student = Student::create(['user_id' => $studentUser->id]);

    StudentCareer::create([
        'student_id' => $student->id,
        'career_id' => $career->id,
        'branch_id' => $branch->id,
        'admission_date' => Carbon::now('America/Lima')->subYear()->toDateString(),
        'status' => StudentCareerStatus::EGRESADO,
    ]);

    Enrollment::create([
        'scheduled_course_id' => $pastCourse->id,
        'student_id' => $student->id,
        'enrollment_date' => Carbon::now('America/Lima')->subMonths(3)->toDateString(),
        'status' => EnrollmentStatus::COMPLETADO,
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.sedes.destroy', $branch));

    $response->assertRedirect(route('admin.sedes.index'));
    $response->assertSessionHasNoErrors();

    $branch->refresh();
    expect($branch->is_active)->toBeFalse();
    expect($branch->trashed())->toBeTrue();
});

test('admin cannot deactivate a branch with active or upcoming scheduled courses', function () {
    Branch::create(['name' => 'Sede Principal', 'code' => 'PRI']);
    $branch = Branch::create(['name' => 'Sede Vigente', 'code' => 'VIG']);
    $admin = createStaffUser('admin_del_course', 'admin', null);

    $course = Course::create([
        'name' => 'Curso Activo',
        'duration_in_weeks' => 8,
        'is_workshop' => true,
    ]);

    ScheduledCourse::create([
        'branch_id' => $branch->id,
        'course_id' => $course->id,
        'start_date' => Carbon::now('America/Lima')->subWeek()->toDateString(),
        'end_date' => Carbon::now('America/Lima')->addWeeks(3)->toDateString(),
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.sedes.destroy', $branch));

    $response->assertSessionHasErrors('branch');

    $branch->refresh();
    expect($branch->is_active)->toBeTrue();
    expect($branch->trashed())->toBeFalse();
});

test('admin cannot deactivate a branch with active or pre-enrolled enrollments even on past courses', function (EnrollmentStatus $status) {
    Branch::create(['name' => 'Sede Principal', 'code' => 'PRI']);
    $branch = Branch::create(['name' => 'Sede Con Matricula', 'code' => 'MAT-'.$status->value]);
    $admin = createStaffUser('admin_del_enr_'.$status->value, 'admin', null);

    $course = Course::create([
        'name' => 'Curso Sin Cerrar',
        'duration_in_weeks' => 4,
        'is_workshop' => true,
    ]);

    $pastCourse = ScheduledCourse::create([
        'branch_id' => $branch->id,
        'course_id' => $course->id,
        'start_date' => Carbon::now('America/Lima')->subMonths(2)->toDateString(),
        'end_date' => Carbon::now('America/Lima')->subWeek()->toDateString(),
    ]);

    $studentUser = User::create([
        'first_name' => 'Alumno',
        'paternal_surname' => 'Vigente',
        'document' => (string) rand(72000000, 72999999),
        'document_type' => DocumentType::DNI,
        'username' => 'alumno_enr_'.$status->value,
        'password' => bcrypt('password'),
    ]);
    $student = Student::create(['user_id' => $studentUser->id]);

    Enrollment::create([
        'scheduled_course_id' => $pastCourse->id,
        'student_id' => $student->id,
        'enrollment_date' => Carbon::now('America/Lima')->subMonths(2)->toDateString(),
        'status' => $status,
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.sedes.destroy', $branch));

    $response->assertSessionHasErrors('branch');

    $branch->refresh();
    expect($branch->is_active)->toBeTrue();
    expect($branch->trashed())->toBeFalse();
})->with([
    'activo' => EnrollmentStatus::ACTIVO,
    'preinscrito' => EnrollmentStatus::PREINSCRITO,
]);

test('only main admin can deactivate or reactivate a branch', function () {
    Branch::create(['name' => 'Sede Principal', 'code' => 'PRI']);
    $branch = Branch::create(['name' => 'Sede Protegida Baja', 'code' => 'PRB']);
    $coord = createStaffUser('coord_del', 'coord', $branch->id);
    $secre = createStaffUser('secre_del', 'secre', $branch->id);
    $studentUser = User::create([
        'first_name' => 'Alumno',
        'paternal_surname' => 'Normal',
        'document' => '73000001',
        'document_type' => DocumentType::DNI,
        'username' => 'alumno_del',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($coord)->delete(route('admin.sedes.destroy', $branch))->assertStatus(403);
    $this->actingAs($secre)->delete(route('admin.sedes.destroy', $branch))->assertStatus(403);
    $this->actingAs($studentUser)->delete(route('admin.sedes.destroy', $branch))->assertStatus(403);

    $this->actingAs($coord)->patch(route('admin.sedes.restore', $branch))->assertStatus(403);
    $this->actingAs($secre)->patch(route('admin.sedes.restore', $branch))->assertStatus(403);
    $this->actingAs($studentUser)->patch(route('admin.sedes.restore', $branch))->assertStatus(403);

    $branch->refresh();
    expect($branch->is_active)->toBeTrue();
    expect($branch->trashed())->toBeFalse();
});

test('admin can reactivate a deactivated branch and restore only classrooms deleted alongside it', function () {
    Branch::create(['name' => 'Sede Principal', 'code' => 'PRI']);
    $branch = Branch::create(['name' => 'Sede Reactivable', 'code' => 'REA']);
    $roomA = Classroom::create(['branch_id' => $branch->id, 'name' => 'Aula A', 'capacity' => 20]);
    $roomB = Classroom::create(['branch_id' => $branch->id, 'name' => 'Aula B', 'capacity' => 30]);
    $manuallyDeletedRoom = Classroom::create(['branch_id' => $branch->id, 'name' => 'Aula Borrada Antes', 'capacity' => 10]);
    $manuallyDeletedRoom->delete();

    $admin = createStaffUser('admin_restore', 'admin', null);

    $this->actingAs($admin)->delete(route('admin.sedes.destroy', $branch))->assertSessionHasNoErrors();

    // Confirm branch appears in inactiveBranches on /admin/sedes
    $this->actingAs($admin)->get(route('admin.sedes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('branches', 1)
            ->has('inactiveBranches', 1)
            ->where('inactiveBranches.0.id', $branch->id)
            ->where('inactiveBranches.0.restorable_classrooms_count', 2)
        );

    // Reactivate branch
    $response = $this->actingAs($admin)->patch(route('admin.sedes.restore', $branch));
    $response->assertRedirect(route('admin.sedes.index'));
    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('success');

    $branch->refresh();
    $roomA->refresh();
    $roomB->refresh();
    $manuallyDeletedRoom->refresh();

    expect($branch->is_active)->toBeTrue();
    expect($branch->trashed())->toBeFalse();
    expect($roomA->trashed())->toBeFalse();
    expect($roomA->deleted_with_branch)->toBeFalse();
    expect($roomB->trashed())->toBeFalse();
    expect($roomB->deleted_with_branch)->toBeFalse();
    expect($manuallyDeletedRoom->trashed())->toBeTrue();
});

test('deactivated branch name and code can be reused and blocks reactivation conflict', function () {
    Branch::create(['name' => 'Sede Principal', 'code' => 'PRI']);
    $oldBranch = Branch::create(['name' => 'Sede Sur', 'code' => 'SUR']);
    $admin = createStaffUser('admin_reuse', 'admin', null);

    $this->actingAs($admin)->delete(route('admin.sedes.destroy', $oldBranch))->assertSessionHasNoErrors();

    // Creating a new active branch with the same name & code succeeds thanks to partial unique indexes
    $this->actingAs($admin)->post(route('admin.sedes.store'), [
        'name' => 'Sede Sur',
        'code' => 'SUR',
        'address' => 'Nueva Av. Sur 100',
    ])->assertRedirect(route('admin.sedes.index'))->assertSessionHasNoErrors();

    // Attempting to reactivate the old branch fails cleanly with validation error instead of 500
    $this->actingAs($admin)->patch(route('admin.sedes.restore', $oldBranch))
        ->assertSessionHasErrors('branch');

    $oldBranch->refresh();
    expect($oldBranch->trashed())->toBeTrue();
});

test('cannot create a classroom in a deactivated branch', function () {
    Branch::create(['name' => 'Sede Principal', 'code' => 'PRI']);
    $deactivatedBranch = Branch::create(['name' => 'Sede Cerrada', 'code' => 'CER']);
    $admin = createStaffUser('admin_closed_room', 'admin', null);

    $this->actingAs($admin)->delete(route('admin.sedes.destroy', $deactivatedBranch))->assertSessionHasNoErrors();

    $this->actingAs($admin)->post(route('admin.classrooms.store'), [
        'name' => 'Aula Ilegal',
        'branch_id' => $deactivatedBranch->id,
        'capacity' => 20,
    ])->assertSessionHasErrors('branch_id');
});
