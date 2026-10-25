<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DayOfWeek;
use App\Enums\DocumentType;
use App\Enums\EnrollmentStatus;
use App\Enums\StudentCareerStatus;
use App\Models\Attendance;
use App\Models\Branch;
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
        // 1. Sedes Institucionales (CETPRO IISEP)
        $sedeComas = Branch::create([
            'name' => 'Sede Comas',
            'code' => 'COM',
            'address' => 'Av. Túpac Amaru 3850 - La Pascana (Costado Plaza Vea)',
            'phone' => '970518364',
            'is_active' => true,
        ]);

        $sedePuentePiedra = Branch::create([
            'name' => 'Sede Puente Piedra',
            'code' => 'PUP',
            'address' => 'Jr. Ricardo Palma 241 (2° piso de la RENIEC)',
            'phone' => '987710344',
            'is_active' => true,
        ]);

        // 2. Aulas y Laboratorios por Sede
        $aula101Comas = Classroom::create([
            'branch_id' => $sedeComas->id,
            'name' => 'Aula 101 - Teoría',
            'capacity' => 35,
        ]);
        $lab1Comas = Classroom::create([
            'branch_id' => $sedeComas->id,
            'name' => 'Laboratorio de Cómputo 1',
            'capacity' => 24,
        ]);
        $aula201PuentePiedra = Classroom::create([
            'branch_id' => $sedePuentePiedra->id,
            'name' => 'Aula 201 - Multiusos',
            'capacity' => 30,
        ]);
        $tallerBellezaPuentePiedra = Classroom::create([
            'branch_id' => $sedePuentePiedra->id,
            'name' => 'Taller de Estética y Cosmetología',
            'capacity' => 20,
        ]);

        // 3. Carreras Técnicas Profesionales (con duración en semanas)
        $carreraComputacion = Career::create([
            'name' => 'Computación e Informática',
            'description' => 'Carrera técnica profesional de desarrollo de software, administración de redes y soporte.',
            'duration_in_weeks' => 104, // 2 años lectivos
        ]);

        $carreraLogistica = Career::create([
            'name' => 'Logística y Almacenes',
            'description' => 'Especialista en administración de inventarios, cadena de suministro y comercio.',
            'duration_in_weeks' => 104,
        ]);

        $carreraGastronomia = Career::create([
            'name' => 'Gastronomía y Arte Culinario',
            'description' => 'Formación integral en técnicas culinarias nacionales e internacionales y gestión de restaurantes.',
            'duration_in_weeks' => 104,
        ]);

        // 4. Módulos Formativos (pueden ser de carreras o llevarse de forma independiente)
        $moduloOfimatica = Module::create([
            'name' => 'Ofimática Empresarial',
            'description' => 'Word, Excel avanzado, PowerPoint y gestión de documentos en la nube.',
            'duration_in_weeks' => 8, // 2 meses
        ]);

        $moduloEnsamblaje = Module::create([
            'name' => 'Ensamblaje y Mantenimiento de PC',
            'description' => 'Diagnóstico de hardware, armado de computadoras, instalación de SO y redes LAN.',
            'duration_in_weeks' => 8,
        ]);

        $moduloCajero = Module::create([
            'name' => 'Cajero Bancario y Comercial',
            'description' => 'Detección de billetes, sistemas financieros, arqueo de caja y servicio al cliente.',
            'duration_in_weeks' => 8,
        ]);

        $moduloPasteleria = Module::create([
            'name' => 'Pastelería y Repostería Fina',
            'description' => 'Técnicas de masas, horneado, decoración y postres internacionales.',
            'duration_in_weeks' => 12, // 3 meses
        ]);

        // Asociar módulos a carreras
        $carreraComputacion->modules()->attach([
            $moduloOfimatica->id => ['order' => 1],
            $moduloEnsamblaje->id => ['order' => 2],
        ]);
        $carreraLogistica->modules()->attach([
            $moduloCajero->id => ['order' => 1],
        ]);
        $carreraGastronomia->modules()->attach([
            $moduloPasteleria->id => ['order' => 1],
        ]);

        // 5. Cursos y Talleres (unidades atómicas con duración y bandera is_workshop)
        $cursoExcel = Course::create([
            'module_id' => $moduloOfimatica->id,
            'name' => 'Excel Avanzado y Macros',
            'description' => 'Fórmulas lógicas, tablas dinámicas, dashboards y automatización VBA.',
            'duration_in_weeks' => 4,
            'is_workshop' => false,
        ]);

        $cursoHardware = Course::create([
            'module_id' => $moduloEnsamblaje->id,
            'name' => 'Arquitectura de Hardware y Diagnóstico',
            'description' => 'Detección de fallas en placas, memorias, fuentes y almacenamiento.',
            'duration_in_weeks' => 4,
            'is_workshop' => false,
        ]);

        // Talleres cortos independientes
        $tallerBarber = Course::create([
            'module_id' => null,
            'name' => 'Taller de Barber Shop y Cortes Urbanos',
            'description' => 'Técnicas de degradado, fade, afeitado tradicional y perfilado de barba.',
            'duration_in_weeks' => 6,
            'is_workshop' => true,
        ]);

        $tallerUnas = Course::create([
            'module_id' => null,
            'name' => 'Taller de Uñas Acrílicas y Polygel',
            'description' => 'Esculpido en acrílico, encapsulados, mano alzada y 3D.',
            'duration_in_weeks' => 4,
            'is_workshop' => true,
        ]);

        $tallerCelulares = Course::create([
            'module_id' => null,
            'name' => 'Taller de Reparación de Celulares y Tablets',
            'description' => 'Cambio de pantallas, micro-soldadura, diagnóstico de placas y flasheo.',
            'duration_in_weeks' => 8,
            'is_workshop' => true,
        ]);

        // 6. Usuarios e Identidades
        // 6.1 Personal Administrativo / Staff de Sede Comas
        $userStaffComas = User::create([
            'first_name' => 'Sofia',
            'paternal_surname' => 'Torres',
            'maternal_surname' => 'Castro',
            'document' => '45678901',
            'document_type' => DocumentType::DNI,
            'birth_date' => Carbon::parse('1988-04-12'),
            'email' => 'sofia.torres@iisep.edu.pe',
            'phone' => '970518364',
            'username' => 'storres',
            'password' => 'admin123',
        ]);
        Staff::create([
            'user_id' => $userStaffComas->id,
            'branch_id' => $sedeComas->id,
            'position' => 'Coordinadora Académica - Sede Comas',
        ]);

        // 6.2 Docente 1 (Profesor de Computación y Hardware)
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
            'specialty' => 'Ingeniería de Sistemas y Soporte Técnico',
        ]);

        // 6.3 Docente 2 (Instructor de Barbería que además estudia Excel en Comas)
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
            'specialty' => 'Estilismo Masculino y Barbería Profesional',
        ]);
        $studentGomez = Student::create([
            'user_id' => $userDocente2->id,
        ]);

        // 6.4 Apoderado (Padre de familia en Sede Comas)
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

        // 6.5 Alumno 1 (Hijo menor inscrito en carrera completa en Sede Comas)
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

        // 6.6 Alumna 2 (Mayor de edad, inscrita en Sede Puente Piedra)
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

        // 7. Admisión a Carreras (vinculadas a la sede de estudio)
        $scDiego = StudentCareer::create([
            'student_id' => $studentDiego->id,
            'career_id' => $carreraComputacion->id,
            'branch_id' => $sedeComas->id,
            'admission_date' => Carbon::parse('2026-03-01'),
            'status' => StudentCareerStatus::ACTIVO,
        ]);

        $scValeria = StudentCareer::create([
            'student_id' => $studentValeria->id,
            'career_id' => $carreraComputacion->id,
            'branch_id' => $sedePuentePiedra->id,
            'admission_date' => Carbon::parse('2026-03-01'),
            'status' => StudentCareerStatus::ACTIVO,
        ]);

        // 8. Cursos Programados (Apertura por sede y docente)
        // 8.1 En Sede Comas: Hardware y Ensamblaje
        $progHardwareComas = ScheduledCourse::create([
            'branch_id' => $sedeComas->id,
            'course_id' => $cursoHardware->id,
            'teacher_id' => $teacherRojas->id,
            'start_date' => Carbon::parse('2026-10-01'),
            'end_date' => Carbon::parse('2026-10-31'),
        ]);

        // 8.2 En Sede Comas: Excel Avanzado (Ofimática)
        $progExcelComas = ScheduledCourse::create([
            'branch_id' => $sedeComas->id,
            'course_id' => $cursoExcel->id,
            'teacher_id' => $teacherRojas->id,
            'start_date' => Carbon::parse('2026-10-01'),
            'end_date' => Carbon::parse('2026-10-31'),
        ]);

        // 8.3 En Sede Puente Piedra: Taller de Barber Shop
        $progBarberPuentePiedra = ScheduledCourse::create([
            'branch_id' => $sedePuentePiedra->id,
            'course_id' => $tallerBarber->id,
            'teacher_id' => $teacherGomez->id,
            'start_date' => Carbon::parse('2026-10-01'),
            'end_date' => Carbon::parse('2026-11-15'),
        ]);

        // 9. Horarios de Clases (en las aulas de cada sede)
        // Hardware en Comas: Lunes y Miércoles 08:00 - 10:00 (Lab 1 Comas)
        CourseSchedule::create([
            'scheduled_course_id' => $progHardwareComas->id,
            'classroom_id' => $lab1Comas->id,
            'day_of_week' => DayOfWeek::LUNES,
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
        ]);
        CourseSchedule::create([
            'scheduled_course_id' => $progHardwareComas->id,
            'classroom_id' => $lab1Comas->id,
            'day_of_week' => DayOfWeek::MIERCOLES,
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
        ]);

        // Excel en Comas: Martes y Jueves 18:00 - 20:00 (Aula 101 Comas)
        CourseSchedule::create([
            'scheduled_course_id' => $progExcelComas->id,
            'classroom_id' => $aula101Comas->id,
            'day_of_week' => DayOfWeek::MARTES,
            'start_time' => '18:00:00',
            'end_time' => '20:00:00',
        ]);

        // Barber Shop en Puente Piedra: Sábados 14:00 - 18:00 (Aula 201 Puente Piedra)
        CourseSchedule::create([
            'scheduled_course_id' => $progBarberPuentePiedra->id,
            'classroom_id' => $aula201PuentePiedra->id,
            'day_of_week' => DayOfWeek::SABADO,
            'start_time' => '14:00:00',
            'end_time' => '18:00:00',
        ]);

        // 10. Matrículas (Enrollments)
        // 10.1 Diego en Hardware (como parte de su carrera en Comas, con apoderado)
        Enrollment::create([
            'scheduled_course_id' => $progHardwareComas->id,
            'student_id' => $studentDiego->id,
            'student_career_id' => $scDiego->id,
            'module_id' => $moduloEnsamblaje->id,
            'guardian_id' => $userPadre->id,
            'enrollment_date' => Carbon::parse('2026-10-01'),
            'status' => EnrollmentStatus::ACTIVO,
        ]);

        // 10.2 Valeria en Hardware (como parte de su carrera, independiente)
        Enrollment::create([
            'scheduled_course_id' => $progHardwareComas->id,
            'student_id' => $studentValeria->id,
            'student_career_id' => $scValeria->id,
            'module_id' => $moduloEnsamblaje->id,
            'guardian_id' => null,
            'enrollment_date' => Carbon::parse('2026-10-01'),
            'status' => EnrollmentStatus::ACTIVO,
        ]);

        // 10.3 Carlos Gomez (Docente) matriculado en el Módulo Independiente de Ofimática (Excel)
        // Sin carrera técnica (estudia solo el módulo)
        Enrollment::create([
            'scheduled_course_id' => $progExcelComas->id,
            'student_id' => $studentGomez->id,
            'student_career_id' => null,
            'module_id' => $moduloOfimatica->id,
            'guardian_id' => null,
            'enrollment_date' => Carbon::parse('2026-10-02'),
            'status' => EnrollmentStatus::ACTIVO,
        ]);

        // 10.4 Valeria Rios: tiene convalidado Excel Avanzado por estudios previos
        Enrollment::create([
            'scheduled_course_id' => $progExcelComas->id,
            'student_id' => $studentValeria->id,
            'student_career_id' => $scValeria->id,
            'module_id' => $moduloOfimatica->id,
            'guardian_id' => null,
            'enrollment_date' => Carbon::parse('2026-03-05'),
            'status' => EnrollmentStatus::CONVALIDADO,
        ]);

        // 11. Registro de Asistencias (Attendances)
        // Sesión 1: 2026-10-05 (Lunes) en Lab Comas
        Attendance::create([
            'scheduled_course_id' => $progHardwareComas->id,
            'student_id' => $studentDiego->id,
            'date' => Carbon::parse('2026-10-05'),
            'attended' => true,
            'notes' => 'Puntual en laboratorio',
        ]);

        Attendance::create([
            'scheduled_course_id' => $progHardwareComas->id,
            'student_id' => $studentValeria->id,
            'date' => Carbon::parse('2026-10-05'),
            'attended' => true,
            'notes' => 'Puntual en laboratorio',
        ]);

        // Sesión 2: 2026-10-07 (Miércoles)
        Attendance::create([
            'scheduled_course_id' => $progHardwareComas->id,
            'student_id' => $studentDiego->id,
            'date' => Carbon::parse('2026-10-07'),
            'attended' => false,
            'notes' => 'Falta justificada por apoderado',
        ]);

        Attendance::create([
            'scheduled_course_id' => $progHardwareComas->id,
            'student_id' => $studentValeria->id,
            'date' => Carbon::parse('2026-10-07'),
            'attended' => true,
            'notes' => 'Práctica destacada',
        ]);
    }
}
