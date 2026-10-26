<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\DayOfWeek;
use App\Enums\DocumentType;
use App\Enums\EnrollmentStatus;
use App\Models\Branch;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ScheduledCourse;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function createStaffUser(string $username, string $position, ?int $branchId): User
{
    $user = User::create([
        'first_name' => $username,
        'paternal_surname' => 'Perez',
        'maternal_surname' => 'Lopez',
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

test('director general and staff can access matriculas and aperturas page', function () {
    $admin = createStaffUser('admin_user', 'admin', null);
    $branch = Branch::create(['name' => 'Sede Central', 'code' => 'CEN']);
    $course = Course::create(['name' => 'Ensamblaje PC', 'duration_in_weeks' => 4, 'is_workshop' => false]);
    $classroom = Classroom::create(['name' => 'Lab 1', 'branch_id' => $branch->id, 'capacity' => 25]);

    $response = $this->actingAs($admin)->get(route('admin.matriculas'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Matriculas')
        ->has('branches')
        ->has('classrooms')
        ->has('coursesList')
        ->has('teachers')
        ->has('scheduledCourses')
        ->has('enrollments')
    );
});

test('director general can create a new scheduled course opening', function () {
    $admin = createStaffUser('admin_user', 'admin', null);
    $branch = Branch::create(['name' => 'Sede Comas', 'code' => 'COM']);
    $course = Course::create(['name' => 'Excel Avanzado', 'duration_in_weeks' => 4, 'is_workshop' => false]);
    $classroom = Classroom::create(['name' => 'Aula 101', 'branch_id' => $branch->id, 'capacity' => 30]);

    $startDate = Carbon::now('America/Lima')->toDateString();
    $endDate = Carbon::now('America/Lima')->addDays(30)->toDateString();

    $response = $this->actingAs($admin)->post(route('admin.scheduled-courses.store'), [
        'branch_id' => $branch->id,
        'course_id' => $course->id,
        'classroom_id' => $classroom->id,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'days' => ['lunes', 'miercoles'],
        'start_time' => '18:00',
        'end_time' => '20:00',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('scheduled_courses', [
        'branch_id' => $branch->id,
        'course_id' => $course->id,
    ]);

    $this->assertDatabaseHas('course_schedules', [
        'classroom_id' => $classroom->id,
        'day_of_week' => DayOfWeek::LUNES->value,
        'start_time' => '18:00:00',
        'end_time' => '20:00:00',
    ]);

    $this->assertDatabaseHas('course_schedules', [
        'classroom_id' => $classroom->id,
        'day_of_week' => DayOfWeek::MIERCOLES->value,
        'start_time' => '18:00:00',
        'end_time' => '20:00:00',
    ]);
});

test('coordinador can create scheduled course in their branch', function () {
    $branch = Branch::create(['name' => 'Sede Puente Piedra', 'code' => 'PUP']);
    $coord = createStaffUser('coord_pup', 'coord', $branch->id);
    $course = Course::create(['name' => 'Barbería Profesional', 'duration_in_weeks' => 6, 'is_workshop' => true]);
    $classroom = Classroom::create(['name' => 'Taller 1', 'branch_id' => $branch->id, 'capacity' => 20]);

    $response = $this->actingAs($coord)->post(route('admin.scheduled-courses.store'), [
        'branch_id' => $branch->id,
        'course_id' => $course->id,
        'classroom_id' => $classroom->id,
        'start_date' => '2026-11-01',
        'end_date' => '2026-12-15',
        'days' => ['sabado'],
        'start_time' => '14:00',
        'end_time' => '18:00',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('scheduled_courses', [
        'branch_id' => $branch->id,
        'course_id' => $course->id,
    ]);
});

test('coordinador cannot create scheduled course in another branch', function () {
    $branch1 = Branch::create(['name' => 'Sede Comas', 'code' => 'COM']);
    $branch2 = Branch::create(['name' => 'Sede Puente Piedra', 'code' => 'PUP']);
    $coord = createStaffUser('coord_comas', 'coord', $branch1->id);
    $course = Course::create(['name' => 'Ofimática', 'duration_in_weeks' => 4, 'is_workshop' => false]);
    $classroom2 = Classroom::create(['name' => 'Aula 201', 'branch_id' => $branch2->id, 'capacity' => 25]);

    $response = $this->actingAs($coord)->post(route('admin.scheduled-courses.store'), [
        'branch_id' => $branch2->id,
        'course_id' => $course->id,
        'classroom_id' => $classroom2->id,
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-30',
        'days' => ['martes', 'jueves'],
        'start_time' => '08:00',
        'end_time' => '10:00',
    ]);

    $response->assertStatus(403);
    $this->assertDatabaseMissing('scheduled_courses', [
        'branch_id' => $branch2->id,
        'course_id' => $course->id,
    ]);
});

test('secretaria cannot create scheduled course opening', function () {
    $branch = Branch::create(['name' => 'Sede Comas', 'code' => 'COM']);
    $secre = createStaffUser('secre_comas', 'secre', $branch->id);
    $course = Course::create(['name' => 'Redes y Conectividad', 'duration_in_weeks' => 4, 'is_workshop' => false]);
    $classroom = Classroom::create(['name' => 'Lab Redes', 'branch_id' => $branch->id, 'capacity' => 20]);

    $response = $this->actingAs($secre)->post(route('admin.scheduled-courses.store'), [
        'branch_id' => $branch->id,
        'course_id' => $course->id,
        'classroom_id' => $classroom->id,
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-30',
        'days' => ['viernes'],
        'start_time' => '15:00',
        'end_time' => '18:00',
    ]);

    $response->assertStatus(403);
    $this->assertDatabaseMissing('scheduled_courses', [
        'course_id' => $course->id,
    ]);
});

test('secretaria cannot update or delete scheduled course opening', function () {
    $branch = Branch::create(['name' => 'Sede Comas', 'code' => 'COM']);
    $secre = createStaffUser('secre_comas', 'secre', $branch->id);
    $course = Course::create(['name' => 'Mecánica de Motos', 'duration_in_weeks' => 6, 'is_workshop' => true]);
    $classroom = Classroom::create(['name' => 'Taller Mecánica', 'branch_id' => $branch->id, 'capacity' => 15]);

    $sc = ScheduledCourse::create([
        'branch_id' => $branch->id,
        'course_id' => $course->id,
        'start_date' => '2026-11-01',
        'end_date' => '2026-12-15',
    ]);

    // Intento de edición por secretaria
    $updateResponse = $this->actingAs($secre)->put(route('admin.scheduled-courses.update', $sc), [
        'branch_id' => $branch->id,
        'course_id' => $course->id,
        'classroom_id' => $classroom->id,
        'start_date' => '2026-11-05',
        'end_date' => '2026-12-20',
        'days' => ['sabado'],
        'start_time' => '09:00',
        'end_time' => '13:00',
    ]);
    $updateResponse->assertStatus(403);

    // Intento de eliminación por secretaria
    $deleteResponse = $this->actingAs($secre)->delete(route('admin.scheduled-courses.destroy', $sc));
    $deleteResponse->assertStatus(403);
    $this->assertDatabaseHas('scheduled_courses', ['id' => $sc->id]);
});

test('secretaria can enroll students in scheduled course of their branch', function () {
    $branch = Branch::create(['name' => 'Sede Comas', 'code' => 'COM']);
    $secre = createStaffUser('secre_comas', 'secre', $branch->id);
    $course = Course::create(['name' => 'Estética Facial', 'duration_in_weeks' => 4, 'is_workshop' => true]);

    $sc = ScheduledCourse::create([
        'branch_id' => $branch->id,
        'course_id' => $course->id,
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-30',
    ]);

    $response = $this->actingAs($secre)->post(route('admin.enrollments.store'), [
        'scheduled_course_id' => $sc->id,
        'student_full_name' => 'Rosa Maria Benitez',
        'student_document' => '71234567',
        'enrollment_date' => Carbon::now('America/Lima')->toDateString(),
        'status' => EnrollmentStatus::ACTIVO->value,
        'notes' => 'Matrícula regular en secretaría',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('enrollments', [
        'scheduled_course_id' => $sc->id,
        'status' => EnrollmentStatus::ACTIVO->value,
    ]);

    $this->assertDatabaseHas('users', [
        'document' => '71234567',
        'first_name' => 'Rosa',
    ]);
});

test('cannot delete scheduled course with enrolled students', function () {
    $branch = Branch::create(['name' => 'Sede Central', 'code' => 'CEN']);
    $admin = createStaffUser('admin_user', 'admin', null);
    $course = Course::create(['name' => 'Electrónica Digital', 'duration_in_weeks' => 4, 'is_workshop' => false]);

    $sc = ScheduledCourse::create([
        'branch_id' => $branch->id,
        'course_id' => $course->id,
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-30',
    ]);

    $userStudent = User::create([
        'first_name' => 'Carlos',
        'paternal_surname' => 'Alva',
        'document' => '72345678',
        'document_type' => DocumentType::DNI,
        'username' => 'calva',
        'password' => bcrypt('password'),
    ]);
    $student = Student::create(['user_id' => $userStudent->id]);

    Enrollment::create([
        'scheduled_course_id' => $sc->id,
        'student_id' => $student->id,
        'enrollment_date' => '2026-11-01',
        'status' => EnrollmentStatus::ACTIVO,
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.scheduled-courses.destroy', $sc));

    $response->assertSessionHasErrors('error');
    $this->assertDatabaseHas('scheduled_courses', ['id' => $sc->id]);
});

test('can delete scheduled course when it has no enrollments', function () {
    $branch = Branch::create(['name' => 'Sede Central', 'code' => 'CEN']);
    $coord = createStaffUser('coord_central', 'coord', $branch->id);
    $course = Course::create(['name' => 'Curso Vacio', 'duration_in_weeks' => 2, 'is_workshop' => true]);

    $sc = ScheduledCourse::create([
        'branch_id' => $branch->id,
        'course_id' => $course->id,
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-30',
    ]);

    $response = $this->actingAs($coord)->delete(route('admin.scheduled-courses.destroy', $sc));

    $response->assertSessionHasNoErrors();
    $this->assertSoftDeleted('scheduled_courses', ['id' => $sc->id]);
});

test('admin dashboard includes scheduled courses and enrollments', function () {
    $admin = createStaffUser('admin_dash', 'admin', null);
    $branch = Branch::create(['name' => 'Sede Central', 'code' => 'CEN']);
    $course = Course::create(['name' => 'Curso Dash', 'duration_in_weeks' => 2, 'is_workshop' => true]);

    ScheduledCourse::create([
        'branch_id' => $branch->id,
        'course_id' => $course->id,
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-30',
    ]);

    $response = $this->actingAs($admin)->get(route('admin'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Index')
        ->has('scheduledCourses')
        ->has('enrollments')
    );
});
