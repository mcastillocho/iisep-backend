<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\DayOfWeek;
use App\Enums\DocumentType;
use App\Enums\EnrollmentStatus;
use App\Models\Branch;
use App\Models\Career;
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
        'days' => ['lunes'],
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
        'days' => ['martes'],
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

test('staff can search student by dni', function () {
    $admin = createStaffUser('admin_search', 'admin', null);
    $userStudent = User::create([
        'first_name' => 'Marcos',
        'paternal_surname' => 'Soto',
        'maternal_surname' => 'Perez',
        'document' => '74819203',
        'document_type' => DocumentType::DNI,
        'username' => 'msoto',
        'password' => bcrypt('password'),
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.students.search', ['document' => '74819203']));

    $response->assertOk();
    $response->assertJson([
        'found' => true,
        'document' => '74819203',
        'full_name' => 'Marcos Soto Perez',
        'first_name' => 'Marcos',
        'paternal_surname' => 'Soto',
        'maternal_surname' => 'Perez',
        'already_enrolled' => false,
        'enrolled_course_ids' => [],
    ]);
});

test('matriculas page includes careers list for enrollment flow', function () {
    $admin = createStaffUser('admin_matr', 'admin', null);
    Career::create(['name' => 'Gastronomia', 'description' => 'Alta cocina']);

    $response = $this->actingAs($admin)->get(route('admin.matriculas'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Matriculas')
        ->has('careers')
        ->where('careers.0.name', 'Gastronomia')
    );
});

test('staff can withdraw student enrollment from scheduled course', function () {
    $branch = Branch::create(['name' => 'Sede Central', 'code' => 'CEN']);
    $coord = createStaffUser('coord_withdraw', 'coord', $branch->id);
    $course = Course::create(['name' => 'Curso Baja', 'duration_in_weeks' => 2, 'is_workshop' => true]);

    $sc = ScheduledCourse::create([
        'branch_id' => $branch->id,
        'course_id' => $course->id,
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-30',
    ]);

    $userStudent = User::create([
        'first_name' => 'Pedro',
        'paternal_surname' => 'Navarro',
        'document' => '75619283',
        'document_type' => DocumentType::DNI,
        'username' => 'pnavarro',
        'password' => bcrypt('password'),
    ]);
    $student = Student::create(['user_id' => $userStudent->id]);

    $enrollment = Enrollment::create([
        'scheduled_course_id' => $sc->id,
        'student_id' => $student->id,
        'enrollment_date' => '2026-11-01',
        'status' => EnrollmentStatus::ACTIVO,
    ]);

    $response = $this->actingAs($coord)->delete(route('admin.enrollments.destroy', $enrollment));

    $response->assertSessionHasNoErrors();
    $this->assertSoftDeleted('enrollments', ['id' => $enrollment->id]);
});

test('scheduled course opening is restricted to exactly 1 day per week', function () {
    $admin = createStaffUser('admin_day_test', 'admin', null);
    $branch = Branch::create(['name' => 'Sede Los Olivos', 'code' => 'LOS']);
    $course = Course::create(['name' => 'Marketing Digital', 'duration_in_weeks' => 4, 'is_workshop' => false]);
    $classroom = Classroom::create(['name' => 'Aula 301', 'branch_id' => $branch->id, 'capacity' => 25]);

    $response = $this->actingAs($admin)->post(route('admin.scheduled-courses.store'), [
        'branch_id' => $branch->id,
        'course_id' => $course->id,
        'classroom_id' => $classroom->id,
        'start_date' => '2026-11-01',
        'days' => ['lunes', 'miercoles'],
        'start_time' => '18:00',
        'end_time' => '20:00',
    ]);

    $response->assertSessionHasErrors(['days']);
});

test('student registration parses Peruvian names and never assigns IISEP', function () {
    $branch = Branch::create(['name' => 'Sede Central', 'code' => 'CEN']);
    $admin = createStaffUser('admin_name_test', 'admin', null);
    $course = Course::create(['name' => 'Diseño Gráfico', 'duration_in_weeks' => 4, 'is_workshop' => false]);

    $sc = ScheduledCourse::create([
        'branch_id' => $branch->id,
        'course_id' => $course->id,
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-30',
    ]);

    // Registro con formato con coma: "Apellidos, Nombres"
    $response1 = $this->actingAs($admin)->post(route('admin.enrollments.store'), [
        'scheduled_course_id' => $sc->id,
        'student_full_name' => 'Mendoza Salazar, Diego Alexander',
        'student_document' => '78912345',
        'enrollment_date' => '2026-11-01',
    ]);
    $response1->assertSessionHasNoErrors();

    $user1 = User::where('document', '78912345')->first();
    expect($user1)->not->toBeNull();
    expect($user1->first_name)->toBe('Diego Alexander');
    expect($user1->paternal_surname)->toBe('Mendoza');
    expect($user1->maternal_surname)->toBe('Salazar');
    expect($user1->full_name)->toBe('Diego Alexander Mendoza Salazar');
    expect($user1->full_name)->not->toContain('IISEP');

    // Intento de registro con un solo nombre sin apellido debe fallar validación (exige apellido)
    $response2 = $this->actingAs($admin)->post(route('admin.enrollments.store'), [
        'scheduled_course_id' => $sc->id,
        'student_full_name' => 'Carlos',
        'student_document' => '78912346',
        'enrollment_date' => '2026-11-01',
    ]);
    $response2->assertSessionHasErrors(['student_full_name']);
    $this->assertDatabaseMissing('users', ['document' => '78912346']);

    // Registro válido con "Nombres Apellidos" sin coma
    $response3 = $this->actingAs($admin)->post(route('admin.enrollments.store'), [
        'scheduled_course_id' => $sc->id,
        'student_full_name' => 'Carlos Ramos',
        'student_document' => '78912347',
        'enrollment_date' => '2026-11-01',
    ]);
    $response3->assertSessionHasNoErrors();

    $user3 = User::where('document', '78912347')->first();
    expect($user3)->not->toBeNull();
    expect($user3->first_name)->toBe('Carlos');
    expect($user3->paternal_surname)->toBe('Ramos');
    // Registro con campos separados: first_name, paternal_surname, maternal_surname
    $response4 = $this->actingAs($admin)->post(route('admin.enrollments.store'), [
        'scheduled_course_id' => $sc->id,
        'first_name' => 'Ana Lucía',
        'paternal_surname' => 'Castillo',
        'maternal_surname' => 'Vargas',
        'student_document' => '78912348',
        'enrollment_date' => '2026-11-01',
    ]);
    $response4->assertSessionHasNoErrors();

    $user4 = User::where('document', '78912348')->first();
    expect($user4)->not->toBeNull();
    expect($user4->first_name)->toBe('Ana Lucía');
    expect($user4->paternal_surname)->toBe('Castillo');
    expect($user4->maternal_surname)->toBe('Vargas');
    expect($user4->full_name)->toBe('Ana Lucía Castillo Vargas');

    // Falla si falta el apellido paterno en campos separados
    $response5 = $this->actingAs($admin)->post(route('admin.enrollments.store'), [
        'scheduled_course_id' => $sc->id,
        'first_name' => 'Luis',
        'paternal_surname' => '',
        'student_document' => '78912349',
        'enrollment_date' => '2026-11-01',
    ]);
    $response5->assertSessionHasErrors(['paternal_surname']);
});

test('staff from secretaria upwards can store student and guardian', function () {
    $branch = Branch::create(['name' => 'Sede Central', 'code' => 'CEN']);
    $secre = createStaffUser('secre_alumnos', 'secre', $branch->id);

    $response = $this->actingAs($secre)->post(route('admin.students.store'), [
        'first_name' => 'Renzo',
        'paternal_surname' => 'Palacios',
        'maternal_surname' => 'Rios',
        'document' => '71829304',
        'birth_date' => '2005-06-15',
        'phone' => '987123456',
        'email' => 'renzo@gmail.com',
        'has_guardian' => true,
        'guardian_first_name' => 'Elena',
        'guardian_paternal_surname' => 'Rios',
        'guardian_maternal_surname' => 'Mendoza',
        'guardian_document' => '08765432',
        'guardian_phone' => '912345678',
        'guardian_email' => 'elena@gmail.com',
    ]);

    $response->assertSessionHasNoErrors();

    $studentUser = User::where('document', '71829304')->first();
    expect($studentUser)->not->toBeNull();
    expect($studentUser->student)->not->toBeNull();

    $guardianUser = User::where('document', '08765432')->first();
    expect($guardianUser)->not->toBeNull();
    expect($guardianUser->student)->not->toBeNull();
    expect($studentUser->student->guardian_id)->toBe($guardianUser->id);
    expect($studentUser->student->guardian->full_name)->toBe('Elena Rios Mendoza');
});

test('staff can update student and guardian data', function () {
    $branch = Branch::create(['name' => 'Sede Central', 'code' => 'CEN']);
    $coord = createStaffUser('coord_alumnos', 'coord', $branch->id);

    $studentUser = User::create([
        'first_name' => 'Gabriel',
        'paternal_surname' => 'Cruz',
        'document' => '76543210',
        'document_type' => DocumentType::DNI,
        'username' => 'est_76543210',
        'password' => bcrypt('76543210'),
    ]);
    $student = Student::create(['user_id' => $studentUser->id]);

    $response = $this->actingAs($coord)->put(route('admin.students.update', $student), [
        'first_name' => 'Gabriel Alejandro',
        'paternal_surname' => 'Cruz',
        'maternal_surname' => 'Paredes',
        'document' => '76543210',
        'phone' => '999888777',
        'has_guardian' => true,
        'guardian_first_name' => 'Manuel',
        'guardian_paternal_surname' => 'Cruz',
        'guardian_document' => '10293847',
        'guardian_phone' => '944555666',
    ]);

    $response->assertSessionHasNoErrors();

    $studentUser->refresh();
    $student->refresh();

    expect($studentUser->first_name)->toBe('Gabriel Alejandro');
    expect($studentUser->maternal_surname)->toBe('Paredes');
    expect($studentUser->phone)->toBe('999888777');
    expect($student->guardian)->not->toBeNull();
    expect($student->guardian->first_name)->toBe('Manuel');
    expect($student->guardian->document)->toBe('10293847');
    expect($student->guardian->student)->not->toBeNull();
});

test('alumnos page returns students list with guardian and enrollments', function () {
    $admin = createStaffUser('admin_alumnos_list', 'admin', null);
    $userStudent = User::create([
        'first_name' => 'Fiorella',
        'paternal_surname' => 'Salazar',
        'document' => '72345678',
        'document_type' => DocumentType::DNI,
        'username' => 'est_72345678',
        'password' => bcrypt('password'),
    ]);
    $student = Student::create(['user_id' => $userStudent->id]);

    $response = $this->actingAs($admin)->get(route('admin.alumnos'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Matriculas')
        ->has('students')
        ->where('students.0.document', '72345678')
    );
});

test('guardian is saved as student without courses and cannot be re-registered with another name', function () {
    $branch = Branch::create(['name' => 'Sede Central', 'code' => 'CEN']);
    $coord = createStaffUser('coord_guardian_test', 'coord', $branch->id);

    // 1. Registrar alumno con apoderado
    $this->actingAs($coord)->post(route('admin.students.store'), [
        'first_name' => 'Ana',
        'paternal_surname' => 'Perez',
        'document' => '71112222',
        'has_guardian' => true,
        'guardian_first_name' => 'Rosa',
        'guardian_paternal_surname' => 'Perez',
        'guardian_document' => '09998888',
    ])->assertSessionHasNoErrors();

    // El apoderado debe existir como Student
    $guardianUser = User::where('document', '09998888')->first();
    expect($guardianUser)->not->toBeNull();
    expect($guardianUser->student)->not->toBeNull();

    // 2. Intentar registrar a otra persona como alumno usando el DNI del apoderado
    $response = $this->actingAs($coord)->post(route('admin.students.store'), [
        'first_name' => 'Carlos',
        'paternal_surname' => 'Gomez',
        'document' => '09998888', // Mismo DNI del apoderado
    ]);

    $response->assertSessionHasErrors(['document']);
});

test('student cannot be their own guardian', function () {
    $branch = Branch::create(['name' => 'Sede Central', 'code' => 'CEN']);
    $coord = createStaffUser('coord_self_guardian', 'coord', $branch->id);

    $response = $this->actingAs($coord)->post(route('admin.students.store'), [
        'first_name' => 'Pedro',
        'paternal_surname' => 'Morales',
        'document' => '73334444',
        'has_guardian' => true,
        'guardian_first_name' => 'Pedro',
        'guardian_paternal_surname' => 'Morales',
        'guardian_document' => '73334444', // Mismo DNI
    ]);

    $response->assertSessionHasErrors(['guardian_document']);
});
