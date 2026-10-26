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

test('director general can create a module', function () {
    $admin = createModuleStaffUser('admin_user', 'admin', null);

    $response = $this->actingAs($admin)->post(route('admin.modules.store'), [
        'name' => 'Ofimática y Gestión Digital',
        'duration_in_weeks' => 8,
        'description' => 'Herramientas de productividad de oficina.',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('modules', [
        'name' => 'Ofimática y Gestión Digital',
        'duration_in_weeks' => 8,
    ]);
});

test('branch coordinator can create a module', function () {
    $branch = Branch::create(['name' => 'Sede Los Olivos', 'code' => 'LOS']);
    $coord = createModuleStaffUser('coord_user', 'coord', $branch->id);

    $response = $this->actingAs($coord)->post(route('admin.modules.store'), [
        'name' => 'Desarrollo Backend y APIs',
        'duration_in_weeks' => 12,
        'description' => 'Arquitectura de servidores y base de datos.',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('modules', [
        'name' => 'Desarrollo Backend y APIs',
        'duration_in_weeks' => 12,
    ]);
});

test('branch secretary cannot create a module', function () {
    $branch = Branch::create(['name' => 'Sede Breña', 'code' => 'BRE']);
    $secre = createModuleStaffUser('secre_user', 'secre', $branch->id);

    $response = $this->actingAs($secre)->post(route('admin.modules.store'), [
        'name' => 'Módulo No Autorizado',
        'duration_in_weeks' => 6,
    ]);

    $response->assertForbidden();
});

test('branch coordinator can update a module', function () {
    $branch = Branch::create(['name' => 'Sede Surco', 'code' => 'SUR']);
    $coord = createModuleStaffUser('coord_user2', 'coord', $branch->id);

    $module = Module::create([
        'name' => 'Marketing Básico',
        'duration_in_weeks' => 6,
        'description' => 'Fundamentos de ventas.',
    ]);

    $response = $this->actingAs($coord)->put(route('admin.modules.update', $module), [
        'name' => 'Marketing Digital y Redes Sociales',
        'duration_in_weeks' => 8,
        'description' => 'Campañas publicitarias, métricas y branding.',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('modules', [
        'id' => $module->id,
        'name' => 'Marketing Digital y Redes Sociales',
        'duration_in_weeks' => 8,
    ]);
});

test('branch secretary cannot update a module', function () {
    $branch = Branch::create(['name' => 'Sede Callao', 'code' => 'CAL']);
    $secre = createModuleStaffUser('secre_user2', 'secre', $branch->id);

    $module = Module::create([
        'name' => 'Diseño Gráfico',
        'duration_in_weeks' => 10,
    ]);

    $response = $this->actingAs($secre)->put(route('admin.modules.update', $module), [
        'name' => 'Diseño Gráfico Modificado',
        'duration_in_weeks' => 10,
    ]);

    $response->assertForbidden();
});

test('cannot delete a module if it has assigned courses', function () {
    $admin = createModuleStaffUser('admin_user2', 'admin', null);

    $module = Module::create([
        'name' => 'Computación e Informática I',
        'duration_in_weeks' => 8,
    ]);

    Course::create([
        'module_id' => $module->id,
        'name' => 'Excel Avanzado',
        'duration_in_weeks' => 4,
        'is_workshop' => false,
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.modules.destroy', $module));

    $response->assertSessionHasErrors(['error']);
    $this->assertDatabaseHas('modules', [
        'id' => $module->id,
        'deleted_at' => null,
    ]);
});

test('cannot delete a module if it is linked to a career', function () {
    $admin = createModuleStaffUser('admin_user3', 'admin', null);

    $module = Module::create([
        'name' => 'Administración y Gestión',
        'duration_in_weeks' => 10,
    ]);

    $career = Career::create([
        'name' => 'Administración Bancaria',
    ]);

    $module->careers()->attach($career->id, ['order' => 1]);

    $response = $this->actingAs($admin)->delete(route('admin.modules.destroy', $module));

    $response->assertSessionHasErrors(['error']);
    $this->assertDatabaseHas('modules', [
        'id' => $module->id,
        'deleted_at' => null,
    ]);
});

test('can delete a module without dependencies', function () {
    $admin = createModuleStaffUser('admin_user4', 'admin', null);

    $module = Module::create([
        'name' => 'Módulo Sin Uso',
        'duration_in_weeks' => 4,
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.modules.destroy', $module));

    $response->assertSessionHasNoErrors();
    $this->assertSoftDeleted('modules', [
        'id' => $module->id,
    ]);
});

test('can create a module and assign courses', function () {
    $admin = createModuleStaffUser('admin_user5', 'admin', null);

    $course1 = Course::create([
        'name' => 'Introducción a Algoritmos',
        'duration_in_weeks' => 4,
        'is_workshop' => false,
    ]);
    $course2 = Course::create([
        'name' => 'Estructura de Datos',
        'duration_in_weeks' => 4,
        'is_workshop' => false,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.modules.store'), [
        'name' => 'Fundamentos de Programación',
        'duration_in_weeks' => 8,
        'course_ids' => [$course1->id, $course2->id],
    ]);

    $response->assertSessionHasNoErrors();
    $module = Module::where('name', 'Fundamentos de Programación')->first();
    expect($module)->not->toBeNull();

    $this->assertDatabaseHas('courses', [
        'id' => $course1->id,
        'module_id' => $module->id,
    ]);
    $this->assertDatabaseHas('courses', [
        'id' => $course2->id,
        'module_id' => $module->id,
    ]);
});

test('can update a module and sync course_ids (assign and unassign)', function () {
    $admin = createModuleStaffUser('admin_user6', 'admin', null);

    $module = Module::create([
        'name' => 'Módulo Base',
        'duration_in_weeks' => 8,
    ]);

    $course1 = Course::create([
        'module_id' => $module->id,
        'name' => 'Curso 1',
        'duration_in_weeks' => 4,
    ]);
    $course2 = Course::create([
        'module_id' => null,
        'name' => 'Curso 2',
        'duration_in_weeks' => 4,
    ]);

    // Update with course2 only (unassigning course1)
    $response = $this->actingAs($admin)->put(route('admin.modules.update', $module), [
        'name' => 'Módulo Base Actualizado',
        'duration_in_weeks' => 8,
        'course_ids' => [$course2->id],
    ]);

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('courses', [
        'id' => $course1->id,
        'module_id' => null,
    ]);
    $this->assertDatabaseHas('courses', [
        'id' => $course2->id,
        'module_id' => $module->id,
    ]);
});
