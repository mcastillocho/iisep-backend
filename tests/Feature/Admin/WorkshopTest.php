<?php

declare(strict_types=1);

use App\Enums\DocumentType;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Module;
use App\Models\ScheduledCourse;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

test('director general can create a workshop', function () {
    $admin = createWorkshopStaffUser('admin_user', 'admin', null);

    $response = $this->actingAs($admin)->post(route('admin.workshops.store'), [
        'name' => 'Taller de Resina Epóxica',
        'duration_in_weeks' => 5,
        'description' => 'Manejo de moldes, acabados transparentes y accesorios.',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('courses', [
        'name' => 'Taller de Resina Epóxica',
        'duration_in_weeks' => 5,
        'is_workshop' => true,
        'module_id' => null,
    ]);
});

test('branch coordinator can create a workshop', function () {
    $branch = Branch::create(['name' => 'Sede Comas', 'code' => 'COM']);
    $coord = createWorkshopStaffUser('coord_user', 'coord', $branch->id);

    $response = $this->actingAs($coord)->post(route('admin.workshops.store'), [
        'name' => 'Taller de Masajes Relajantes',
        'duration_in_weeks' => 4,
        'description' => 'Técnicas de digitopuntura y piedras calientes.',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('courses', [
        'name' => 'Taller de Masajes Relajantes',
        'duration_in_weeks' => 4,
        'is_workshop' => true,
    ]);
});

test('secretary cannot create a workshop', function () {
    $branch = Branch::create(['name' => 'Sede Comas', 'code' => 'COM']);
    $sec = createWorkshopStaffUser('sec_user', 'secre', $branch->id);

    $response = $this->actingAs($sec)->post(route('admin.workshops.store'), [
        'name' => 'Taller No Autorizado',
        'duration_in_weeks' => 4,
    ]);

    $response->assertStatus(403);
    $this->assertDatabaseMissing('courses', [
        'name' => 'Taller No Autorizado',
    ]);
});

test('branch coordinator can update a workshop', function () {
    $branch = Branch::create(['name' => 'Sede Comas', 'code' => 'COM']);
    $coord = createWorkshopStaffUser('coord_user', 'coord', $branch->id);
    $workshop = Course::create([
        'name' => 'Taller Inicial',
        'duration_in_weeks' => 3,
        'is_workshop' => true,
    ]);

    $response = $this->actingAs($coord)->put(route('admin.workshops.update', $workshop), [
        'name' => 'Taller Actualizado',
        'duration_in_weeks' => 6,
        'description' => 'Nueva descripción ampliada.',
    ]);

    $response->assertSessionHasNoErrors();
    $workshop->refresh();
    expect($workshop->name)->toBe('Taller Actualizado');
    expect($workshop->duration_in_weeks)->toBe(6);
});

test('secretary cannot update a workshop', function () {
    $branch = Branch::create(['name' => 'Sede Comas', 'code' => 'COM']);
    $sec = createWorkshopStaffUser('sec_user', 'secre', $branch->id);
    $workshop = Course::create([
        'name' => 'Taller Existente',
        'duration_in_weeks' => 4,
        'is_workshop' => true,
    ]);

    $response = $this->actingAs($sec)->put(route('admin.workshops.update', $workshop), [
        'name' => 'Intento Modificar Taller',
        'duration_in_weeks' => 8,
    ]);

    $response->assertStatus(403);
    $workshop->refresh();
    expect($workshop->name)->toBe('Taller Existente');
});

test('cannot delete a workshop if it has scheduled courses', function () {
    $branch = Branch::create(['name' => 'Sede Comas', 'code' => 'COM']);
    $admin = createWorkshopStaffUser('admin_user', 'admin', null);
    $workshop = Course::create([
        'name' => 'Taller Con Clases',
        'duration_in_weeks' => 4,
        'is_workshop' => true,
    ]);

    ScheduledCourse::create([
        'branch_id' => $branch->id,
        'course_id' => $workshop->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addWeeks(4)->toDateString(),
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.workshops.destroy', $workshop));

    $response->assertSessionHasErrors('workshop');
    $this->assertDatabaseHas('courses', [
        'id' => $workshop->id,
        'deleted_at' => null,
    ]);
});

test('branch coordinator can delete a workshop without scheduled courses', function () {
    $branch = Branch::create(['name' => 'Sede Comas', 'code' => 'COM']);
    $coord = createWorkshopStaffUser('coord_user', 'coord', $branch->id);
    $workshop = Course::create([
        'name' => 'Taller Vacio',
        'duration_in_weeks' => 3,
        'is_workshop' => true,
    ]);

    $response = $this->actingAs($coord)->delete(route('admin.workshops.destroy', $workshop));

    $response->assertSessionHasNoErrors();
    $this->assertSoftDeleted('courses', [
        'id' => $workshop->id,
    ]);
});

test('can create a course directly attached to a module', function () {
    $admin = createWorkshopStaffUser('admin_user_mod', 'admin', null);
    $module = Module::create([
        'name' => 'Módulo Contable',
        'duration_in_weeks' => 12,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.workshops.store'), [
        'name' => 'Contabilidad Básica I',
        'duration_in_weeks' => 4,
        'description' => 'Libros contables y asientos.',
        'is_workshop' => false,
        'module_id' => $module->id,
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('courses', [
        'name' => 'Contabilidad Básica I',
        'module_id' => $module->id,
        'is_workshop' => false,
    ]);
});
