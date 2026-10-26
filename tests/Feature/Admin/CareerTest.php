<?php

declare(strict_types=1);

use App\Enums\DocumentType;
use App\Models\Branch;
use App\Models\Career;
use App\Models\Module;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentCareer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createCareerStaffUser(string $username, string $position, ?int $branchId): User
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

test('director general can create a career', function () {
    $admin = createCareerStaffUser('admin_user', 'admin', null);

    $response = $this->actingAs($admin)->post(route('admin.careers.store'), [
        'name' => 'Desarrollo de Software e IA',
        'duration_in_weeks' => 72,
        'description' => 'Carrera técnica profesional en ingeniería de software y machine learning.',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('careers', [
        'name' => 'Desarrollo de Software e IA',
        'duration_in_weeks' => 72,
    ]);
});

test('branch coordinator can create a career with modules assigned', function () {
    $branch = Branch::create(['name' => 'Sede Magdalena', 'code' => 'MAG']);
    $coord = createCareerStaffUser('coord_user', 'coord', $branch->id);

    $mod1 = Module::create(['name' => 'Módulo 1: Fundamentos', 'duration_in_weeks' => 8]);
    $mod2 = Module::create(['name' => 'Módulo 2: Avanzado', 'duration_in_weeks' => 12]);

    $response = $this->actingAs($coord)->post(route('admin.careers.store'), [
        'name' => 'Ciberseguridad y Redes',
        'duration_in_weeks' => 48,
        'description' => 'Seguridad perimetral y pentesting.',
        'modules' => [
            ['id' => $mod1->id, 'order' => 1],
            ['id' => $mod2->id, 'order' => 2],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    $career = Career::where('name', 'Ciberseguridad y Redes')->first();
    expect($career)->not->toBeNull();
    expect($career->modules)->toHaveCount(2);
});

test('branch secretary cannot create a career', function () {
    $branch = Branch::create(['name' => 'Sede Lince', 'code' => 'LIN']);
    $secre = createCareerStaffUser('secre_user', 'secre', $branch->id);

    $response = $this->actingAs($secre)->post(route('admin.careers.store'), [
        'name' => 'Carrera No Autorizada',
        'duration_in_weeks' => 40,
    ]);

    $response->assertForbidden();
});

test('branch coordinator can update a career and its module assignments', function () {
    $branch = Branch::create(['name' => 'Sede Ate', 'code' => 'ATE']);
    $coord = createCareerStaffUser('coord_user2', 'coord', $branch->id);

    $career = Career::create([
        'name' => 'Administración de Empresas',
        'duration_in_weeks' => 60,
        'description' => 'Gestión empresarial.',
    ]);

    $mod1 = Module::create(['name' => 'Contabilidad Financiera', 'duration_in_weeks' => 8]);
    $career->modules()->attach($mod1->id, ['order' => 1]);

    $mod2 = Module::create(['name' => 'Recursos Humanos', 'duration_in_weeks' => 8]);

    $response = $this->actingAs($coord)->put(route('admin.careers.update', $career), [
        'name' => 'Administración de Negocios Globales',
        'duration_in_weeks' => 64,
        'description' => 'Comercio exterior y finanzas.',
        'modules' => [
            ['id' => $mod1->id, 'order' => 1],
            ['id' => $mod2->id, 'order' => 2],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('careers', [
        'id' => $career->id,
        'name' => 'Administración de Negocios Globales',
        'duration_in_weeks' => 64,
    ]);
    expect($career->fresh()->modules)->toHaveCount(2);
});

test('branch secretary cannot update a career', function () {
    $branch = Branch::create(['name' => 'Sede Miraflores', 'code' => 'MIR']);
    $secre = createCareerStaffUser('secre_user2', 'secre', $branch->id);

    $career = Career::create([
        'name' => 'Gastronomía',
        'duration_in_weeks' => 50,
    ]);

    $response = $this->actingAs($secre)->put(route('admin.careers.update', $career), [
        'name' => 'Gastronomía Modificada',
        'duration_in_weeks' => 50,
    ]);

    $response->assertForbidden();
});

test('cannot delete a career if students are enrolled', function () {
    $admin = createCareerStaffUser('admin_user2', 'admin', null);
    $branch = Branch::create(['name' => 'Sede Central', 'code' => 'CEN']);

    $career = Career::create([
        'name' => 'Enfermería Técnica',
        'duration_in_weeks' => 70,
    ]);

    $studentUser = User::create([
        'first_name' => 'Ana',
        'paternal_surname' => 'Torres',
        'document' => '77665544',
        'document_type' => DocumentType::DNI,
        'username' => 'atorres',
        'password' => bcrypt('password'),
    ]);

    $student = Student::create([
        'user_id' => $studentUser->id,
    ]);

    StudentCareer::create([
        'student_id' => $student->id,
        'career_id' => $career->id,
        'branch_id' => $branch->id,
        'admission_date' => now()->toDateString(),
        'status' => 'activo',
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.careers.destroy', $career));

    $response->assertSessionHasErrors(['error']);
    $this->assertDatabaseHas('careers', [
        'id' => $career->id,
        'deleted_at' => null,
    ]);
});

test('can delete a career without dependencies', function () {
    $admin = createCareerStaffUser('admin_user3', 'admin', null);

    $career = Career::create([
        'name' => 'Carrera Antigua Sin Matriculas',
        'duration_in_weeks' => 40,
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.careers.destroy', $career));

    $response->assertSessionHasNoErrors();
    $this->assertSoftDeleted('careers', [
        'id' => $career->id,
    ]);
});

test('can create a career with branches assigned', function () {
    $admin = createCareerStaffUser('admin_user4', 'admin', null);
    $b1 = Branch::create(['name' => 'Sede Norte', 'code' => 'NOR']);
    $b2 = Branch::create(['name' => 'Sede Sur', 'code' => 'SUR']);

    $response = $this->actingAs($admin)->post(route('admin.careers.store'), [
        'name' => 'Diseño y Animación Digital',
        'duration_in_weeks' => 50,
        'available_branch_ids' => [$b1->id, $b2->id],
    ]);

    $response->assertSessionHasNoErrors();
    $career = Career::where('name', 'Diseño y Animación Digital')->first();
    expect($career)->not->toBeNull();
    expect($career->branches)->toHaveCount(2);
    $this->assertDatabaseHas('branch_career', [
        'career_id' => $career->id,
        'branch_id' => $b1->id,
    ]);
    $this->assertDatabaseHas('branch_career', [
        'career_id' => $career->id,
        'branch_id' => $b2->id,
    ]);
});

test('can update career branch assignments', function () {
    $admin = createCareerStaffUser('admin_user5', 'admin', null);
    $b1 = Branch::create(['name' => 'Sede Este', 'code' => 'EST']);
    $b2 = Branch::create(['name' => 'Sede Oeste', 'code' => 'OES']);

    $career = Career::create([
        'name' => 'Marketing Empresarial',
        'duration_in_weeks' => 60,
    ]);
    $career->branches()->attach($b1->id);

    // Update to have b2 only
    $response = $this->actingAs($admin)->put(route('admin.careers.update', $career), [
        'name' => 'Marketing Empresarial y Digital',
        'duration_in_weeks' => 60,
        'available_branch_ids' => [$b2->id],
    ]);

    $response->assertSessionHasNoErrors();
    $career->refresh();
    expect($career->branches)->toHaveCount(1);
    expect($career->branches->first()->id)->toBe($b2->id);
});
