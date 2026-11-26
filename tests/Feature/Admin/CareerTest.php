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

test('staff can view academic offer while non-staff receives 403', function () {
    $branch = Branch::create(['name' => 'Sede Comas', 'code' => 'COM']);
    $secre = createCareerStaffUser('secre_view_offer', 'secre', $branch->id);
    $studentUser = User::create([
        'first_name' => 'Alumno',
        'paternal_surname' => 'Externo',
        'document' => '79000001',
        'document_type' => DocumentType::DNI,
        'username' => 'alumno_offer_ext',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($secre)->get(route('admin.oferta-academica'))->assertOk();
    $this->actingAs($studentUser)->get(route('admin.oferta-academica'))->assertForbidden();
});

test('director general can create a career with modules and branches assigned', function () {
    $admin = createCareerStaffUser('admin_user', 'admin', null);
    $b1 = Branch::create(['name' => 'Sede Norte', 'code' => 'NOR']);
    $b2 = Branch::create(['name' => 'Sede Sur', 'code' => 'SUR']);
    $mod1 = Module::create(['name' => 'Módulo 1: Fundamentos', 'duration_in_weeks' => 8]);
    $mod2 = Module::create(['name' => 'Módulo 2: Avanzado', 'duration_in_weeks' => 12]);

    $response = $this->actingAs($admin)->post(route('admin.careers.store'), [
        'name' => 'Desarrollo de Software e IA',
        'duration_in_weeks' => 72,
        'description' => 'Carrera técnica profesional en ingeniería de software y machine learning.',
        'modules' => [
            ['id' => $mod1->id, 'order' => 1],
            ['id' => $mod2->id, 'order' => 2],
        ],
        'available_branch_ids' => [$b1->id, $b2->id],
    ]);

    $response->assertSessionHasNoErrors();
    $career = Career::where('name', 'Desarrollo de Software e IA')->first();
    expect($career)->not->toBeNull();
    expect($career->duration_in_weeks)->toBe(72);
    expect($career->modules)->toHaveCount(2);
    expect($career->branches)->toHaveCount(2);
});

test('same module can be shared across multiple careers and assignableModules filters correctly', function () {
    $admin = createCareerStaffUser('admin_shared_mod', 'admin', null);
    $sharedMod = Module::create(['name' => 'Ofimática Compartida', 'duration_in_weeks' => 8]);
    $otherMod = Module::create(['name' => 'Contabilidad', 'duration_in_weeks' => 10]);

    $career1 = Career::create(['name' => 'Administración', 'duration_in_weeks' => 48]);
    $career1->modules()->attach($sharedMod->id, ['order' => 1]);

    // Shared module can be assigned to a second career
    $this->actingAs($admin)->post(route('admin.careers.store'), [
        'name' => 'Secretariado Ejecutivo',
        'modules' => [['id' => $sharedMod->id, 'order' => 1]],
    ])->assertSessionHasNoErrors();

    $career2 = Career::where('name', 'Secretariado Ejecutivo')->firstOrFail();
    expect($career2->modules)->toHaveCount(1);
    expect($career2->duration_in_weeks)->toBe(8);

    // Querying assignable modules for career1 excludes sharedMod (already in career1) and keeps otherMod
    $this->actingAs($admin)
        ->getJson(route('admin.careers.assignable-modules', ['career_id' => $career1->id]))
        ->assertOk()
        ->assertJsonCount(1, 'modules')
        ->assertJsonPath('modules.0.id', $otherMod->id);

    // Without career_id (new career), both active modules are returned
    $this->actingAs($admin)
        ->getJson(route('admin.careers.assignable-modules'))
        ->assertOk()
        ->assertJsonCount(2, 'modules');
});

test('branch coordinator and secretary cannot manage careers or query assignable modules', function () {
    $branch = Branch::create(['name' => 'Sede Lince', 'code' => 'LIN']);
    $coord = createCareerStaffUser('coord_user', 'coord', $branch->id);
    $secre = createCareerStaffUser('secre_user', 'secre', $branch->id);
    $career = Career::create(['name' => 'Gastronomía', 'duration_in_weeks' => 50]);

    foreach ([$coord, $secre] as $user) {
        $this->actingAs($user)->post(route('admin.careers.store'), [
            'name' => 'Carrera No Autorizada',
            'duration_in_weeks' => 40,
        ])->assertForbidden();

        $this->actingAs($user)->put(route('admin.careers.update', $career), [
            'name' => 'Gastronomía Modificada',
            'duration_in_weeks' => 50,
        ])->assertForbidden();

        $this->actingAs($user)->delete(route('admin.careers.destroy', $career))
            ->assertForbidden();

        $this->actingAs($user)->getJson(route('admin.careers.assignable-modules'))
            ->assertForbidden();
    }
});

test('director general can update a career, its module assignments, and branches', function () {
    $admin = createCareerStaffUser('admin_upd_career', 'admin', null);
    $b1 = Branch::create(['name' => 'Sede Este', 'code' => 'EST']);
    $b2 = Branch::create(['name' => 'Sede Oeste', 'code' => 'OES']);

    $career = Career::create([
        'name' => 'Administración de Empresas',
        'duration_in_weeks' => 60,
        'description' => 'Gestión empresarial.',
    ]);
    $career->branches()->attach($b1->id);

    $mod1 = Module::create(['name' => 'Contabilidad Financiera', 'duration_in_weeks' => 8]);
    $career->modules()->attach($mod1->id, ['order' => 1]);
    $mod2 = Module::create(['name' => 'Recursos Humanos', 'duration_in_weeks' => 8]);

    $response = $this->actingAs($admin)->put(route('admin.careers.update', $career), [
        'name' => 'Administración de Negocios Globales',
        'duration_in_weeks' => 64,
        'description' => 'Comercio exterior y finanzas.',
        'modules' => [
            ['id' => $mod1->id, 'order' => 1],
            ['id' => $mod2->id, 'order' => 2],
        ],
        'available_branch_ids' => [$b2->id],
    ]);

    $response->assertSessionHasNoErrors();
    $career->refresh();
    expect($career->name)->toBe('Administración de Negocios Globales');
    expect($career->duration_in_weeks)->toBe(64);
    expect($career->modules)->toHaveCount(2);
    expect($career->branches)->toHaveCount(1);
    expect($career->branches->first()->id)->toBe($b2->id);
});

test('cannot create a duplicate active career name', function () {
    $admin = createCareerStaffUser('admin_dup_career', 'admin', null);
    Career::create(['name' => 'Enfermería Técnica', 'duration_in_weeks' => 70]);

    $this->actingAs($admin)->post(route('admin.careers.store'), [
        'name' => 'Enfermería Técnica',
        'duration_in_weeks' => 60,
    ])->assertSessionHasErrors('name');
});

test('cannot delete a career if students are enrolled in student_careers', function () {
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

test('can delete a career without students even when linked to shared modules without affecting those modules', function () {
    $admin = createCareerStaffUser('admin_user3', 'admin', null);
    $sharedModule = Module::create(['name' => 'Módulo Transversal', 'duration_in_weeks' => 8]);

    $careerToDelete = Career::create(['name' => 'Carrera Antigua', 'duration_in_weeks' => 40]);
    $careerToDelete->modules()->attach($sharedModule->id, ['order' => 1]);

    $otherCareer = Career::create(['name' => 'Carrera Activa', 'duration_in_weeks' => 40]);
    $otherCareer->modules()->attach($sharedModule->id, ['order' => 1]);

    $response = $this->actingAs($admin)->delete(route('admin.careers.destroy', $careerToDelete));

    $response->assertSessionHasNoErrors();
    $this->assertSoftDeleted('careers', ['id' => $careerToDelete->id]);
    $this->assertDatabaseMissing('career_module', ['career_id' => $careerToDelete->id]);
    $this->assertDatabaseHas('modules', ['id' => $sharedModule->id, 'deleted_at' => null]);
    expect($otherCareer->fresh()->modules)->toHaveCount(1);
});
