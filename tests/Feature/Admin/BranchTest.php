<?php

declare(strict_types=1);

use App\Enums\DocumentType;
use App\Models\Branch;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
