<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\DayOfWeek;
use App\Enums\DocumentType;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
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

        // Catálogo de cursos disponibles para aperturar
        $coursesList = Course::query()
            ->with('module')
            ->orderBy('name')
            ->get()
            ->map(fn (Course $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'duration_in_weeks' => $c->duration_in_weeks ?? 4,
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

        return [
            'branches' => $branches,
            'classrooms' => $classrooms,
            'coursesList' => $coursesList,
            'teachers' => $teachers,
            'scheduledCourses' => $scheduledCourses,
            'enrollments' => $enrollments,
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
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['required', Rule::enum(DayOfWeek::class)],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
        ], [
            'days.min' => 'Debe seleccionar al menos un día de la semana para el horario de clases.',
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
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['required', Rule::enum(DayOfWeek::class)],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
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
            'student_full_name' => ['nullable', 'string', 'max:255'],
            'student_document' => ['required', 'string', 'max:20'],
            'enrollment_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::enum(EnrollmentStatus::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Buscar estudiante por documento o crear uno nuevo
        $cleanDocument = trim($validated['student_document']);
        $userStudent = User::where('document', $cleanDocument)->first();

        if (! $userStudent) {
            $fullName = trim((string) ($validated['student_full_name'] ?? ''));
            if ($fullName === '') {
                return back()->withErrors([
                    'student_document' => 'El estudiante no está registrado. Ingrese los nombres y apellidos para darlo de alta.',
                ]);
            }

            $nameParts = explode(' ', $fullName);
            $firstName = $nameParts[0] ?? 'Estudiante';
            $paternal = $nameParts[1] ?? 'IISEP';
            $maternal = $nameParts[2] ?? null;

            $userStudent = User::create([
                'first_name' => $firstName,
                'paternal_surname' => $paternal,
                'maternal_surname' => $maternal,
                'document' => $cleanDocument,
                'document_type' => DocumentType::DNI,
                'username' => 'est_'.strtolower($cleanDocument),
                'password' => bcrypt($cleanDocument),
            ]);
        }

        $student = Student::firstOrCreate([
            'user_id' => $userStudent->id,
        ]);

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

        $courseId = $request->input('scheduled_course_id');
        $isEnrolled = false;
        if ($courseId && $user->student) {
            $isEnrolled = Enrollment::where('student_id', $user->student->id)
                ->where('scheduled_course_id', (int) $courseId)
                ->whereNotIn('status', [EnrollmentStatus::RETIRADO->value, EnrollmentStatus::ANULADO->value])
                ->exists();
        }

        return response()->json([
            'found' => true,
            'document' => $user->document,
            'full_name' => $user->full_name,
            'already_enrolled' => $isEnrolled,
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
}
