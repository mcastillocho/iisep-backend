<?php

declare(strict_types=1);

use App\Enums\DocumentType;
use App\Models\Branch;
use App\Models\Career;
use App\Models\Course;
use App\Models\Module;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createModuleStaffUser(string $username, string $position, ?int $branchId): User
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

test('director general can create and update a module', function () {
    $admin = createModuleStaffUser('admin_user', 'admin', null);

    $this->actingAs($admin)->post(route('admin.modules.store'), [
        'name' => 'Ofimática y Gestión Digital',
        'duration_in_weeks' => 8,
        'description' => 'Herramientas de productividad de oficina.',
    ])->assertSessionHasNoErrors();

    $module = Module::where('name', 'Ofimática y Gestión Digital')->firstOrFail();

    $this->actingAs($admin)->put(route('admin.modules.update', $module), [
        'name' => 'Ofimática Avanzada',
        'duration_in_weeks' => 10,
        'description' => 'Hojas de cálculo y automatización.',
    ])->assertSessionHasNoErrors();

    expect($module->fresh()->name)->toBe('Ofimática Avanzada');
    expect($module->fresh()->duration_in_weeks)->toBe(10);
});

test('branch coordinator and secretary cannot manage modules or query assignable courses', function () {
    $branch = Branch::create(['name' => 'Sede Los Olivos', 'code' => 'LOS']);
    $coord = createModuleStaffUser('coord_user', 'coord', $branch->id);
    $secre = createModuleStaffUser('secre_user', 'secre', $branch->id);
    $module = Module::create(['name' => 'Diseño Gráfico', 'duration_in_weeks' => 10]);

    foreach ([$coord, $secre] as $user) {
        $this->actingAs($user)->post(route('admin.modules.store'), [
            'name' => 'Módulo No Autorizado',
            'duration_in_weeks' => 6,
        ])->assertForbidden();

        $this->actingAs($user)->put(route('admin.modules.update', $module), [
            'name' => 'Diseño Gráfico Modificado',
            'duration_in_weeks' => 10,
        ])->assertForbidden();

        $this->actingAs($user)->delete(route('admin.modules.destroy', $module))
            ->assertForbidden();

        $this->actingAs($user)->getJson(route('admin.modules.assignable-courses'))
            ->assertForbidden();
    }
});

test('cannot delete a module if it has assigned courses or linked careers', function () {
    $admin = createModuleStaffUser('admin_user2', 'admin', null);

    $moduleWithCourse = Module::create(['name' => 'Computación I', 'duration_in_weeks' => 8]);
    Course::create([
        'module_id' => $moduleWithCourse->id,
        'name' => 'Excel Avanzado',
        'duration_in_weeks' => 4,
        'is_workshop' => false,
    ]);

    $this->actingAs($admin)->delete(route('admin.modules.destroy', $moduleWithCourse))
        ->assertSessionHasErrors(['error']);
    $this->assertDatabaseHas('modules', ['id' => $moduleWithCourse->id, 'deleted_at' => null]);

    $moduleWithCareer = Module::create(['name' => 'Administración y Gestión', 'duration_in_weeks' => 10]);
    $career = Career::create(['name' => 'Administración Bancaria']);
    $moduleWithCareer->careers()->attach($career->id, ['order' => 1]);

    $this->actingAs($admin)->delete(route('admin.modules.destroy', $moduleWithCareer))
        ->assertSessionHasErrors(['error']);
    $this->assertDatabaseHas('modules', ['id' => $moduleWithCareer->id, 'deleted_at' => null]);
});

test('can delete a module without dependencies', function () {
    $admin = createModuleStaffUser('admin_user4', 'admin', null);
    $module = Module::create(['name' => 'Módulo Sin Uso', 'duration_in_weeks' => 4]);

    $this->actingAs($admin)->delete(route('admin.modules.destroy', $module))
        ->assertSessionHasNoErrors();
    $this->assertSoftDeleted('modules', ['id' => $module->id]);
});

test('can create a module and assign unassigned non-workshop courses', function () {
    $admin = createModuleStaffUser('admin_user5', 'admin', null);

    $course1 = Course::create(['name' => 'Introducción a Algoritmos', 'duration_in_weeks' => 4, 'is_workshop' => false]);
    $course2 = Course::create(['name' => 'Estructura de Datos', 'duration_in_weeks' => 4, 'is_workshop' => false]);

    $this->actingAs($admin)->post(route('admin.modules.store'), [
        'name' => 'Fundamentos de Programación',
        'duration_in_weeks' => 8,
        'course_ids' => [$course1->id, $course2->id],
    ])->assertSessionHasNoErrors();

    $module = Module::where('name', 'Fundamentos de Programación')->firstOrFail();

    $this->assertDatabaseHas('courses', ['id' => $course1->id, 'module_id' => $module->id]);
    $this->assertDatabaseHas('courses', ['id' => $course2->id, 'module_id' => $module->id]);
});

test('cannot assign a course that already belongs to another module or a free workshop', function () {
    $admin = createModuleStaffUser('admin_block_steal', 'admin', null);

    $moduleA = Module::create(['name' => 'Módulo A', 'duration_in_weeks' => 8]);
    $moduleB = Module::create(['name' => 'Módulo B', 'duration_in_weeks' => 8]);
    $courseInA = Course::create(['module_id' => $moduleA->id, 'name' => 'Curso Exclusivo A', 'duration_in_weeks' => 4, 'is_workshop' => false]);
    $workshop = Course::create(['module_id' => null, 'name' => 'Taller Libre de Fotografía', 'duration_in_weeks' => 4, 'is_workshop' => true]);

    // Store blocked when trying to steal courseInA
    $this->actingAs($admin)->post(route('admin.modules.store'), [
        'name' => 'Módulo Nuevo',
        'duration_in_weeks' => 8,
        'course_ids' => [$courseInA->id],
    ])->assertSessionHasErrors('course_ids');
    expect($courseInA->fresh()->module_id)->toBe($moduleA->id);

    // Store blocked when trying to attach a workshop
    $this->actingAs($admin)->post(route('admin.modules.store'), [
        'name' => 'Módulo Nuevo Con Taller',
        'duration_in_weeks' => 8,
        'course_ids' => [$workshop->id],
    ])->assertSessionHasErrors('course_ids');
    expect($workshop->fresh()->module_id)->toBeNull();

    // Update of moduleB blocked when trying to steal courseInA or attach workshop
    $this->actingAs($admin)->put(route('admin.modules.update', $moduleB), [
        'name' => 'Módulo B',
        'duration_in_weeks' => 8,
        'course_ids' => [$courseInA->id],
    ])->assertSessionHasErrors('course_ids');
    expect($courseInA->fresh()->module_id)->toBe($moduleA->id);

    $this->actingAs($admin)->put(route('admin.modules.update', $moduleB), [
        'name' => 'Módulo B',
        'duration_in_weeks' => 8,
        'course_ids' => [$workshop->id],
    ])->assertSessionHasErrors('course_ids');
});

test('can update a module and sync course_ids (assign and unassign)', function () {
    $admin = createModuleStaffUser('admin_user6', 'admin', null);
    $module = Module::create(['name' => 'Módulo Base', 'duration_in_weeks' => 8]);

    $course1 = Course::create(['module_id' => $module->id, 'name' => 'Curso 1', 'duration_in_weeks' => 4, 'is_workshop' => false]);
    $course2 = Course::create(['module_id' => null, 'name' => 'Curso 2', 'duration_in_weeks' => 4, 'is_workshop' => false]);

    $this->actingAs($admin)->put(route('admin.modules.update', $module), [
        'name' => 'Módulo Base Actualizado',
        'duration_in_weeks' => 8,
        'course_ids' => [$course2->id],
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('courses', ['id' => $course1->id, 'module_id' => null]);
    $this->assertDatabaseHas('courses', ['id' => $course2->id, 'module_id' => $module->id]);
});

test('assignableCourses returns only non-workshop courses not assigned to other modules', function () {
    $admin = createModuleStaffUser('admin_assignable', 'admin', null);
    $module1 = Module::create(['name' => 'Módulo 1', 'duration_in_weeks' => 8]);
    $module2 = Module::create(['name' => 'Módulo 2', 'duration_in_weeks' => 8]);

    $freeCourse = Course::create(['module_id' => null, 'name' => 'Curso Libre', 'duration_in_weeks' => 4, 'is_workshop' => false]);
    $mod1Course = Course::create(['module_id' => $module1->id, 'name' => 'Curso de Mod 1', 'duration_in_weeks' => 4, 'is_workshop' => false]);
    Course::create(['module_id' => $module2->id, 'name' => 'Curso de Mod 2', 'duration_in_weeks' => 4, 'is_workshop' => false]);
    Course::create(['module_id' => null, 'name' => 'Taller Independiente', 'duration_in_weeks' => 4, 'is_workshop' => true]);

    // New module: only freeCourse
    $this->actingAs($admin)
        ->getJson(route('admin.modules.assignable-courses'))
        ->assertOk()
        ->assertJsonCount(1, 'courses')
        ->assertJsonPath('courses.0.id', $freeCourse->id);

    // Editing module1: freeCourse + mod1Course (excludes mod2Course and workshop)
    $response = $this->actingAs($admin)
        ->getJson(route('admin.modules.assignable-courses', ['module_id' => $module1->id]))
        ->assertOk()
        ->assertJsonCount(2, 'courses');

    $ids = collect($response->json('courses'))->pluck('id')->all();
    expect($ids)->toEqualCanonicalizing([$freeCourse->id, $mod1Course->id]);
});
