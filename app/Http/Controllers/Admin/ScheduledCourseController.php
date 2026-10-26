<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\DayOfWeek;
use App\Enums\DocumentType;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Career;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseSchedule;
use App\Models\Enrollment;
use App\Models\ScheduledCourse;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ScheduledCourseController extends Controller
{
    /**
     * Obtiene los datos de Apertura de Cursos y Matrículas desde la base de datos PostgreSQL.
     *
     * @return array{branches: list<array<string, mixed>>, classrooms: list<array<string, mixed>>, coursesList: list<array<string, mixed>>, teachers: list<array<string, mixed>>, scheduledCourses: list<array<string, mixed>>, enrollments: list<array<string, mixed>>}
     */
    public static function getScheduledCourseData(Request $request): array
    {
        $user = $request->user();
        $staff = $user?->staff;

        // Filtrado por sede si el staff no es Director General (isAdmin)
        $branchQuery = Branch::query()->orderBy('id');
        if ($staff && ! $staff->isAdmin() && $staff->branch_id !== null) {
            $branchQuery->where('id', $staff->branch_id);
        }

        $branches = $branchQuery->get()->map(fn (Branch $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'code' => $b->code ?? 'SED-'.$b->id,
            'address' => $b->address ?? '',
            'phone' => $b->phone ?? '',
        ])->values()->all();

        $allowedBranchIds = collect($branches)->pluck('id')->all();

        // Aulas de las sedes permitidas
        $classrooms = Classroom::query()
            ->whereIn('branch_id', $allowedBranchIds)
            ->orderBy('name')
            ->get()
            ->map(fn (Classroom $c) => [
                'id' => $c->id,
                'branch_id' => $c->branch_id,
                'branch_name' => $c->branch?->name ?? '',
                'name' => $c->name,
                'capacity' => $c->capacity,
            ])->values()->all();

        // Catálogo de carreras técnicas
        $careers = Career::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Career $car) => [
                'id' => $car->id,
                'name' => $car->name,
                'description' => $car->description,
            ])->values()->all();

        // Catálogo de cursos disponibles para aperturar
        $coursesList = Course::query()
            ->with('module.careers')
            ->orderBy('name')
            ->get()
            ->map(fn (Course $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'duration_in_weeks' => $c->duration_in_weeks ?? 4,
                'career_id' => $c->module?->careers?->first()?->id,
                'career_name' => $c->module?->careers?->first()?->name,
                'module_id' => $c->module_id,
                'module_name' => $c->module?->name,
                'is_workshop' => (bool) $c->is_workshop,
            ])->values()->all();

        // Lista de docentes
        $teachers = Teacher::query()
            ->with('user')
            ->get()
            ->map(fn (Teacher $t) => [
                'id' => $t->id,
                'full_name' => $t->full_name ?? ($t->user?->first_name.' '.$t->user?->paternal_surname),
                'specialty' => $t->specialty ?? 'Docente IISEP',
            ])->values()->all();

        // Zona horaria de Perú para determinar estados actuales
        $todayInPeru = Carbon::now('America/Lima')->toDateString();

        // Cursos aperturados reales desde PostgreSQL
        $scheduledCoursesQuery = ScheduledCourse::query()
            ->whereIn('branch_id', $allowedBranchIds)
            ->with([
                'course.module.careers',
                'branch',
                'teacher.user',
                'schedules.classroom',
            ])
            ->withCount(['enrollments as active_enrollments_count' => function ($query) {
                $query->whereIn('status', [
                    EnrollmentStatus::ACTIVO->value,
                    EnrollmentStatus::CONVALIDADO->value,
                    EnrollmentStatus::PREINSCRITO->value,
                ]);
            }])
            ->orderByDesc('start_date');

        $scheduledCourses = $scheduledCoursesQuery->get()->map(function (ScheduledCourse $sc) use ($todayInPeru) {
            $startDateStr = $sc->start_date ? $sc->start_date->format('Y-m-d') : '';
            $endDateStr = $sc->end_date ? $sc->end_date->format('Y-m-d') : '';

            // Cálculo dinámico de estado en zona horaria peruana
            $status = 'en_curso';
            if ($startDateStr > $todayInPeru) {
                $status = 'programado';
            } elseif ($endDateStr < $todayInPeru) {
                $status = 'finalizado';
            }

            // Agrupación y formateo de horarios
            $schedules = $sc->schedules;
            $firstSchedule = $schedules->first();
            $classroom = $firstSchedule?->classroom;

            $daysLabels = $schedules->map(function (CourseSchedule $sched) {
                return $sched->day_of_week->label();
            })->unique()->values()->all();

            $rawDays = $schedules->map(function (CourseSchedule $sched) {
                return $sched->day_of_week->value;
            })->unique()->values()->all();

            $dayOfWeekFormatted = self::formatDaysList($daysLabels);

            $startTime = $firstSchedule ? substr((string) $firstSchedule->start_time, 0, 5) : '18:00';
            $endTime = $firstSchedule ? substr((string) $firstSchedule->end_time, 0, 5) : '20:00';
            $timeRange = $firstSchedule ? "{$startTime} - {$endTime}" : 'Por definir';

            return [
                'id' => $sc->id,
                'course_id' => $sc->course_id,
                'course_name' => $sc->course->name ?? 'Curso No Especificado',
                'career_id' => $sc->course?->module?->careers?->first()?->id,
                'career_name' => $sc->course?->module?->careers?->first()?->name,
                'is_workshop' => (bool) ($sc->course?->is_workshop ?? false),
                'branch_id' => $sc->branch_id,
                'branch_name' => $sc->branch->name ?? 'Sede No Especificada',
                'classroom_id' => $classroom?->id ?? 0,
                'classroom_name' => $classroom?->name ?? 'Ambiente sin asignar',
                'teacher_id' => $sc->teacher_id,
                'teacher_name' => $sc->teacher ? ($sc->teacher->full_name ?? 'Docente Asignado') : 'Docente por asignar',
                'start_date' => $startDateStr,
                'end_date' => $endDateStr,
                'day_of_week' => $dayOfWeekFormatted ?: 'Por definir',
                'days' => $rawDays,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'time_range' => $timeRange,
                'capacity' => $classroom?->capacity ?? 30,
                'enrolled_count' => (int) $sc->active_enrollments_count,
                'status' => $status,
            ];
        })->values()->all();

        // Matrículas reales desde PostgreSQL
        $enrollmentsQuery = Enrollment::query()
            ->whereHas('scheduledCourse', function ($query) use ($allowedBranchIds) {
                $query->whereIn('branch_id', $allowedBranchIds);
            })
            ->with([
                'student.user',
                'scheduledCourse.course.module.careers',
                'scheduledCourse.branch',
                'scheduledCourse.schedules.classroom',
            ])
            ->orderByDesc('enrollment_date')
            ->orderByDesc('id');

        $enrollments = $enrollmentsQuery->get()->map(function (Enrollment $enr) {
            $student = $enr->student;
            $sched = $enr->scheduledCourse;
            $course = $sched?->course;
            $classroom = $sched?->schedules?->first()?->classroom;
            $career = $course?->module?->careers?->first();

            return [
                'id' => $enr->id,
                'scheduled_course_id' => $enr->scheduled_course_id,
                'student_full_name' => $student?->full_name ?? ($student?->user?->first_name.' '.$student?->user?->paternal_surname),
                'student_document' => $student?->document ?? ($student?->user?->document ?? '---'),
                'course_name' => $course?->name ?? 'Curso General',
                'module_name' => $enr->module?->name ?? $course?->module?->name,
                'career_name' => $career?->name,
                'branch_name' => $sched?->branch?->name ?? 'Sede Central',
                'classroom_name' => $classroom?->name ?? 'Aula 101',
                'enrollment_date' => $enr->enrollment_date ? $enr->enrollment_date->format('Y-m-d') : Carbon::now('America/Lima')->toDateString(),
                'status' => $enr->status->value,
                'notes' => null,
            ];
        })->values()->all();

        // Lista completa de Alumnos con sus apoderados e historial de matrículas
        $studentsQuery = Student::query()
            ->with([
                'user',
                'guardian',
                'enrollments' => function ($q) {
                    $q->orderByDesc('enrollment_date')->orderByDesc('id');
                },
                'enrollments.scheduledCourse.course.module.careers',
                'enrollments.scheduledCourse.branch',
                'enrollments.scheduledCourse.schedules.classroom',
            ])
            ->orderByDesc('id');

        $students = $studentsQuery->get()->map(function (Student $student) {
            $u = $student->user;
            $g = $student->guardian;

            $studentEnrollments = $student->enrollments->map(function (Enrollment $enr) {
                $sched = $enr->scheduledCourse;
                $course = $sched?->course;
                $career = $course?->module?->careers?->first();
                $classroom = $sched?->schedules?->first()?->classroom;

                $startDateStr = $sched?->start_date ? $sched->start_date->format('Y-m-d') : '';
                $endDateStr = $sched?->end_date ? $sched->end_date->format('Y-m-d') : '';

                $schedules = $sched?->schedules ?? collect();
                $firstSchedule = $schedules->first();
                $daysLabels = $schedules->map(fn (CourseSchedule $s) => $s->day_of_week->label())->unique()->values()->all();
                $dayOfWeekFormatted = self::formatDaysList($daysLabels);
                $startTime = $firstSchedule ? substr((string) $firstSchedule->start_time, 0, 5) : '18:00';
                $endTime = $firstSchedule ? substr((string) $firstSchedule->end_time, 0, 5) : '20:00';
                $timeRange = $firstSchedule ? "{$startTime} - {$endTime}" : 'Por definir';

                return [
                    'id' => $enr->id,
                    'scheduled_course_id' => $enr->scheduled_course_id,
                    'course_id' => $course?->id,
                    'course_name' => $course?->name ?? 'Curso General',
                    'career_id' => $career?->id,
                    'career_name' => $career?->name,
                    'is_workshop' => (bool) ($course?->is_workshop ?? false),
                    'branch_id' => $sched?->branch_id,
                    'branch_name' => $sched?->branch?->name ?? 'Sede Principal',
                    'classroom_name' => $classroom?->name ?? 'Aula General',
                    'start_date' => $startDateStr,
                    'end_date' => $endDateStr,
                    'day_of_week' => $dayOfWeekFormatted ?: 'Por definir',
                    'time_range' => $timeRange,
                    'enrollment_date' => $enr->enrollment_date ? $enr->enrollment_date->format('Y-m-d') : '',
                    'status' => $enr->status->value,
                    'notes' => $enr->notes ?? null,
                ];
            })->values()->all();

            return [
                'id' => $student->id,
                'user_id' => $student->user_id,
                'document' => $u?->document ?? '',
                'document_type' => $u?->document_type?->value ?? 'DNI',
                'first_name' => $u?->first_name ?? '',
                'paternal_surname' => $u?->paternal_surname ?? '',
                'maternal_surname' => $u?->maternal_surname ?? '',
                'full_name' => $u?->full_name ?? '',
                'birth_date' => $u?->birth_date ? $u->birth_date->format('Y-m-d') : null,
                'phone' => $u?->phone ?? '',
                'email' => $u?->email ?? '',
                'guardian' => $g ? [
                    'id' => $g->id,
                    'document' => $g->document,
                    'first_name' => $g->first_name,
                    'paternal_surname' => $g->paternal_surname,
                    'maternal_surname' => $g->maternal_surname,
                    'full_name' => $g->full_name,
                    'phone' => $g->phone ?? '',
                    'email' => $g->email ?? '',
                ] : null,
                'enrollments' => $studentEnrollments,
            ];
        })->values()->all();

        return [
            'branches' => $branches,
            'classrooms' => $classrooms,
            'careers' => $careers,
            'coursesList' => $coursesList,
            'teachers' => $teachers,
            'scheduledCourses' => $scheduledCourses,
            'enrollments' => $enrollments,
            'students' => $students,
        ];
    }

    /**
     * Muestra la vista principal de Matrículas y Apertura de Cursos.
     */
    public function index(Request $request): Response
    {
        $data = self::getScheduledCourseData($request);
        $data['initialTab'] = $request->query('tab') === 'aperturas' ? 'aperturas' : 'inscripciones';

        return Inertia::render('Admin/Matriculas', $data);
    }

    /**
     * Registra una nueva apertura de curso y aula (ScheduledCourse y CourseSchedule).
     * Autorización: Solo Coordinación de la sede correspondiente o Dirección General (Admin).
     * La Secretaría NO tiene permisos para aperturar cursos/aulas.
     */
    public function store(Request $request): RedirectResponse
    {
        $staff = $request->user()?->staff;
        if (! $staff) {
            abort(403, 'Acceso denegado: no cuenta con perfil administrativo.');
        }

        $branchId = (int) $request->input('branch_id');

        // REGLA: solo Coordinación de la sede o Director General puede crear cursos/aulas
        if (! $staff->isAdmin() && ! $staff->isCoordinador($branchId)) {
            abort(403, 'Solo el personal de Coordinación o Dirección General puede aperturar nuevos cursos y aulas.');
        }

        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'classroom_id' => ['required', 'integer', 'exists:classrooms,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'days' => ['required', 'array', 'size:1'],
            'days.*' => ['required', Rule::enum(DayOfWeek::class)],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
        ], [
            'days.size' => 'Cada curso programado debe tener asignado exactamente 1 día de clase por semana.',
        ]);

        $startTime = str_pad(trim($validated['start_time']), 5, '0');
        if (strlen($startTime) === 5) {
            $startTime .= ':00';
        }
        $endTime = str_pad(trim($validated['end_time']), 5, '0');
        if (strlen($endTime) === 5) {
            $endTime .= ':00';
        }

        if ($startTime >= $endTime) {
            return back()->withErrors([
                'end_time' => 'La hora de finalización debe ser posterior a la hora de inicio.',
            ]);
        }

        // Cálculo seguro de fecha de fin según la duración curricular oficial del curso (evita inyecciones)
        $course = Course::findOrFail($validated['course_id']);
        $durationWeeks = (int) ($course->duration_in_weeks ?: 4);
        $startDate = Carbon::parse($validated['start_date'], 'America/Lima');
        $calculatedEndDate = $startDate->copy()->addWeeks($durationWeeks)->toDateString();

        $scheduledCourse = ScheduledCourse::create([
            'branch_id' => $validated['branch_id'],
            'course_id' => $validated['course_id'],
            'teacher_id' => $validated['teacher_id'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $calculatedEndDate,
        ]);

        foreach ($validated['days'] as $dayVal) {
            CourseSchedule::create([
                'scheduled_course_id' => $scheduledCourse->id,
                'classroom_id' => $validated['classroom_id'],
                'day_of_week' => DayOfWeek::from($dayVal),
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]);
        }

        return back()->with('success', 'Curso aperturado exitosamente en la sede y aula asignada.');
    }

    /**
     * Actualiza una apertura de curso y sus horarios.
     * Autorización: Solo Coordinación de la sede correspondiente o Dirección General (Admin).
     */
    public function update(Request $request, ScheduledCourse $scheduledCourse): RedirectResponse
    {
        $staff = $request->user()?->staff;
        if (! $staff) {
            abort(403, 'Acceso denegado: no cuenta con perfil administrativo.');
        }

        $newBranchId = (int) ($request->input('branch_id') ?: $scheduledCourse->branch_id);

        if (! $staff->isAdmin() && (! $staff->isCoordinador($scheduledCourse->branch_id) || ! $staff->isCoordinador($newBranchId))) {
            abort(403, 'Solo el personal de Coordinación o Dirección General puede modificar cursos aperturados.');
        }

        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'classroom_id' => ['required', 'integer', 'exists:classrooms,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'days' => ['required', 'array', 'size:1'],
            'days.*' => ['required', Rule::enum(DayOfWeek::class)],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
        ], [
            'days.size' => 'Cada curso programado debe tener asignado exactamente 1 día de clase por semana.',
        ]);

        $startTime = str_pad(trim($validated['start_time']), 5, '0');
        if (strlen($startTime) === 5) {
            $startTime .= ':00';
        }
        $endTime = str_pad(trim($validated['end_time']), 5, '0');
        if (strlen($endTime) === 5) {
            $endTime .= ':00';
        }

        if ($startTime >= $endTime) {
            return back()->withErrors([
                'end_time' => 'La hora de finalización debe ser posterior a la hora de inicio.',
            ]);
        }

        // Cálculo seguro de fecha de fin según la duración curricular oficial del curso (evita inyecciones)
        $course = Course::findOrFail($validated['course_id']);
        $durationWeeks = (int) ($course->duration_in_weeks ?: 4);
        $startDate = Carbon::parse($validated['start_date'], 'America/Lima');
        $calculatedEndDate = $startDate->copy()->addWeeks($durationWeeks)->toDateString();

        $scheduledCourse->update([
            'branch_id' => $validated['branch_id'],
            'course_id' => $validated['course_id'],
            'teacher_id' => $validated['teacher_id'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $calculatedEndDate,
        ]);

        // Recrear horarios del curso
        $scheduledCourse->schedules()->forceDelete();

        foreach ($validated['days'] as $dayVal) {
            CourseSchedule::create([
                'scheduled_course_id' => $scheduledCourse->id,
                'classroom_id' => $validated['classroom_id'],
                'day_of_week' => DayOfWeek::from($dayVal),
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]);
        }

        return back()->with('success', 'Apertura de curso y horario actualizados correctamente.');
    }

    /**
     * Elimina una apertura de curso y sus horarios asociados.
     * Restricción: No se puede eliminar si ya cuenta con alumnos matriculados.
     */
    public function destroy(Request $request, ScheduledCourse $scheduledCourse): RedirectResponse
    {
        $staff = $request->user()?->staff;
        if (! $staff) {
            abort(403, 'Acceso denegado: no cuenta con perfil administrativo.');
        }

        if (! $staff->isAdmin() && ! $staff->isCoordinador($scheduledCourse->branch_id)) {
            abort(403, 'Solo el personal de Coordinación o Dirección General puede eliminar cursos aperturados.');
        }

        if ($scheduledCourse->enrollments()->exists()) {
            return back()->withErrors([
                'error' => 'No es posible eliminar el curso aperturado porque ya cuenta con estudiantes matriculados.',
            ]);
        }

        $scheduledCourse->schedules()->delete();
        $scheduledCourse->delete();

        return back()->with('success', 'Apertura de curso eliminada del sistema.');
    }

    /**
     * Registra la matrícula de un estudiante en un curso aperturado.
     * Autorización: La Secretaría de Sede, Coordinador de Sede y Dirección General pueden matricular.
     */
    public function storeEnrollment(Request $request): RedirectResponse
    {
        $staff = $request->user()?->staff;
        if (! $staff) {
            abort(403, 'Acceso denegado: no cuenta con perfil administrativo.');
        }

        $scheduledCourse = ScheduledCourse::with('course')->findOrFail((int) $request->input('scheduled_course_id'));

        // REGLA: Secretaría, Coordinación o Admin pueden matricular en su sede
        if (! $staff->isAdmin() && ! $staff->isCoordinador($scheduledCourse->branch_id) && ! $staff->isSecretaria($scheduledCourse->branch_id)) {
            abort(403, 'No tiene autorización para registrar matrículas en esta sede.');
        }

        $validated = $request->validate([
            'scheduled_course_id' => ['required', 'integer', 'exists:scheduled_courses,id'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'paternal_surname' => ['nullable', 'string', 'max:255'],
            'maternal_surname' => ['nullable', 'string', 'max:255'],
            'student_full_name' => ['nullable', 'string', 'max:255'],
            'student_document' => ['required', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            // Datos del apoderado
            'has_guardian' => ['nullable', 'boolean'],
            'guardian_first_name' => ['nullable', 'string', 'max:100'],
            'guardian_paternal_surname' => ['nullable', 'string', 'max:100'],
            'guardian_maternal_surname' => ['nullable', 'string', 'max:100'],
            'guardian_document' => ['nullable', 'string', 'max:20'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'guardian_email' => ['nullable', 'email', 'max:255'],
            'enrollment_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::enum(EnrollmentStatus::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Buscar estudiante por documento o crear uno nuevo
        $cleanDocument = trim($validated['student_document']);
        $userStudent = User::where('document', $cleanDocument)->first();

        if (! $userStudent) {
            $firstName = trim((string) ($validated['first_name'] ?? ''));
            $paternal = trim((string) ($validated['paternal_surname'] ?? ''));
            $maternal = trim((string) ($validated['maternal_surname'] ?? ''));

            if ($firstName !== '' || $paternal !== '') {
                if ($firstName === '' || $paternal === '') {
                    return back()->withErrors(array_filter([
                        'first_name' => $firstName === '' ? 'El nombre del estudiante es obligatorio.' : null,
                        'paternal_surname' => $paternal === '' ? 'El apellido paterno es obligatorio.' : null,
                    ]));
                }
            } else {
                $fullName = trim((string) ($validated['student_full_name'] ?? ''));
                if ($fullName === '') {
                    return back()->withErrors([
                        'student_document' => 'El estudiante no está registrado. Ingrese los nombres y apellidos para darlo de alta.',
                    ]);
                }

                $parsed = self::parseStudentName($fullName);
                if (! $parsed || empty($parsed['paternal_surname']) || empty($parsed['first_name'])) {
                    return back()->withErrors([
                        'student_full_name' => 'Debe ingresar obligatoriamente al menos un apellido y un nombre del estudiante (ej: "Gómez Morales, Carlos Raúl" o "Carlos Gómez").',
                    ]);
                }

                $firstName = $parsed['first_name'];
                $paternal = $parsed['paternal_surname'];
                $maternal = $parsed['maternal_surname'] ?? '';
            }

            $userStudent = User::create([
                'first_name' => $firstName,
                'paternal_surname' => $paternal,
                'maternal_surname' => $maternal !== '' ? $maternal : null,
                'document' => $cleanDocument,
                'document_type' => DocumentType::DNI,
                'birth_date' => ! empty($validated['birth_date']) ? $validated['birth_date'] : null,
                'phone' => ! empty($validated['phone']) ? trim($validated['phone']) : null,
                'email' => ! empty($validated['email']) ? trim($validated['email']) : null,
                'username' => 'est_'.strtolower($cleanDocument),
                'password' => bcrypt($cleanDocument),
            ]);
        } else {
            $updateData = [];
            if (! empty($validated['birth_date'])) {
                $updateData['birth_date'] = $validated['birth_date'];
            }
            if (! empty($validated['phone'])) {
                $updateData['phone'] = trim($validated['phone']);
            }
            if (! empty($validated['email'])) {
                $updateData['email'] = trim($validated['email']);
            }
            if (! empty($updateData)) {
                $userStudent->update($updateData);
            }
        }

        $student = Student::firstOrCreate([
            'user_id' => $userStudent->id,
        ]);

        // Registrar o actualizar apoderado si se indicó
        $hasGuardian = (bool) ($validated['has_guardian'] ?? false);
        if ($hasGuardian && ! empty($validated['guardian_document'])) {
            $guardianDoc = trim($validated['guardian_document']);
            if ($guardianDoc === $cleanDocument) {
                return back()->withErrors(['guardian_document' => 'El estudiante no puede ser su propio apoderado.']);
            }

            $guardianFirst = trim((string) ($validated['guardian_first_name'] ?? ''));
            $guardianPat = trim((string) ($validated['guardian_paternal_surname'] ?? ''));
            $guardianMat = trim((string) ($validated['guardian_maternal_surname'] ?? ''));

            if ($guardianFirst !== '' && $guardianPat !== '') {
                $guardianUser = User::where('document', $guardianDoc)->first();
                if ($guardianUser) {
                    $guardianUser->update([
                        'first_name' => $guardianFirst ?: $guardianUser->first_name,
                        'paternal_surname' => $guardianPat ?: $guardianUser->paternal_surname,
                        'maternal_surname' => $guardianMat ?: $guardianUser->maternal_surname,
                        'phone' => ! empty($validated['guardian_phone']) ? trim($validated['guardian_phone']) : $guardianUser->phone,
                        'email' => ! empty($validated['guardian_email']) ? trim($validated['guardian_email']) : $guardianUser->email,
                    ]);
                } else {
                    $guardianUser = User::create([
                        'first_name' => $guardianFirst,
                        'paternal_surname' => $guardianPat,
                        'maternal_surname' => $guardianMat ?: null,
                        'document' => $guardianDoc,
                        'document_type' => DocumentType::DNI,
                        'phone' => ! empty($validated['guardian_phone']) ? trim($validated['guardian_phone']) : null,
                        'email' => ! empty($validated['guardian_email']) ? trim($validated['guardian_email']) : null,
                        'username' => 'apod_'.$guardianDoc,
                        'password' => bcrypt($guardianDoc),
                    ]);
                }

                // Guardar al apoderado también como estudiante sin curso en la base de datos
                Student::firstOrCreate([
                    'user_id' => $guardianUser->id,
                ]);

                $student->update(['guardian_id' => $guardianUser->id]);
            }
        }

        // Evitar matrícula duplicada activa en la misma apertura
        $alreadyEnrolled = Enrollment::where('student_id', $student->id)
            ->where('scheduled_course_id', $scheduledCourse->id)
            ->whereNotIn('status', [EnrollmentStatus::RETIRADO->value, EnrollmentStatus::ANULADO->value])
            ->exists();

        if ($alreadyEnrolled) {
            return back()->withErrors([
                'student_document' => "El estudiante {$userStudent->full_name} ya está matriculado en este curso aperturado.",
            ]);
        }

        $enrollmentDate = ! empty($validated['enrollment_date'])
            ? $validated['enrollment_date']
            : Carbon::now('America/Lima')->toDateString();

        $status = ! empty($validated['status'])
            ? EnrollmentStatus::from($validated['status'])
            : EnrollmentStatus::ACTIVO;

        Enrollment::create([
            'scheduled_course_id' => $scheduledCourse->id,
            'student_id' => $student->id,
            'guardian_id' => $student->guardian_id,
            'module_id' => $scheduledCourse->course?->module_id,
            'enrollment_date' => $enrollmentDate,
            'status' => $status,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', "Estudiante {$userStudent->full_name} matriculado exitosamente.");
    }

    /**
     * Búsqueda rápida de alumno por DNI para prellenado y validación en aperturas.
     */
    public function searchStudentByDni(Request $request): JsonResponse
    {
        $document = trim((string) $request->input('document'));
        if (strlen($document) < 6) {
            return response()->json(['found' => false]);
        }

        $user = User::where('document', $document)->first();
        if (! $user) {
            return response()->json(['found' => false]);
        }

        if (! $user->student) {
            Student::firstOrCreate(['user_id' => $user->id]);
            $user->load('student');
        }

        $courseId = $request->input('scheduled_course_id');
        $isEnrolled = false;
        $enrolledCourseIds = [];
        if ($user->student) {
            $enrollments = Enrollment::where('student_id', $user->student->id)
                ->whereNotIn('status', [EnrollmentStatus::RETIRADO->value, EnrollmentStatus::ANULADO->value])
                ->with('scheduledCourse')
                ->get();

            if ($courseId) {
                $isEnrolled = $enrollments->contains(fn ($e) => (int) $e->scheduled_course_id === (int) $courseId);
            }

            $enrolledCourseIds = $enrollments->pluck('scheduledCourse.course_id')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $guardian = $user->student?->guardian;

        return response()->json([
            'found' => true,
            'document' => $user->document,
            'full_name' => $user->full_name,
            'first_name' => $user->first_name,
            'paternal_surname' => $user->paternal_surname,
            'maternal_surname' => $user->maternal_surname,
            'birth_date' => $user->birth_date ? $user->birth_date->format('Y-m-d') : null,
            'phone' => $user->phone ?? '',
            'email' => $user->email ?? '',
            'guardian' => $guardian ? [
                'id' => $guardian->id,
                'document' => $guardian->document,
                'full_name' => $guardian->full_name,
                'first_name' => $guardian->first_name,
                'paternal_surname' => $guardian->paternal_surname,
                'maternal_surname' => $guardian->maternal_surname,
                'phone' => $guardian->phone ?? '',
                'email' => $guardian->email ?? '',
            ] : null,
            'already_enrolled' => $isEnrolled,
            'enrolled_course_ids' => $enrolledCourseIds,
        ]);
    }

    /**
     * Retira / elimina una matrícula de un curso aperturado.
     */
    public function destroyEnrollment(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $staff = $request->user()?->staff;
        if (! $staff) {
            abort(403, 'Acceso denegado: no cuenta con perfil administrativo.');
        }

        $branchId = $enrollment->scheduledCourse?->branch_id;
        if (! $staff->isAdmin() && ! $staff->isCoordinador($branchId) && ! $staff->isSecretaria($branchId)) {
            abort(403, 'No tiene autorización para retirar matrículas en esta sede.');
        }

        $studentName = $enrollment->student?->user?->full_name ?? 'Estudiante';
        $enrollment->delete();

        return back()->with('success', "Matrícula de {$studentName} retirada correctamente.");
    }

    /**
     * Actualiza el estado de una matrícula (activo, convalidado, completado, etc.).
     */
    public function updateEnrollmentStatus(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $staff = $request->user()?->staff;
        if (! $staff) {
            abort(403, 'Acceso denegado: no cuenta con perfil administrativo.');
        }

        $branchId = $enrollment->scheduledCourse?->branch_id;
        if (! $staff->isAdmin() && ! $staff->isCoordinador($branchId) && ! $staff->isSecretaria($branchId)) {
            abort(403, 'No tiene autorización para modificar matrículas en esta sede.');
        }

        $validated = $request->validate([
            'status' => ['required', Rule::enum(EnrollmentStatus::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $enrollment->update([
            'status' => EnrollmentStatus::from($validated['status']),
        ]);

        return back()->with('success', 'Estado de matrícula actualizado.');
    }

    /**
     * Registra un nuevo alumno junto a su apoderado opcional.
     * Accesible desde Secretaría hacia arriba (secre, coord, admin).
     */
    public function storeStudent(Request $request): RedirectResponse
    {
        $staff = $request->user()?->staff;
        if (! $staff) {
            abort(403, 'Acceso denegado: no cuenta con perfil administrativo.');
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'paternal_surname' => ['required', 'string', 'max:100'],
            'maternal_surname' => ['nullable', 'string', 'max:100'],
            'document' => ['required', 'string', 'regex:/^[0-9]{8}$/'],
            'birth_date' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            // Apoderado
            'has_guardian' => ['nullable', 'boolean'],
            'guardian_first_name' => ['nullable', 'string', 'max:100'],
            'guardian_paternal_surname' => ['nullable', 'string', 'max:100'],
            'guardian_maternal_surname' => ['nullable', 'string', 'max:100'],
            'guardian_document' => ['nullable', 'string', 'regex:/^[0-9]{8}$/'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'guardian_email' => ['nullable', 'email', 'max:255'],
        ]);

        $cleanDoc = trim($validated['document']);
        $user = User::where('document', $cleanDoc)->first();

        if ($user) {
            return back()->withErrors(['document' => "Ya existe una persona registrada con el DNI {$cleanDoc} ({$user->full_name})."]);
        }

        $user = User::create([
            'first_name' => trim($validated['first_name']),
            'paternal_surname' => trim($validated['paternal_surname']),
            'maternal_surname' => ! empty($validated['maternal_surname']) ? trim($validated['maternal_surname']) : null,
            'document' => $cleanDoc,
            'document_type' => DocumentType::DNI,
            'birth_date' => ! empty($validated['birth_date']) ? $validated['birth_date'] : null,
            'phone' => ! empty($validated['phone']) ? trim($validated['phone']) : null,
            'email' => ! empty($validated['email']) ? trim($validated['email']) : null,
            'username' => 'est_'.$cleanDoc,
            'password' => bcrypt($cleanDoc),
        ]);

        $guardianId = null;
        $hasGuardian = (bool) ($validated['has_guardian'] ?? false);
        if ($hasGuardian && ! empty($validated['guardian_document'])) {
            $guardianDoc = trim($validated['guardian_document']);
            if ($guardianDoc === $cleanDoc) {
                return back()->withErrors(['guardian_document' => 'El estudiante no puede ser su propio apoderado.']);
            }

            $guardianFirst = trim((string) ($validated['guardian_first_name'] ?? ''));
            $guardianPat = trim((string) ($validated['guardian_paternal_surname'] ?? ''));
            $guardianMat = trim((string) ($validated['guardian_maternal_surname'] ?? ''));

            if ($guardianFirst === '' || $guardianPat === '') {
                return back()->withErrors([
                    'guardian_first_name' => 'El nombre y apellido paterno del apoderado son requeridos cuando se indica que tiene apoderado.',
                ]);
            }

            $guardianUser = User::where('document', $guardianDoc)->first();
            if ($guardianUser) {
                $guardianUser->update([
                    'first_name' => $guardianFirst ?: $guardianUser->first_name,
                    'paternal_surname' => $guardianPat ?: $guardianUser->paternal_surname,
                    'maternal_surname' => $guardianMat ?: $guardianUser->maternal_surname,
                    'phone' => ! empty($validated['guardian_phone']) ? trim($validated['guardian_phone']) : $guardianUser->phone,
                    'email' => ! empty($validated['guardian_email']) ? trim($validated['guardian_email']) : $guardianUser->email,
                ]);
            } else {
                $guardianUser = User::create([
                    'first_name' => $guardianFirst ?: 'Apoderado',
                    'paternal_surname' => $guardianPat ?: 'Registrado',
                    'maternal_surname' => $guardianMat ?: null,
                    'document' => $guardianDoc,
                    'document_type' => DocumentType::DNI,
                    'phone' => ! empty($validated['guardian_phone']) ? trim($validated['guardian_phone']) : null,
                    'email' => ! empty($validated['guardian_email']) ? trim($validated['guardian_email']) : null,
                    'username' => 'apod_'.$guardianDoc,
                    'password' => bcrypt($guardianDoc),
                ]);
            }

            // Guardar al apoderado también como estudiante sin curso en la base de datos
            Student::firstOrCreate([
                'user_id' => $guardianUser->id,
            ]);

            $guardianId = $guardianUser->id;
        }

        Student::create([
            'user_id' => $user->id,
            'guardian_id' => $guardianId,
        ]);

        return back()->with('success', "Alumno {$user->full_name} registrado exitosamente.");
    }

    /**
     * Actualiza la información personal de un alumno y su apoderado.
     * Accesible desde Secretaría hacia arriba (secre, coord, admin).
     */
    public function updateStudent(Request $request, Student $student): RedirectResponse
    {
        $staff = $request->user()?->staff;
        if (! $staff) {
            abort(403, 'Acceso denegado: no cuenta con perfil administrativo.');
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'paternal_surname' => ['required', 'string', 'max:100'],
            'maternal_surname' => ['nullable', 'string', 'max:100'],
            'document' => ['required', 'string', 'regex:/^[0-9]{8}$/'],
            'birth_date' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            // Apoderado
            'has_guardian' => ['nullable', 'boolean'],
            'guardian_first_name' => ['nullable', 'string', 'max:100'],
            'guardian_paternal_surname' => ['nullable', 'string', 'max:100'],
            'guardian_maternal_surname' => ['nullable', 'string', 'max:100'],
            'guardian_document' => ['nullable', 'string', 'regex:/^[0-9]{8}$/'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'guardian_email' => ['nullable', 'email', 'max:255'],
        ]);

        $user = $student->user;
        if (! $user) {
            abort(404, 'Usuario de estudiante no encontrado.');
        }

        // Validar DNI único respecto a otros usuarios
        $existing = User::where('document', $validated['document'])
            ->where('id', '!=', $user->id)
            ->first();
        if ($existing) {
            return back()->withErrors(['document' => 'El DNI ingresado ya está asignado a otro usuario en el sistema.']);
        }

        $user->update([
            'first_name' => trim($validated['first_name']),
            'paternal_surname' => trim($validated['paternal_surname']),
            'maternal_surname' => ! empty($validated['maternal_surname']) ? trim($validated['maternal_surname']) : null,
            'document' => trim($validated['document']),
            'birth_date' => ! empty($validated['birth_date']) ? $validated['birth_date'] : null,
            'phone' => ! empty($validated['phone']) ? trim($validated['phone']) : null,
            'email' => ! empty($validated['email']) ? trim($validated['email']) : null,
        ]);

        $hasGuardian = (bool) ($validated['has_guardian'] ?? false);
        if ($hasGuardian && ! empty($validated['guardian_document'])) {
            $guardianDoc = trim($validated['guardian_document']);
            if ($guardianDoc === trim($validated['document'])) {
                return back()->withErrors(['guardian_document' => 'El estudiante no puede ser su propio apoderado.']);
            }

            $guardianFirst = trim((string) ($validated['guardian_first_name'] ?? ''));
            $guardianPat = trim((string) ($validated['guardian_paternal_surname'] ?? ''));
            $guardianMat = trim((string) ($validated['guardian_maternal_surname'] ?? ''));

            if ($guardianFirst === '' || $guardianPat === '') {
                return back()->withErrors([
                    'guardian_first_name' => 'El nombre y apellido paterno del apoderado son requeridos.',
                ]);
            }

            $guardianUser = User::where('document', $guardianDoc)->first();
            if ($guardianUser) {
                $guardianUser->update([
                    'first_name' => $guardianFirst ?: $guardianUser->first_name,
                    'paternal_surname' => $guardianPat ?: $guardianUser->paternal_surname,
                    'maternal_surname' => $guardianMat ?: $guardianUser->maternal_surname,
                    'phone' => ! empty($validated['guardian_phone']) ? trim($validated['guardian_phone']) : $guardianUser->phone,
                    'email' => ! empty($validated['guardian_email']) ? trim($validated['guardian_email']) : $guardianUser->email,
                ]);
            } else {
                $guardianUser = User::create([
                    'first_name' => $guardianFirst,
                    'paternal_surname' => $guardianPat,
                    'maternal_surname' => $guardianMat ?: null,
                    'document' => $guardianDoc,
                    'document_type' => DocumentType::DNI,
                    'phone' => ! empty($validated['guardian_phone']) ? trim($validated['guardian_phone']) : null,
                    'email' => ! empty($validated['guardian_email']) ? trim($validated['guardian_email']) : null,
                    'username' => 'apod_'.$guardianDoc,
                    'password' => bcrypt($guardianDoc),
                ]);
            }

            // Guardar al apoderado también como estudiante sin curso en la base de datos
            Student::firstOrCreate([
                'user_id' => $guardianUser->id,
            ]);

            $student->update(['guardian_id' => $guardianUser->id]);
        } else {
            $student->update(['guardian_id' => null]);
        }

        return back()->with('success', "Datos del alumno {$user->full_name} actualizados exitosamente.");
    }

    /**
     * Convierte una lista de nombres de días en una cadena legible en español.
     *
     * @param  list<string>  $days
     */
    private static function formatDaysList(array $days): string
    {
        if (empty($days)) {
            return '';
        }

        if (count($days) === 1) {
            return $days[0];
        }

        if (count($days) === 2) {
            return "{$days[0]} y {$days[1]}";
        }

        $lastDay = array_pop($days);

        return implode(', ', $days)." y {$lastDay}";
    }

    /**
     * Parsea un nombre completo (formato peruano con o sin coma) en nombres y apellidos.
     * Exige obligatoriamente al menos un apellido y un nombre.
     * Soporta:
     * - "Apellidos, Nombres" (ej: "Gómez Morales, Carlos Raúl" o "Quispe, Juan")
     * - "Nombres Apellidos" (ej: "Carlos Raúl Gómez Morales", "Rosa Maria Benitez", "Carlos Gómez")
     *
     * @return array{first_name: string, paternal_surname: string, maternal_surname: ?string}|null
     */
    public static function parseStudentName(string $fullName): ?array
    {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return null;
        }

        if (str_contains($fullName, ',')) {
            [$surnamesPart, $namesPart] = array_map('trim', explode(',', $fullName, 2));

            if ($surnamesPart === '' || $namesPart === '') {
                return null;
            }

            $surnameWords = array_values(array_filter(explode(' ', $surnamesPart), fn ($w) => $w !== ''));
            if (empty($surnameWords)) {
                return null;
            }

            $paternal = $surnameWords[0];
            $maternal = count($surnameWords) > 1 ? implode(' ', array_slice($surnameWords, 1)) : null;

            return [
                'first_name' => $namesPart,
                'paternal_surname' => $paternal,
                'maternal_surname' => $maternal,
            ];
        }

        $words = array_values(array_filter(explode(' ', $fullName), fn ($w) => $w !== ''));

        // Se exige al menos 2 palabras (un nombre y al menos un apellido) para evitar datos incompletos o apellidos al azar
        if (count($words) < 2) {
            return null;
        }

        if (count($words) === 2) {
            return [
                'first_name' => $words[0],
                'paternal_surname' => $words[1],
                'maternal_surname' => null,
            ];
        }

        if (count($words) === 3) {
            return [
                'first_name' => $words[0],
                'paternal_surname' => $words[1],
                'maternal_surname' => $words[2],
            ];
        }

        // 4 o más palabras: los dos últimos son apellidos paterno y materno
        $maternal = array_pop($words);
        $paternal = array_pop($words);
        $firstName = implode(' ', $words);

        return [
            'first_name' => $firstName,
            'paternal_surname' => $paternal,
            'maternal_surname' => $maternal,
        ];
    }
}
