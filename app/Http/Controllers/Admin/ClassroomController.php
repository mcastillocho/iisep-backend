<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\CourseSchedule;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ClassroomController extends Controller
{
    /**
     * Devuelve los horarios programados de un aula específica bajo demanda (JSON).
     * Autorización: Dirección General o personal administrativo de la sede del aula.
     */
    public function schedules(Request $request, Classroom $classroom): JsonResponse
    {
        $staff = $request->user()?->staff;

        if (! $staff || (! $staff->isAdmin() && $staff->branch_id !== $classroom->branch_id)) {
            abort(403, 'Acceso denegado. No tienes permisos para consultar los horarios de esta aula.');
        }

        $todayInPeru = Carbon::now('America/Lima')->toDateString();

        $schedules = $classroom->courseSchedules()
            ->with([
                'scheduledCourse:id,course_id,teacher_id,start_date,end_date',
                'scheduledCourse.course:id,name',
                'scheduledCourse.teacher:id,user_id',
                'scheduledCourse.teacher.user:id,first_name,paternal_surname,maternal_surname',
            ])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->map(function (CourseSchedule $schedule) use ($todayInPeru) {
                $sc = $schedule->scheduledCourse;
                $startDate = $sc?->start_date ? $sc->start_date->format('Y-m-d') : null;
                $endDate = $sc?->end_date ? $sc->end_date->format('Y-m-d') : null;

                $status = 'en_curso';
                if ($startDate && $startDate > $todayInPeru) {
                    $status = 'programado';
                } elseif ($endDate && $endDate < $todayInPeru) {
                    $status = 'finalizado';
                }

                return [
                    'id' => $schedule->id,
                    'course_name' => $sc !== null ? $sc->course->name : 'Curso',
                    'teacher_name' => $sc?->teacher !== null ? ($sc->teacher->full_name ?? 'Docente por asignar') : 'Docente por asignar',
                    'day_of_week' => $schedule->day_of_week->label(),
                    'start_time' => substr((string) $schedule->start_time, 0, 5),
                    'end_time' => substr((string) $schedule->end_time, 0, 5),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => $status,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'classroom_id' => $classroom->id,
            'classroom_name' => $classroom->name,
            'schedules' => $schedules,
        ]);
    }

    /**
     * Registra una nueva aula.
     * Solo la Dirección General (branch_id === null) y la Coordinación de Sede ('coord') tienen autorización.
     */
    public function store(Request $request): RedirectResponse
    {
        $staff = $request->user()?->staff;
        $targetBranchId = (int) $request->input('branch_id');

        if (! $this->canCreateClassroom($staff, $targetBranchId)) {
            abort(403, 'Acceso denegado. Solo la Dirección General y la Coordinación de Sede pueden registrar aulas.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
        ], [
            'name.required' => 'El nombre del aula es obligatorio.',
            'branch_id.required' => 'La sede es obligatoria.',
            'capacity.required' => 'La capacidad es obligatoria.',
            'capacity.min' => 'La capacidad mínima es de 1 estudiante.',
        ]);

        // Si es coordinador de sede, forzar su sede asignada
        if ($staff->branch_id !== null) {
            $validated['branch_id'] = $staff->branch_id;
        }

        $classroom = Classroom::create([
            'name' => trim($validated['name']),
            'branch_id' => $validated['branch_id'],
            'capacity' => (int) $validated['capacity'],
        ]);

        return redirect()->back()->with('success', "Aula \"{$classroom->name}\" registrada con éxito.");
    }

    /**
     * Actualiza un aula existente.
     * Dirección y Coordinación pueden editar nombre y aforo.
     * Secretaría de Sede ('secre') solo puede editar la cantidad de espacios/aforo.
     */
    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $this->canEditClassroom($staff, $classroom)) {
            abort(403, 'Acceso denegado. No tienes permisos para modificar esta aula.');
        }

        // Si es secretaría de sede ('secre'), solo puede editar la capacidad/aforo
        if ($this->isOnlyCapacityEditable($staff)) {
            $validated = $request->validate([
                'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            ], [
                'capacity.required' => 'El aforo o capacidad es obligatorio.',
                'capacity.min' => 'El aforo mínimo es de 1 estudiante.',
            ]);

            $classroom->update([
                'capacity' => (int) $validated['capacity'],
            ]);

            return redirect()->back()->with('success', "Capacidad del aula \"{$classroom->name}\" actualizada a {$classroom->capacity} espacios.");
        }

        // Dirección General o Coordinación de Sede
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'branch_id' => ['sometimes', 'integer', Rule::exists('branches', 'id')->whereNull('deleted_at')->where('is_active', true)],
        ], [
            'name.required' => 'El nombre del aula es obligatorio.',
            'capacity.required' => 'La capacidad es obligatoria.',
        ]);

        $updateData = [
            'name' => trim($validated['name']),
            'capacity' => (int) $validated['capacity'],
        ];

        // Solo admin general puede transferir un aula a otra sede
        if ($staff->branch_id === null && isset($validated['branch_id'])) {
            $updateData['branch_id'] = $validated['branch_id'];
        }

        $classroom->update($updateData);

        return redirect()->back()->with('success', "Aula \"{$classroom->name}\" actualizada con éxito.");
    }

    /**
     * Elimina un aula.
     * Solo la Dirección General y la Coordinación de Sede pueden eliminar aulas.
     */
    public function destroy(Request $request, Classroom $classroom): RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $this->canDeleteClassroom($staff, $classroom)) {
            abort(403, 'Acceso denegado. No tienes permisos para eliminar esta aula.');
        }

        if ($classroom->courseSchedules()->exists()) {
            return redirect()->back()->withErrors([
                'classroom' => "No es posible eliminar el aula \"{$classroom->name}\" porque tiene clases u horarios programados.",
            ]);
        }

        $classroomName = $classroom->name;
        $classroom->delete();

        return redirect()->back()->with('success', "Aula \"{$classroomName}\" eliminada con éxito.");
    }

    /**
     * Valida si el personal puede registrar aulas en la sede destino.
     * Solo Director General (isAdmin) y Coordinador de la sede destino (isCoordinador($targetBranchId)).
     */
    protected function canCreateClassroom(?Staff $staff, int $targetBranchId): bool
    {
        return (bool) ($staff && ($staff->isAdmin() || $staff->isCoordinador($targetBranchId)));
    }

    /**
     * Valida si el personal puede modificar el aula.
     * Director General (isAdmin), Coordinador (isCoordinador($branchId)) y Secretaría (isSecretaria($branchId)).
     */
    protected function canEditClassroom(?Staff $staff, Classroom $classroom): bool
    {
        return (bool) ($staff && ($staff->isAdmin() || $staff->isCoordinador($classroom->branch_id) || $staff->isSecretaria($classroom->branch_id)));
    }

    /**
     * Determina si el usuario solo tiene permiso para modificar la capacidad (Secretaría: isSecretaria()).
     */
    protected function isOnlyCapacityEditable(?Staff $staff): bool
    {
        return (bool) $staff?->isSecretaria();
    }

    /**
     * Valida si el personal puede eliminar el aula.
     * Solo Director General y Coordinador de la sede del aula.
     */
    protected function canDeleteClassroom(?Staff $staff, Classroom $classroom): bool
    {
        return (bool) ($staff && ($staff->isAdmin() || $staff->isCoordinador($classroom->branch_id)));
    }
}
