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
        'duration_in_weeks' => 4,
        'is_workshop' => false,
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

test('staff of same branch and admin can fetch classroom schedules on demand', function () {
    $branch1 = Branch::create(['name' => 'Sede 1', 'code' => 'S1']);
    $branch2 = Branch::create(['name' => 'Sede 2', 'code' => 'S2']);
    $secre1 = createUserWithStaff('secre_sched_1', 'secre', $branch1->id);
    $secre2 = createUserWithStaff('secre_sched_2', 'secre', $branch2->id);
    $classroom = Classroom::create(['name' => 'Lab 101', 'branch_id' => $branch1->id, 'capacity' => 24]);

    $course = Course::create(['name' => 'Ofimática', 'duration_in_weeks' => 4, 'is_workshop' => false]);
    $sc = ScheduledCourse::create([
        'branch_id' => $branch1->id,
        'course_id' => $course->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addWeeks(4)->toDateString(),
    ]);
    CourseSchedule::create([
        'scheduled_course_id' => $sc->id,
        'classroom_id' => $classroom->id,
        'day_of_week' => DayOfWeek::MARTES,
        'start_time' => '18:00:00',
        'end_time' => '20:00:00',
    ]);

    $this->actingAs($secre1)
        ->getJson(route('admin.classrooms.schedules', $classroom))
        ->assertOk()
        ->assertJson([
            'classroom_id' => $classroom->id,
            'classroom_name' => 'Lab 101',
        ])
        ->assertJsonCount(1, 'schedules');

    $this->actingAs($secre2)
        ->getJson(route('admin.classrooms.schedules', $classroom))
        ->assertStatus(403);
});

test('creating a classroom with duplicate name in the same branch returns validation error while another branch allows it', function () {
    $branch1 = Branch::create(['name' => 'Sede 1', 'code' => 'S1']);
    $branch2 = Branch::create(['name' => 'Sede 2', 'code' => 'S2']);
    $admin = createUserWithStaff('admin_dup_room', 'admin', null);

    Classroom::create(['name' => 'Aula 101', 'branch_id' => $branch1->id, 'capacity' => 25]);

    $this->actingAs($admin)->post(route('admin.classrooms.store'), [
        'name' => 'Aula 101',
        'branch_id' => $branch1->id,
        'capacity' => 30,
    ])->assertSessionHasErrors('name');

    $this->actingAs($admin)->post(route('admin.classrooms.store'), [
        'name' => 'Aula 101',
        'branch_id' => $branch2->id,
        'capacity' => 30,
    ])->assertSessionHasNoErrors();
});

test('updating a classroom validates unique name per branch while allowing its own name', function () {
    $branch = Branch::create(['name' => 'Sede Unica', 'code' => 'UNI']);
    $admin = createUserWithStaff('admin_upd_dup', 'admin', null);

    $room1 = Classroom::create(['name' => 'Aula A', 'branch_id' => $branch->id, 'capacity' => 20]);
    $room2 = Classroom::create(['name' => 'Aula B', 'branch_id' => $branch->id, 'capacity' => 25]);

    $this->actingAs($admin)->put(route('admin.classrooms.update', $room2), [
        'name' => 'Aula A',
        'capacity' => 25,
    ])->assertSessionHasErrors('name');

    $this->actingAs($admin)->put(route('admin.classrooms.update', $room2), [
        'name' => 'Aula B',
        'capacity' => 35,
    ])->assertSessionHasNoErrors();

    expect($room2->fresh()->capacity)->toBe(35);
});

test('deleted classroom name can be reused in the same branch thanks to partial unique index', function () {
    $branch = Branch::create(['name' => 'Sede Reuso', 'code' => 'REU']);
    $admin = createUserWithStaff('admin_reuse_room', 'admin', null);

    $room = Classroom::create(['name' => 'Laboratorio 1', 'branch_id' => $branch->id, 'capacity' => 20]);

    $this->actingAs($admin)->delete(route('admin.classrooms.destroy', $room))->assertSessionHasNoErrors();

    $this->actingAs($admin)->post(route('admin.classrooms.store'), [
        'name' => 'Laboratorio 1',
        'branch_id' => $branch->id,
        'capacity' => 28,
    ])->assertSessionHasNoErrors();

    expect(Classroom::where('branch_id', $branch->id)->where('name', 'Laboratorio 1')->count())->toBe(1);
});

test('admin cannot transfer a classroom with schedules to another branch but can transfer an empty one', function () {
    $branch1 = Branch::create(['name' => 'Sede Origen', 'code' => 'ORI']);
    $branch2 = Branch::create(['name' => 'Sede Destino', 'code' => 'DES']);
    $admin = createUserWithStaff('admin_transfer', 'admin', null);

    $busyRoom = Classroom::create(['name' => 'Aula Ocupada', 'branch_id' => $branch1->id, 'capacity' => 25]);
    $freeRoom = Classroom::create(['name' => 'Aula Libre', 'branch_id' => $branch1->id, 'capacity' => 25]);

    $course = Course::create(['name' => 'Redes', 'duration_in_weeks' => 4, 'is_workshop' => false]);
    $sc = ScheduledCourse::create([
        'branch_id' => $branch1->id,
        'course_id' => $course->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addWeeks(4)->toDateString(),
    ]);
    CourseSchedule::create([
        'scheduled_course_id' => $sc->id,
        'classroom_id' => $busyRoom->id,
        'day_of_week' => DayOfWeek::LUNES,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
    ]);

    // Transfer of busy room is blocked
    $this->actingAs($admin)->put(route('admin.classrooms.update', $busyRoom), [
        'name' => 'Aula Ocupada',
        'capacity' => 25,
        'branch_id' => $branch2->id,
    ])->assertSessionHasErrors('branch_id');
    expect($busyRoom->fresh()->branch_id)->toBe($branch1->id);

    // Transfer of empty room succeeds
    $this->actingAs($admin)->put(route('admin.classrooms.update', $freeRoom), [
        'name' => 'Aula Libre',
        'capacity' => 25,
        'branch_id' => $branch2->id,
    ])->assertSessionHasNoErrors();
    expect($freeRoom->fresh()->branch_id)->toBe($branch2->id);
});

test('classroom schedules endpoint ignores soft-deleted scheduled courses', function () {
    $branch = Branch::create(['name' => 'Sede Horarios', 'code' => 'HOR']);
    $admin = createUserWithStaff('admin_sched_del', 'admin', null);
    $classroom = Classroom::create(['name' => 'Aula 202', 'branch_id' => $branch->id, 'capacity' => 20]);

    $course = Course::create(['name' => 'Diseño', 'duration_in_weeks' => 4, 'is_workshop' => false]);
    $sc = ScheduledCourse::create([
        'branch_id' => $branch->id,
        'course_id' => $course->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addWeeks(4)->toDateString(),
    ]);
    CourseSchedule::create([
        'scheduled_course_id' => $sc->id,
        'classroom_id' => $classroom->id,
        'day_of_week' => DayOfWeek::JUEVES,
        'start_time' => '15:00:00',
        'end_time' => '17:00:00',
    ]);

    $sc->delete();

    $this->actingAs($admin)
        ->getJson(route('admin.classrooms.schedules', $classroom))
        ->assertOk()
        ->assertJsonCount(0, 'schedules');
});
