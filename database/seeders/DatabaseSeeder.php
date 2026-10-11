<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DayOfWeek;
use App\Enums\DocumentType;
use App\Enums\EnrollmentStatus;
use App\Enums\StudentCareerStatus;
use App\Models\Attendance;
use App\Models\Career;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseSchedule;
use App\Models\Enrollment;
use App\Models\Module;
use App\Models\ScheduledCourse;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentCareer;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with realistic test data.
     */
    public function run(): void
    {
        // 1. Aulas y Laboratorios
        $aula101 = Classroom::create(['name' => 'Aula 101 - Pabellón A', 'capacity' => 35]);
        $aula202 = Classroom::create(['name' => 'Aula 202 - Pabellón B', 'capacity' => 30]);
        $lab1 = Classroom::create(['name' => 'Laboratorio de Cómputo 1', 'capacity' => 24]);

        // 2. Carreras Profesionales
        $carreraSistemas = Career::create([
            'name' => 'Desarrollo de Sistemas de Información',
            'description' => 'Carrera profesional técnica enfocada en ingeniería de software, arquitectura cloud y bases de datos.',
        ]);

        $carreraAdmin = Career::create([
            'name' => 'Administración y Gestión de Empresas',
            'description' => 'Carrera enfocada en finanzas, liderazgo empresarial, contabilidad y gestión de proyectos.',
        ]);

        // 3. Módulos Formativos
        $moduloSoftware = Module::create([
            'name' => 'Desarrollo de Software y Base de Datos',
            'description' => 'Módulo formativo enfocado en programación estructurada y bases de datos relacionales.',
        ]);

        $moduloInfra = Module::create([
            'name' => 'Infraestructura, Servidores y Redes',
            'description' => 'Módulo formativo sobre conectividad, topologías y seguridad de red.',
        ]);

        $moduloFinanzas = Module::create([
            'name' => 'Gestión Financiera y Contable',
            'description' => 'Módulo enfocado en balances contables y finanzas corporativas.',
        ]);

        // Asociar módulos a carreras con su orden
        $carreraSistemas->modules()->attach([
            $moduloSoftware->id => ['order' => 1],
            $moduloInfra->id => ['order' => 2],
        ]);

        $carreraAdmin->modules()->attach([
            $moduloFinanzas->id => ['order' => 1],
        ]);

        // 4. Cursos
        $cursoBD = Course::create([
            'name' => 'Modelamiento y Administración de Bases de Datos',
            'description' => 'Diseño ER, normalización y SQL avanzado en PostgreSQL.',
        ]);

        $cursoLaravel = Course::create([
            'name' => 'Desarrollo Backend con PHP y Laravel',
            'description' => 'Arquitectura MVC, Eloquent ORM, migraciones y APIs REST.',
        ]);

        $cursoRedes = Course::create([
            'name' => 'Redes de Computadoras y Conectividad',
            'description' => 'Modelo OSI, subnetting, enrutamiento y conmutación.',
        ]);

        $cursoConta = Course::create([
            'name' => 'Contabilidad Financiera y Costos',
            'description' => 'Estados financieros, balance general y contabilidad de costos.',
        ]);

        // Asociar cursos a módulos
        $moduloSoftware->courses()->attach([$cursoBD->id, $cursoLaravel->id]);
        $moduloInfra->courses()->attach([$cursoRedes->id]);
        $moduloFinanzas->courses()->attach([$cursoConta->id]);

        // 5. Usuarios e Identidades
        // 5.1 Personal Administrativo / Staff
        $userStaff = User::create([
            'first_name' => 'Sofia',
            'paternal_surname' => 'Torres',
            'maternal_surname' => 'Castro',
            'document' => '45678901',
            'document_type' => DocumentType::DNI,
            'birth_date' => Carbon::parse('1988-04-12'),
            'email' => 'sofia.torres@iisep.edu.pe',
            'phone' => '987111222',
            'username' => 'storres',
            'password' => 'admin123',
        ]);
        Staff::create([
            'user_id' => $userStaff->id,
            'position' => 'Coordinadora Académica General',
        ]);

        // 5.2 Docente 1 (Profesor principal de software)
        $userDocente1 = User::create([
            'first_name' => 'Marco Antonio',
            'paternal_surname' => 'Rojas',
            'maternal_surname' => 'Quispe',
            'document' => '23456789',
            'document_type' => DocumentType::DNI,
            'birth_date' => Carbon::parse('1982-11-25'),
            'email' => 'marco.rojas@iisep.edu.pe',
            'phone' => '987333444',
            'username' => 'mrojas',
            'password' => 'docente123',
        ]);
        $teacherRojas = Teacher::create([
            'user_id' => $userDocente1->id,
            'specialty' => 'Ingeniería de Software y Arquitectura PostgreSQL',
        ]);

        // 5.3 Docente 2 (Docente de redes que además es alumno en Laravel)
        $userDocente2 = User::create([
            'first_name' => 'Carlos Alberto',
            'paternal_surname' => 'Gomez',
            'maternal_surname' => 'Paredes',
            'document' => '34567890',
            'document_type' => DocumentType::DNI,
            'birth_date' => Carbon::parse('1985-06-18'),
            'email' => 'carlos.gomez@iisep.edu.pe',
            'phone' => '987555666',
            'username' => 'cgomez',
            'password' => 'docente123',
        ]);
        $teacherGomez = Teacher::create([
            'user_id' => $userDocente2->id,
            'specialty' => 'Telecomunicaciones y Seguridad de Redes',
        ]);
        $studentGomez = Student::create([
            'user_id' => $userDocente2->id,
        ]);

        // 5.4 Apoderado (Padre de familia)
        $userPadre = User::create([
            'first_name' => 'Juan Carlos',
            'paternal_surname' => 'Mendoza',
            'maternal_surname' => 'Huaman',
            'document' => '12345670',
            'document_type' => DocumentType::DNI,
            'birth_date' => Carbon::parse('1976-02-14'),
            'email' => 'juan.mendoza@gmail.com',
            'phone' => '987777888',
            'username' => 'jmendoza',
            'password' => 'padre123',
        ]);

        // 5.5 Alumno 1 (Hijo menor con tutor/apoderado)
        $userDiego = User::create([
            'first_name' => 'Diego',
            'paternal_surname' => 'Mendoza',
            'maternal_surname' => 'Salazar',
            'document' => '76543210',
            'document_type' => DocumentType::DNI,
            'birth_date' => Carbon::parse('2006-08-20'),
            'email' => 'diego.mendoza@iisep.edu.pe',
            'phone' => '987999000',
            'username' => 'dmendoza',
            'password' => 'alumno123',
        ]);
        $studentDiego = Student::create([
            'user_id' => $userDiego->id,
        ]);

        // 5.6 Alumna 2 (Mayor de edad, independiente)
        $userValeria = User::create([
            'first_name' => 'Valeria',
            'paternal_surname' => 'Rios',
            'maternal_surname' => 'Flores',
            'document' => '78901234',
            'document_type' => DocumentType::DNI,
            'birth_date' => Carbon::parse('2003-01-30'),
            'email' => 'valeria.rios@iisep.edu.pe',
            'phone' => '987222333',
            'username' => 'vrios',
            'password' => 'alumno123',
        ]);
        $studentValeria = Student::create([
            'user_id' => $userValeria->id,
        ]);

        // 6. Admisión a Carreras (StudentCareers)
        $scDiego = StudentCareer::create([
            'student_id' => $studentDiego->id,
            'career_id' => $carreraSistemas->id,
            'admission_date' => Carbon::parse('2026-03-01'),
            'status' => StudentCareerStatus::ACTIVO,
        ]);

        $scValeria = StudentCareer::create([
            'student_id' => $studentValeria->id,
            'career_id' => $carreraSistemas->id,
            'admission_date' => Carbon::parse('2026-03-01'),
            'status' => StudentCareerStatus::ACTIVO,
        ]);

        // 7. Cursos Programados (Periodo Académico 2026-II)
        $progCursoBD = ScheduledCourse::create([
            'course_id' => $cursoBD->id,
            'teacher_id' => $teacherRojas->id,
            'start_date' => Carbon::parse('2026-10-01'),
            'end_date' => Carbon::parse('2026-12-20'),
        ]);

        $progCursoLaravel = ScheduledCourse::create([
            'course_id' => $cursoLaravel->id,
            'teacher_id' => $teacherRojas->id,
            'start_date' => Carbon::parse('2026-10-01'),
            'end_date' => Carbon::parse('2026-12-20'),
        ]);

        $progCursoRedes = ScheduledCourse::create([
            'course_id' => $cursoRedes->id,
            'teacher_id' => $teacherGomez->id,
            'start_date' => Carbon::parse('2026-10-01'),
            'end_date' => Carbon::parse('2026-12-20'),
        ]);

        // 8. Horarios de Clases
        // Curso BD: Lunes y Miércoles en Lab 1 (08:00 - 10:00)
        CourseSchedule::create([
            'scheduled_course_id' => $progCursoBD->id,
            'classroom_id' => $lab1->id,
            'day_of_week' => DayOfWeek::LUNES,
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
        ]);
        CourseSchedule::create([
            'scheduled_course_id' => $progCursoBD->id,
            'classroom_id' => $lab1->id,
            'day_of_week' => DayOfWeek::MIERCOLES,
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
        ]);

        // Curso Laravel: Martes y Jueves en Aula 101 (10:00 - 12:00)
        CourseSchedule::create([
            'scheduled_course_id' => $progCursoLaravel->id,
            'classroom_id' => $aula101->id,
            'day_of_week' => DayOfWeek::MARTES,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
        ]);
        CourseSchedule::create([
            'scheduled_course_id' => $progCursoLaravel->id,
            'classroom_id' => $aula101->id,
            'day_of_week' => DayOfWeek::JUEVES,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
        ]);

        // Curso Redes: Viernes en Aula 202 (14:00 - 18:00)
        CourseSchedule::create([
            'scheduled_course_id' => $progCursoRedes->id,
            'classroom_id' => $aula202->id,
            'day_of_week' => DayOfWeek::VIERNES,
            'start_time' => '14:00:00',
            'end_time' => '18:00:00',
        ]);

        // 9. Matrículas (Enrollments)
        // 9.1 Diego en Base de Datos I con su padre como apoderado
        Enrollment::create([
            'scheduled_course_id' => $progCursoBD->id,
            'student_id' => $studentDiego->id,
            'student_career_id' => $scDiego->id,
            'guardian_id' => $userPadre->id,
            'enrollment_date' => Carbon::parse('2026-10-01'),
            'status' => EnrollmentStatus::ACTIVO,
        ]);

        // 9.2 Valeria en Base de Datos I (independiente)
        Enrollment::create([
            'scheduled_course_id' => $progCursoBD->id,
            'student_id' => $studentValeria->id,
            'student_career_id' => $scValeria->id,
            'guardian_id' => null,
            'enrollment_date' => Carbon::parse('2026-10-01'),
            'status' => EnrollmentStatus::ACTIVO,
        ]);

        // 9.3 Carlos Gomez (Docente de redes) matriculado como Alumno en Laravel
        Enrollment::create([
            'scheduled_course_id' => $progCursoLaravel->id,
            'student_id' => $studentGomez->id,
            'student_career_id' => null,
            'guardian_id' => null,
            'enrollment_date' => Carbon::parse('2026-10-02'),
            'status' => EnrollmentStatus::ACTIVO,
        ]);

        // 10. Registro de Asistencias (Attendances)
        // Sesión 1: 2026-10-05 (Lunes)
        Attendance::create([
            'scheduled_course_id' => $progCursoBD->id,
            'student_id' => $studentDiego->id,
            'date' => Carbon::parse('2026-10-05'),
            'attended' => true,
            'notes' => 'Asistencia puntual',
        ]);

        Attendance::create([
            'scheduled_course_id' => $progCursoBD->id,
            'student_id' => $studentValeria->id,
            'date' => Carbon::parse('2026-10-05'),
            'attended' => true,
            'notes' => 'Asistencia puntual',
        ]);

        // Sesión 2: 2026-10-07 (Miércoles)
        Attendance::create([
            'scheduled_course_id' => $progCursoBD->id,
            'student_id' => $studentDiego->id,
            'date' => Carbon::parse('2026-10-07'),
            'attended' => false,
            'notes' => 'Inasistencia justificada por apoderado',
        ]);

        Attendance::create([
            'scheduled_course_id' => $progCursoBD->id,
            'student_id' => $studentValeria->id,
            'date' => Carbon::parse('2026-10-07'),
            'attended' => true,
            'notes' => 'Participación activa en laboratorio',
        ]);
    }
}
