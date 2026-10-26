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
        // ==========================================
        // 1. SEDES INSTITUCIONALES (CETPRO IISEP)
        // ==========================================
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

        // ==========================================
        // 2. AULAS Y LABORATORIOS POR SEDE
        // ==========================================
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

        $tallerGastroComas = Classroom::create([
            'branch_id' => $sedeComas->id,
            'name' => 'Taller de Gastronomía y Cocina',
            'capacity' => 20,
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

        $lab2PuentePiedra = Classroom::create([
            'branch_id' => $sedePuentePiedra->id,
            'name' => 'Laboratorio de Cómputo 2',
            'capacity' => 25,
        ]);

        // ==========================================
        // 3. CARRERAS TÉCNICAS PROFESIONALES
        // ==========================================
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

        // Sedes habilitadas por carrera
        $carreraComputacion->branches()->attach([$sedeComas->id, $sedePuentePiedra->id]);
        $carreraLogistica->branches()->attach([$sedeComas->id]);
        $carreraGastronomia->branches()->attach([$sedeComas->id, $sedePuentePiedra->id]);

        // ==========================================
        // 4. MÓDULOS FORMATIVOS
        // ==========================================
        $moduloOfimatica = Module::create([
            'name' => 'Ofimática Empresarial',
            'description' => 'Word, Excel avanzado, PowerPoint y gestión de documentos en la nube.',
            'duration_in_weeks' => 8,
        ]);

        $moduloEnsamblaje = Module::create([
            'name' => 'Ensamblaje y Mantenimiento de PC',
            'description' => 'Diagnóstico de hardware, armado de computadoras, instalación de SO y redes LAN.',
            'duration_in_weeks' => 8,
        ]);

        $moduloRedes = Module::create([
            'name' => 'Redes y Conectividad',
            'description' => 'Topologías, cableado estructurado, configuración de routers y seguridad básica.',
            'duration_in_weeks' => 8,
        ]);

        $moduloCajero = Module::create([
            'name' => 'Cajero Bancario y Comercial',
            'description' => 'Detección de billetes, sistemas financieros, arqueo de caja y servicio al cliente.',
            'duration_in_weeks' => 8,
        ]);

        $moduloAlmacen = Module::create([
            'name' => 'Gestión de Almacenes e Inventarios',
            'description' => 'Control de inventarios, métodos Kardex, compras y logística de distribución.',
            'duration_in_weeks' => 8,
        ]);

        $moduloPasteleria = Module::create([
            'name' => 'Pastelería y Repostería Fina',
            'description' => 'Técnicas de masas, horneado, decoración y postres internacionales.',
            'duration_in_weeks' => 12,
        ]);

        $moduloCocina = Module::create([
            'name' => 'Cocina Peruana e Internacional',
            'description' => 'Técnicas básicas de corte, fondos, guisos tradicionales y cocina fusión.',
            'duration_in_weeks' => 12,
        ]);

        // Asociar módulos a carreras formativas
        $carreraComputacion->modules()->attach([
            $moduloOfimatica->id => ['order' => 1],
            $moduloEnsamblaje->id => ['order' => 2],
            $moduloRedes->id => ['order' => 3],
        ]);

        $carreraLogistica->modules()->attach([
            $moduloCajero->id => ['order' => 1],
            $moduloAlmacen->id => ['order' => 2],
        ]);

        $carreraGastronomia->modules()->attach([
            $moduloPasteleria->id => ['order' => 1],
            $moduloCocina->id => ['order' => 2],
        ]);

        // ==========================================
        // 5. CURSOS Y TALLERES ATÓMICOS
        // ==========================================
        // Cursos pertenecientes a Módulos
        $cursoExcel = Course::create([
            'module_id' => $moduloOfimatica->id,
            'name' => 'Excel Avanzado y Macros',
            'description' => 'Fórmulas lógicas, tablas dinámicas, dashboards y automatización VBA.',
            'duration_in_weeks' => 4,
            'is_workshop' => false,
        ]);

        $cursoWord = Course::create([
            'module_id' => $moduloOfimatica->id,
            'name' => 'Word Empresarial y Gestión Documental',
            'description' => 'Correspondencia combinada, tablas de contenido y diseño de documentos institucionales.',
            'duration_in_weeks' => 4,
            'is_workshop' => false,
        ]);

        $cursoHardware = Course::create([
            'module_id' => $moduloEnsamblaje->id,
            'name' => 'Arquitectura de Hardware y Diagnóstico',
            'description' => 'Detección de fallas en placas madre, memorias RAM, fuentes y almacenamiento.',
            'duration_in_weeks' => 4,
            'is_workshop' => false,
        ]);

        $cursoMantenimiento = Course::create([
            'module_id' => $moduloEnsamblaje->id,
            'name' => 'Mantenimiento Preventivo y Correctivo de PC',
            'description' => 'Limpieza de componentes, optimización de sistemas operativos y resolución de fallas comunes.',
            'duration_in_weeks' => 4,
            'is_workshop' => false,
        ]);

        $cursoRedes = Course::create([
            'module_id' => $moduloRedes->id,
            'name' => 'Redes LAN y Cableado Estructurado',
            'description' => 'Crimpeado de cables RJ-45, configuración de routers y switchs en red local.',
            'duration_in_weeks' => 4,
            'is_workshop' => false,
        ]);

        $cursoBilletes = Course::create([
            'module_id' => $moduloCajero->id,
            'name' => 'Técnicas de Conteo y Detección de Billetes',
            'description' => 'Reconocimiento de elementos de seguridad en moneda nacional y moneda extranjera.',
            'duration_in_weeks' => 4,
            'is_workshop' => false,
        ]);

        $cursoCaja = Course::create([
            'module_id' => $moduloCajero->id,
            'name' => 'Operaciones de Caja y Arqueo Financiero',
            'description' => 'Cuadre diario, registro en POS, manejo de cheques y transferencias.',
            'duration_in_weeks' => 4,
            'is_workshop' => false,
        ]);

        $cursoKardex = Course::create([
            'module_id' => $moduloAlmacen->id,
            'name' => 'Control de Inventarios y Kardex',
            'description' => 'Métodos PEPS, promedio ponderado, ingresos y salidas en almacén comercial.',
            'duration_in_weeks' => 4,
            'is_workshop' => false,
        ]);

        $cursoMasas = Course::create([
            'module_id' => $moduloPasteleria->id,
            'name' => 'Masas Básicas, Bizcochuelos y Queques',
            'description' => 'Batidos aireados y pesados, técnicas de horneado y formulación de recetas.',
            'duration_in_weeks' => 6,
            'is_workshop' => false,
        ]);

        $cursoDecoracion = Course::create([
            'module_id' => $moduloPasteleria->id,
            'name' => 'Decoración y Pastelería Fina en Masa Elástica',
            'description' => 'Modelado de figuras, forrado de tortas de pisos y técnicas de boquillas.',
            'duration_in_weeks' => 6,
            'is_workshop' => false,
        ]);

        // Talleres Prácticos Cortos Independientes (Sin Módulo)
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

        $tallerMaquillaje = Course::create([
            'module_id' => null,
            'name' => 'Taller de Maquillaje Profesional y Social',
            'description' => 'Preparación de la piel, visagismo, técnicas de ojos ahumados y maquillaje para eventos.',
            'duration_in_weeks' => 4,
            'is_workshop' => true,
        ]);

        $tallerPanaderia = Course::create([
            'module_id' => null,
            'name' => 'Taller de Panadería Artesanal y Masas Fermentadas',
            'description' => 'Elaboración de panes comerciales, masa madre y fermentaciones prolongadas.',
            'duration_in_weeks' => 6,
            'is_workshop' => true,
        ]);

        // ==========================================
        // 6. USUARIOS, PERSONAL Y ALUMNOS
        // ==========================================
        // 6.0 Super Administrador General
        $userSuperAdmin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'first_name' => 'Administrador',
                'paternal_surname' => 'General',
                'maternal_surname' => 'IISEP',
                'document' => '10000001',
                'document_type' => DocumentType::DNI,
                'birth_date' => Carbon::parse('1980-01-01'),
                'email' => 'admin@iisep.edu.pe',
                'phone' => '970518364',
                'password' => 'admin123',
            ]
        );

        Staff::firstOrCreate(
            ['user_id' => $userSuperAdmin->id],
            [
                'branch_id' => null, // Acceso global
                'position' => 'admin',
            ]
        );

        // 6.1 Personal de Coordinación - Sede Comas
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
            'position' => 'coord',
        ]);

        // 6.2 Personal de Secretaría - Sede Comas
        $userSecreComas = User::create([
            'first_name' => 'Milagros',
            'paternal_surname' => 'Castro',
            'maternal_surname' => 'Mendoza',
            'document' => '46789012',
            'document_type' => DocumentType::DNI,
            'birth_date' => Carbon::parse('1992-08-15'),
            'email' => 'milagros.castro@iisep.edu.pe',
            'phone' => '970518365',
            'username' => 'mcastro',
            'password' => 'admin123',
        ]);
        Staff::create([
            'user_id' => $userSecreComas->id,
            'branch_id' => $sedeComas->id,
            'position' => 'secre',
        ]);

        // 6.3 Personal de Coordinación - Sede Puente Piedra
        $userStaffPuentePiedra = User::create([
            'first_name' => 'Luisa',
            'paternal_surname' => 'Flores',
            'maternal_surname' => 'Medina',
            'document' => '47890123',
            'document_type' => DocumentType::DNI,
            'birth_date' => Carbon::parse('1989-10-20'),
            'email' => 'luisa.flores@iisep.edu.pe',
            'phone' => '987710344',
            'username' => 'lflores',
            'password' => 'admin123',
        ]);
        Staff::create([
            'user_id' => $userStaffPuentePiedra->id,
            'branch_id' => $sedePuentePiedra->id,
            'position' => 'coord',
        ]);

        // 6.4 Docentes
        // Docente 1 (Sistemas y Hardware)
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

        // Docente 2 (Barbería y Estilismo)
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

        // Docente 3 (Gastronomía y Pastelería)
        $userDocente3 = User::create([
            'first_name' => 'Lucia Fernanda',
            'paternal_surname' => 'Vega',
            'maternal_surname' => 'Morales',
            'document' => '35678901',
            'document_type' => DocumentType::DNI,
            'birth_date' => Carbon::parse('1987-03-14'),
            'email' => 'lucia.vega@iisep.edu.pe',
            'phone' => '987666777',
            'username' => 'lvega',
            'password' => 'docente123',
        ]);
        $teacherVega = Teacher::create([
            'user_id' => $userDocente3->id,
            'specialty' => 'Gastronomía y Pastelería Internacional',
        ]);

        // Docente 4 (Finanzas y Logística)
        $userDocente4 = User::create([
            'first_name' => 'Roberto Carlos',
            'paternal_surname' => 'Huaman',
            'maternal_surname' => 'Rios',
            'document' => '36789012',
            'document_type' => DocumentType::DNI,
            'birth_date' => Carbon::parse('1981-09-08'),
            'email' => 'roberto.huaman@iisep.edu.pe',
            'phone' => '987888999',
            'username' => 'rhuaman',
            'password' => 'docente123',
        ]);
        $teacherHuaman = Teacher::create([
            'user_id' => $userDocente4->id,
            'specialty' => 'Finanzas, Caja Bancaria y Administración de Almacenes',
        ]);

        // Docente 5 (Estética y Belleza)
        $userDocente5 = User::create([
            'first_name' => 'Patricia',
            'paternal_surname' => 'Navarro',
            'maternal_surname' => 'Meza',
            'document' => '37890123',
            'document_type' => DocumentType::DNI,
            'birth_date' => Carbon::parse('1990-12-05'),
            'email' => 'patricia.navarro@iisep.edu.pe',
            'phone' => '987999111',
            'username' => 'pnavarro',
            'password' => 'docente123',
        ]);
        $teacherNavarro = Teacher::create([
            'user_id' => $userDocente5->id,
            'specialty' => 'Estética de Uñas y Cuidado de la Piel',
        ]);

        // 6.5 Apoderados
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

        $userMadre = User::create([
            'first_name' => 'Carmen Rosa',
            'paternal_surname' => 'Rosas',
            'maternal_surname' => 'Diaz',
            'document' => '13456781',
            'document_type' => DocumentType::DNI,
            'birth_date' => Carbon::parse('1979-05-22'),
            'email' => 'carmen.rosas@gmail.com',
            'phone' => '987888111',
            'username' => 'crosas',
            'password' => 'padre123',
        ]);

        // Apoderados registrados también como estudiantes sin cursos
        Student::create(['user_id' => $userPadre->id]);
        Student::create(['user_id' => $userMadre->id]);

        // 6.6 Alumnos
        // Alumno 1: Diego Mendoza Salazar (Menor de edad, hijo de Juan Carlos)
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
            'guardian_id' => $userPadre->id,
        ]);

        // Alumna 2: Valeria Rios Flores
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
        $studentValeria = Student::create(['user_id' => $userValeria->id]);

        // Alumna 3: Ana Lucía Paredes Castro
        $userAna = User::create([
            'first_name' => 'Ana Lucía',
            'paternal_surname' => 'Paredes',
            'maternal_surname' => 'Castro',
            'document' => '71234567',
            'document_type' => DocumentType::DNI,
            'birth_date' => Carbon::parse('2002-04-11'),
            'email' => 'ana.paredes@iisep.edu.pe',
            'phone' => '987111222',
            'username' => 'aparedes',
            'password' => 'alumno123',
        ]);
        $studentAna = Student::create(['user_id' => $userAna->id]);

        // Alumno 4: Kevin Alexander Morales Flores
        $userKevin = User::create([
            'first_name' => 'Kevin Alexander',
            'paternal_surname' => 'Morales',
            'maternal_surname' => 'Flores',
            'document' => '72345678',
            'document_type' => DocumentType::DNI,
            'birth_date' => Carbon::parse('2001-09-17'),
            'email' => 'kevin.morales@iisep.edu.pe',
            'phone' => '987333555',
            'username' => 'kmorales',
            'password' => 'alumno123',
        ]);
        $studentKevin = Student::create(['user_id' => $userKevin->id]);

        // Alumna 5: Gabriela Milagros Santos Ramos
        $userGabriela = User::create([
            'first_name' => 'Gabriela Milagros',
            'paternal_surname' => 'Santos',
            'maternal_surname' => 'Ramos',
            'document' => '73456789',
            'document_type' => DocumentType::DNI,
            'birth_date' => Carbon::parse('2000-07-25'),
            'email' => 'gabriela.santos@iisep.edu.pe',
            'phone' => '987444666',
            'username' => 'gsantos',
            'password' => 'alumno123',
        ]);
        $studentGabriela = Student::create(['user_id' => $userGabriela->id]);

        // Alumno 6: Jose Luis Quispe Fernandez (Menor de edad, hijo de Carmen Rosa)
        $userJoseLuis = User::create([
            'first_name' => 'Jose Luis',
            'paternal_surname' => 'Quispe',
            'maternal_surname' => 'Fernandez',
            'document' => '74567890',
            'document_type' => DocumentType::DNI,
            'birth_date' => Carbon::parse('2007-03-12'),
            'email' => 'joseluis.quispe@iisep.edu.pe',
            'phone' => '987555777',
            'username' => 'jquispe',
            'password' => 'alumno123',
        ]);
        $studentJoseLuis = Student::create([
            'user_id' => $userJoseLuis->id,
            'guardian_id' => $userMadre->id,
        ]);

        // Alumna 7: Maria Elena Cordova Benites
        $userMariaElena = User::create([
            'first_name' => 'Maria Elena',
            'paternal_surname' => 'Cordova',
            'maternal_surname' => 'Benites',
            'document' => '75678901',
            'document_type' => DocumentType::DNI,
            'birth_date' => Carbon::parse('1999-11-04'),
            'email' => 'maria.cordova@iisep.edu.pe',
            'phone' => '987666888',
            'username' => 'mcordova',
            'password' => 'alumno123',
        ]);
        $studentMariaElena = Student::create(['user_id' => $userMariaElena->id]);

        // ==========================================
        // 7. ADMISIÓN A CARRERAS (STUDENT CAREERS)
        // ==========================================
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

        $scKevin = StudentCareer::create([
            'student_id' => $studentKevin->id,
            'career_id' => $carreraComputacion->id,
            'branch_id' => $sedeComas->id,
            'admission_date' => Carbon::parse('2026-03-01'),
            'status' => StudentCareerStatus::ACTIVO,
        ]);

        $scGabriela = StudentCareer::create([
            'student_id' => $studentGabriela->id,
            'career_id' => $carreraLogistica->id,
            'branch_id' => $sedeComas->id,
            'admission_date' => Carbon::parse('2026-03-01'),
            'status' => StudentCareerStatus::ACTIVO,
        ]);

        $scMariaElena = StudentCareer::create([
            'student_id' => $studentMariaElena->id,
            'career_id' => $carreraGastronomia->id,
            'branch_id' => $sedePuentePiedra->id,
            'admission_date' => Carbon::parse('2026-03-01'),
            'status' => StudentCareerStatus::ACTIVO,
        ]);

        // ==========================================
        // 8. APERTURA DE CURSOS Y AULAS (SCHEDULED COURSES)
        // ==========================================
        // 8.1 Sede Comas: Hardware y Ensamblaje
        $progHardwareComas = ScheduledCourse::create([
            'branch_id' => $sedeComas->id,
            'course_id' => $cursoHardware->id,
            'teacher_id' => $teacherRojas->id,
            'start_date' => Carbon::parse('2026-10-01'),
            'end_date' => Carbon::parse('2026-10-31'),
        ]);

        // 8.2 Sede Comas: Excel Avanzado (Ofimática)
        $progExcelComas = ScheduledCourse::create([
            'branch_id' => $sedeComas->id,
            'course_id' => $cursoExcel->id,
            'teacher_id' => $teacherRojas->id,
            'start_date' => Carbon::parse('2026-10-01'),
            'end_date' => Carbon::parse('2026-10-31'),
        ]);

        // 8.3 Sede Comas: Reparación de Celulares y Tablets
        $progCelularesComas = ScheduledCourse::create([
            'branch_id' => $sedeComas->id,
            'course_id' => $tallerCelulares->id,
            'teacher_id' => $teacherRojas->id,
            'start_date' => Carbon::parse('2026-10-01'),
            'end_date' => Carbon::parse('2026-11-25'),
        ]);

        // 8.4 Sede Comas: Operaciones de Caja y Arqueo
        $progCajaComas = ScheduledCourse::create([
            'branch_id' => $sedeComas->id,
            'course_id' => $cursoCaja->id,
            'teacher_id' => $teacherHuaman->id,
            'start_date' => Carbon::parse('2026-10-01'),
            'end_date' => Carbon::parse('2026-10-31'),
        ]);

        // 8.5 Sede Comas: Masas Básicas y Bizcochuelos
        $progMasasComas = ScheduledCourse::create([
            'branch_id' => $sedeComas->id,
            'course_id' => $cursoMasas->id,
            'teacher_id' => $teacherVega->id,
            'start_date' => Carbon::parse('2026-10-01'),
            'end_date' => Carbon::parse('2026-11-15'),
        ]);

        // 8.6 Sede Puente Piedra: Taller de Barber Shop
        $progBarberPuentePiedra = ScheduledCourse::create([
            'branch_id' => $sedePuentePiedra->id,
            'course_id' => $tallerBarber->id,
            'teacher_id' => $teacherGomez->id,
            'start_date' => Carbon::parse('2026-10-01'),
            'end_date' => Carbon::parse('2026-11-15'),
        ]);

        // 8.7 Sede Puente Piedra: Taller de Uñas Acrílicas
        $progUnasPuentePiedra = ScheduledCourse::create([
            'branch_id' => $sedePuentePiedra->id,
            'course_id' => $tallerUnas->id,
            'teacher_id' => $teacherNavarro->id,
            'start_date' => Carbon::parse('2026-10-01'),
            'end_date' => Carbon::parse('2026-10-31'),
        ]);

        // 8.8 Sede Puente Piedra: Excel Avanzado (Laboratorio 2)
        $progExcelPuentePiedra = ScheduledCourse::create([
            'branch_id' => $sedePuentePiedra->id,
            'course_id' => $cursoExcel->id,
            'teacher_id' => $teacherRojas->id,
            'start_date' => Carbon::parse('2026-10-01'),
            'end_date' => Carbon::parse('2026-10-31'),
        ]);

        // 8.9 Sede Puente Piedra: Decoración y Pastelería Fina
        $progDecoracionPuentePiedra = ScheduledCourse::create([
            'branch_id' => $sedePuentePiedra->id,
            'course_id' => $cursoDecoracion->id,
            'teacher_id' => $teacherVega->id,
            'start_date' => Carbon::parse('2026-10-01'),
            'end_date' => Carbon::parse('2026-11-15'),
        ]);

        // 8.10 Sede Comas: Taller de Panadería Artesanal y Masas Fermentadas (Domingos)
        $progPanaderiaComas = ScheduledCourse::create([
            'branch_id' => $sedeComas->id,
            'course_id' => $tallerPanaderia->id,
            'teacher_id' => $teacherVega->id,
            'start_date' => Carbon::parse('2026-10-01'),
            'end_date' => Carbon::parse('2026-11-15'),
        ]);

        // ==========================================
        // 9. HORARIOS DE CLASES (COURSE SCHEDULES)
        // ==========================================
        // 9.1 Hardware en Comas: Lunes y Miércoles 08:00 - 10:00 (Lab 1)
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

        // 9.2 Excel en Comas: Martes y Jueves 18:00 - 20:00 (Aula 101)
        CourseSchedule::create([
            'scheduled_course_id' => $progExcelComas->id,
            'classroom_id' => $aula101Comas->id,
            'day_of_week' => DayOfWeek::MARTES,
            'start_time' => '18:00:00',
            'end_time' => '20:00:00',
        ]);
        CourseSchedule::create([
            'scheduled_course_id' => $progExcelComas->id,
            'classroom_id' => $aula101Comas->id,
            'day_of_week' => DayOfWeek::JUEVES,
            'start_time' => '18:00:00',
            'end_time' => '20:00:00',
        ]);

        // 9.3 Celulares en Comas: Viernes y Sábados 14:00 - 17:00 (Lab 1)
        CourseSchedule::create([
            'scheduled_course_id' => $progCelularesComas->id,
            'classroom_id' => $lab1Comas->id,
            'day_of_week' => DayOfWeek::VIERNES,
            'start_time' => '14:00:00',
            'end_time' => '17:00:00',
        ]);
        CourseSchedule::create([
            'scheduled_course_id' => $progCelularesComas->id,
            'classroom_id' => $lab1Comas->id,
            'day_of_week' => DayOfWeek::SABADO,
            'start_time' => '14:00:00',
            'end_time' => '17:00:00',
        ]);

        // 9.4 Operaciones de Caja en Comas: Lunes y Miércoles 16:00 - 18:00 (Aula 101)
        CourseSchedule::create([
            'scheduled_course_id' => $progCajaComas->id,
            'classroom_id' => $aula101Comas->id,
            'day_of_week' => DayOfWeek::LUNES,
            'start_time' => '16:00:00',
            'end_time' => '18:00:00',
        ]);
        CourseSchedule::create([
            'scheduled_course_id' => $progCajaComas->id,
            'classroom_id' => $aula101Comas->id,
            'day_of_week' => DayOfWeek::MIERCOLES,
            'start_time' => '16:00:00',
            'end_time' => '18:00:00',
        ]);

        // 9.5 Masas Básicas en Comas: Sábados 09:00 - 13:00 (Taller de Cocina)
        CourseSchedule::create([
            'scheduled_course_id' => $progMasasComas->id,
            'classroom_id' => $tallerGastroComas->id,
            'day_of_week' => DayOfWeek::SABADO,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
        ]);

        // 9.6 Barber Shop en Puente Piedra: Sábados 14:00 - 18:00 (Aula 201)
        CourseSchedule::create([
            'scheduled_course_id' => $progBarberPuentePiedra->id,
            'classroom_id' => $aula201PuentePiedra->id,
            'day_of_week' => DayOfWeek::SABADO,
            'start_time' => '14:00:00',
            'end_time' => '18:00:00',
        ]);

        // 9.7 Uñas Acrílicas en Puente Piedra: Martes y Jueves 15:00 - 18:00 (Taller de Belleza)
        CourseSchedule::create([
            'scheduled_course_id' => $progUnasPuentePiedra->id,
            'classroom_id' => $tallerBellezaPuentePiedra->id,
            'day_of_week' => DayOfWeek::MARTES,
            'start_time' => '15:00:00',
            'end_time' => '18:00:00',
        ]);
        CourseSchedule::create([
            'scheduled_course_id' => $progUnasPuentePiedra->id,
            'classroom_id' => $tallerBellezaPuentePiedra->id,
            'day_of_week' => DayOfWeek::JUEVES,
            'start_time' => '15:00:00',
            'end_time' => '18:00:00',
        ]);

        // 9.8 Excel en Puente Piedra: Sábados 08:00 - 12:00 (Lab 2)
        CourseSchedule::create([
            'scheduled_course_id' => $progExcelPuentePiedra->id,
            'classroom_id' => $lab2PuentePiedra->id,
            'day_of_week' => DayOfWeek::SABADO,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
        ]);

        // 9.9 Decoración Pastelería en Puente Piedra: Domingos 09:00 - 13:00 (Aula 201)
        CourseSchedule::create([
            'scheduled_course_id' => $progDecoracionPuentePiedra->id,
            'classroom_id' => $aula201PuentePiedra->id,
            'day_of_week' => DayOfWeek::DOMINGO,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
        ]);

        // 9.10 Panadería Artesanal en Comas: Domingos 08:00 - 13:00 (Taller de Cocina)
        CourseSchedule::create([
            'scheduled_course_id' => $progPanaderiaComas->id,
            'classroom_id' => $tallerGastroComas->id,
            'day_of_week' => DayOfWeek::DOMINGO,
            'start_time' => '08:00:00',
            'end_time' => '13:00:00',
        ]);

        // ==========================================
        // 10. MATRÍCULAS E INSCRIPCIONES (ENROLLMENTS)
        // ==========================================
        // 10.1 Diego Mendoza en Hardware (Comas) - Activo con Apoderado
        Enrollment::create([
            'scheduled_course_id' => $progHardwareComas->id,
            'student_id' => $studentDiego->id,
            'student_career_id' => $scDiego->id,
            'module_id' => $moduloEnsamblaje->id,
            'guardian_id' => $userPadre->id,
            'enrollment_date' => Carbon::parse('2026-10-01'),
            'status' => EnrollmentStatus::ACTIVO,
        ]);

        // 10.2 Diego Mendoza: Excel Avanzado (Comas) - Convalidado por estudios previos
        Enrollment::create([
            'scheduled_course_id' => $progExcelComas->id,
            'student_id' => $studentDiego->id,
            'student_career_id' => $scDiego->id,
            'module_id' => $moduloOfimatica->id,
            'guardian_id' => $userPadre->id,
            'enrollment_date' => Carbon::parse('2026-03-05'),
            'status' => EnrollmentStatus::CONVALIDADO,
        ]);

        // 10.3 Valeria Rios en Hardware (Comas) - Activo
        Enrollment::create([
            'scheduled_course_id' => $progHardwareComas->id,
            'student_id' => $studentValeria->id,
            'student_career_id' => $scValeria->id,
            'module_id' => $moduloEnsamblaje->id,
            'guardian_id' => null,
            'enrollment_date' => Carbon::parse('2026-10-01'),
            'status' => EnrollmentStatus::ACTIVO,
        ]);

        // 10.4 Valeria Rios: Excel Avanzado (Comas) - Convalidado
        Enrollment::create([
            'scheduled_course_id' => $progExcelComas->id,
            'student_id' => $studentValeria->id,
            'student_career_id' => $scValeria->id,
            'module_id' => $moduloOfimatica->id,
            'guardian_id' => null,
            'enrollment_date' => Carbon::parse('2026-03-05'),
            'status' => EnrollmentStatus::CONVALIDADO,
        ]);

        // 10.5 Carlos Alberto Gomez (Docente y Alumno) en Excel (Comas) - Activo sin carrera
        Enrollment::create([
            'scheduled_course_id' => $progExcelComas->id,
            'student_id' => $studentGomez->id,
            'student_career_id' => null,
            'module_id' => $moduloOfimatica->id,
            'guardian_id' => null,
            'enrollment_date' => Carbon::parse('2026-10-02'),
            'status' => EnrollmentStatus::ACTIVO,
        ]);

        // 10.6 Kevin Morales en Hardware (Comas) - Activo
        Enrollment::create([
            'scheduled_course_id' => $progHardwareComas->id,
            'student_id' => $studentKevin->id,
            'student_career_id' => $scKevin->id,
            'module_id' => $moduloEnsamblaje->id,
            'guardian_id' => null,
            'enrollment_date' => Carbon::parse('2026-10-01'),
            'status' => EnrollmentStatus::ACTIVO,
        ]);

        // 10.7 Kevin Morales en Taller de Celulares (Comas) - Activo
        Enrollment::create([
            'scheduled_course_id' => $progCelularesComas->id,
            'student_id' => $studentKevin->id,
            'student_career_id' => null,
            'module_id' => null,
            'guardian_id' => null,
            'enrollment_date' => Carbon::parse('2026-10-03'),
            'status' => EnrollmentStatus::ACTIVO,
        ]);

        // 10.8 Gabriela Santos en Operaciones de Caja y Arqueo (Comas) - Activo
        Enrollment::create([
            'scheduled_course_id' => $progCajaComas->id,
            'student_id' => $studentGabriela->id,
            'student_career_id' => $scGabriela->id,
            'module_id' => $moduloCajero->id,
            'guardian_id' => null,
            'enrollment_date' => Carbon::parse('2026-10-01'),
            'status' => EnrollmentStatus::ACTIVO,
        ]);

        // 10.9 Maria Elena Cordova en Masas Básicas (Comas) - Activo
        Enrollment::create([
            'scheduled_course_id' => $progMasasComas->id,
            'student_id' => $studentMariaElena->id,
            'student_career_id' => $scMariaElena->id,
            'module_id' => $moduloPasteleria->id,
            'guardian_id' => null,
            'enrollment_date' => Carbon::parse('2026-10-01'),
            'status' => EnrollmentStatus::ACTIVO,
        ]);

        // 10.10 Maria Elena Cordova en Decoración Pastelería (Puente Piedra) - Activo
        Enrollment::create([
            'scheduled_course_id' => $progDecoracionPuentePiedra->id,
            'student_id' => $studentMariaElena->id,
            'student_career_id' => $scMariaElena->id,
            'module_id' => $moduloPasteleria->id,
            'guardian_id' => null,
            'enrollment_date' => Carbon::parse('2026-10-02'),
            'status' => EnrollmentStatus::ACTIVO,
        ]);

        // 10.11 Ana Lucía Paredes en Taller de Uñas (Puente Piedra) - Activo
        Enrollment::create([
            'scheduled_course_id' => $progUnasPuentePiedra->id,
            'student_id' => $studentAna->id,
            'student_career_id' => null,
            'module_id' => null,
            'guardian_id' => null,
            'enrollment_date' => Carbon::parse('2026-10-01'),
            'status' => EnrollmentStatus::ACTIVO,
        ]);

        // 10.12 Ana Lucía Paredes en Barber Shop (Puente Piedra) - Retirado
        Enrollment::create([
            'scheduled_course_id' => $progBarberPuentePiedra->id,
            'student_id' => $studentAna->id,
            'student_career_id' => null,
            'module_id' => null,
            'guardian_id' => null,
            'enrollment_date' => Carbon::parse('2026-10-01'),
            'status' => EnrollmentStatus::RETIRADO,
        ]);

        // 10.13 Jose Luis Quispe en Barber Shop (Puente Piedra) - Activo con Apoderada
        Enrollment::create([
            'scheduled_course_id' => $progBarberPuentePiedra->id,
            'student_id' => $studentJoseLuis->id,
            'student_career_id' => null,
            'module_id' => null,
            'guardian_id' => $userMadre->id,
            'enrollment_date' => Carbon::parse('2026-10-01'),
            'status' => EnrollmentStatus::ACTIVO,
        ]);

        // 10.14 Jose Luis Quispe en Excel Puente Piedra - Preinscrito
        Enrollment::create([
            'scheduled_course_id' => $progExcelPuentePiedra->id,
            'student_id' => $studentJoseLuis->id,
            'student_career_id' => null,
            'module_id' => $moduloOfimatica->id,
            'guardian_id' => $userMadre->id,
            'enrollment_date' => Carbon::parse('2026-10-04'),
            'status' => EnrollmentStatus::PREINSCRITO,
        ]);

        // 10.15 Gabriela Santos en Panadería Artesanal Comas (Clases Domingos) - Activo
        Enrollment::create([
            'scheduled_course_id' => $progPanaderiaComas->id,
            'student_id' => $studentGabriela->id,
            'student_career_id' => null,
            'module_id' => null,
            'guardian_id' => null,
            'enrollment_date' => Carbon::parse('2026-10-01'),
            'status' => EnrollmentStatus::ACTIVO,
        ]);

        // ==========================================
        // 11. REGISTRO DE ASISTENCIAS (ATTENDANCES)
        // ==========================================
        // Sesión 1: 2026-10-05 (Lunes) en Lab Comas (Hardware)
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

        Attendance::create([
            'scheduled_course_id' => $progHardwareComas->id,
            'student_id' => $studentKevin->id,
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

        Attendance::create([
            'scheduled_course_id' => $progHardwareComas->id,
            'student_id' => $studentKevin->id,
            'date' => Carbon::parse('2026-10-07'),
            'attended' => true,
            'notes' => 'Asistencia conforme',
        ]);
    }
}
