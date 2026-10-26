<?php

declare(strict_types=1);

use App\Enums\DayOfWeek;
use App\Enums\DocumentType;
use App\Models\Branch;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseSchedule;
use App\Models\ScheduledCourse;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createUserWithStaff(string $username, string $position, ?int $branchId): User
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

test('director general can create classrooms in any branch', function () {
    $branch = Branch::create(['name' => 'Sede Central', 'code' => 'CEN']);
    $admin = createUserWithStaff('admin_test', 'admin', null);

    $response = $this->actingAs($admin)->post(route('admin.classrooms.store'), [
        'name' => 'Aula Magna Director',
        'branch_id' => $branch->id,
        'capacity' => 45,
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('classrooms', [
        'name' => 'Aula Magna Director',
        'branch_id' => $branch->id,
        'capacity' => 45,
    ]);
});

test('branch coordinator can create classrooms in their own branch', function () {
    $branch = Branch::create(['name' => 'Sede Norte', 'code' => 'NOR']);
    $coord = createUserWithStaff('coord_norte', 'coord', $branch->id);

    $response = $this->actingAs($coord)->post(route('admin.classrooms.store'), [
        'name' => 'Aula Coord 201',
        'branch_id' => $branch->id,
        'capacity' => 30,
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('classrooms', [
        'name' => 'Aula Coord 201',
        'branch_id' => $branch->id,
        'capacity' => 30,
    ]);
});

test('branch coordinator cannot create classrooms in another branch', function () {
    $branch1 = Branch::create(['name' => 'Sede Norte', 'code' => 'NOR']);
    $branch2 = Branch::create(['name' => 'Sede Sur', 'code' => 'SUR']);
    $coord = createUserWithStaff('coord_norte', 'coord', $branch1->id);

    $response = $this->actingAs($coord)->post(route('admin.classrooms.store'), [
        'name' => 'Aula Intento Sur',
        'branch_id' => $branch2->id,
        'capacity' => 30,
    ]);

    $response->assertStatus(403);
    $this->assertDatabaseMissing('classrooms', [
        'name' => 'Aula Intento Sur',
    ]);
});

test('secretary cannot create classrooms', function () {
    $branch = Branch::create(['name' => 'Sede Sur', 'code' => 'SUR']);
    $sec = createUserWithStaff('sec_sur', 'secre', $branch->id);

    $response = $this->actingAs($sec)->post(route('admin.classrooms.store'), [
        'name' => 'Aula No Permitida',
        'branch_id' => $branch->id,
        'capacity' => 25,
    ]);

    $response->assertStatus(403);
    $this->assertDatabaseMissing('classrooms', [
        'name' => 'Aula No Permitida',
    ]);
});

test('secretary can update capacity of classroom in their branch but cannot alter name', function () {
    $branch = Branch::create(['name' => 'Sede Este', 'code' => 'EST']);
    $sec = createUserWithStaff('sec_este', 'secre', $branch->id);

    $classroom = Classroom::create([
        'name' => 'Aula Original',
        'branch_id' => $branch->id,
        'capacity' => 20,
    ]);

    $response = $this->actingAs($sec)->put(route('admin.classrooms.update', $classroom), [
        'name' => 'Intento De Cambio De Nombre',
        'capacity' => 35,
    ]);

    $response->assertSessionHasNoErrors();
    $classroom->refresh();
    // Capacity is updated
    expect($classroom->capacity)->toBe(35);
    // Name remains unchanged because secretary only has permission to update capacity
    expect($classroom->name)->toBe('Aula Original');
});

test('secretary cannot edit classroom of another branch', function () {
    $branch1 = Branch::create(['name' => 'Sede 1', 'code' => 'S1']);
    $branch2 = Branch::create(['name' => 'Sede 2', 'code' => 'S2']);

    $sec = createUserWithStaff('sec_s1', 'secre', $branch1->id);

    $otherClassroom = Classroom::create([
        'name' => 'Aula Sede 2',
        'branch_id' => $branch2->id,
        'capacity' => 20,
    ]);

    $response = $this->actingAs($sec)->put(route('admin.classrooms.update', $otherClassroom), [
        'capacity' => 50,
    ]);

    $response->assertStatus(403);
    $otherClassroom->refresh();
    expect($otherClassroom->capacity)->toBe(20);
});

test('cannot delete classroom if it has course schedules programadas', function () {
    $branch = Branch::create(['name' => 'Sede Central', 'code' => 'CEN']);
    $admin = createUserWithStaff('admin_test', 'admin', null);
    $classroom = Classroom::create([
        'name' => 'Aula Con Clases',
        'branch_id' => $branch->id,
        'capacity' => 25,
    ]);

    $course = Course::create([
        'name' => 'Excel Avanzado',
        'code' => 'EXC-01',
        'duration_weeks' => 4,
        'total_hours' => 20,
    ]);

    $scheduledCourse = ScheduledCourse::create([
        'branch_id' => $branch->id,
        'course_id' => $course->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addWeeks(4)->toDateString(),
    ]);

    CourseSchedule::create([
        'scheduled_course_id' => $scheduledCourse->id,
        'classroom_id' => $classroom->id,
        'day_of_week' => DayOfWeek::LUNES,
        'start_time' => '08:00',
        'end_time' => '10:00',
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.classrooms.destroy', $classroom));

    $response->assertSessionHasErrors('classroom');
    $this->assertDatabaseHas('classrooms', [
        'id' => $classroom->id,
        'deleted_at' => null,
    ]);
});

test('can delete classroom without course schedules', function () {
    $branch = Branch::create(['name' => 'Sede Sur', 'code' => 'SUR']);
    $coord = createUserWithStaff('coord_sur', 'coord', $branch->id);
    $classroom = Classroom::create([
        'name' => 'Aula Vacia',
        'branch_id' => $branch->id,
        'capacity' => 20,
    ]);

    $response = $this->actingAs($coord)->delete(route('admin.classrooms.destroy', $classroom));

    $response->assertSessionHasNoErrors();
    $this->assertSoftDeleted('classrooms', [
        'id' => $classroom->id,
    ]);
});
