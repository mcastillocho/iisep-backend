<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class WorkshopController extends Controller
{
    /**
     * Valida si el personal tiene autorización para gestionar cursos y talleres (catálogo institucional).
     * Exclusivo de Dirección General / Administrador General (Nivel 4 — branch_id === null).
     */
    protected function canManageWorkshops(?Staff $staff): bool
    {
        return (bool) ($staff && $staff->isAdmin());
    }

    /**
     * Registra un nuevo curso modular o taller libre.
     * Si is_workshop es true, siempre se guarda desvinculado de cualquier módulo (module_id = null).
     */
    public function store(Request $request): RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $this->canManageWorkshops($staff)) {
            abort(403, 'Acceso denegado. Solo la Dirección General puede crear cursos o talleres.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('courses', 'name')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_in_weeks' => ['required', 'integer', 'min:1', 'max:52'],
            'module_id' => ['nullable', 'integer', Rule::exists('modules', 'id')->whereNull('deleted_at')],
            'is_workshop' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'El nombre del curso o taller es obligatorio.',
            'name.unique' => 'Ya existe un curso o taller registrado con ese nombre.',
            'duration_in_weeks.required' => 'La duración en semanas es obligatoria.',
            'duration_in_weeks.min' => 'La duración mínima es de 1 semana.',
        ]);

        $requestedModuleId = ! empty($validated['module_id']) ? (int) $validated['module_id'] : null;
        $isWorkshop = $request->has('is_workshop')
            ? (bool) $validated['is_workshop']
            : ($requestedModuleId === null);

        // Si es taller práctico libre, se desvincula automáticamente de cualquier módulo formativo
        $moduleId = $isWorkshop ? null : $requestedModuleId;

        $course = Course::create([
            'name' => trim($validated['name']),
            'description' => ! empty($validated['description']) ? trim($validated['description']) : null,
            'duration_in_weeks' => (int) $validated['duration_in_weeks'],
            'is_workshop' => $isWorkshop,
            'module_id' => $moduleId,
        ]);

        $label = $isWorkshop ? 'Taller' : 'Curso';

        return redirect()->back()->with('success', "{$label} \"{$course->name}\" registrado exitosamente.");
    }

    /**
     * Actualiza la información de un curso o taller existente.
     * Permite cambiar directamente de módulo (desvinculando del anterior) y, si se marca como taller práctico
     * (is_workshop = true), desvincula automáticamente module_id = null.
     * Bloquea cambios de módulo o tipo (curso ↔ taller) si tiene aperturas vigentes o matrículas en curso.
     */
    public function update(Request $request, Course $course): RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $this->canManageWorkshops($staff)) {
            abort(403, 'Acceso denegado. Solo la Dirección General puede modificar cursos o talleres.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('courses', 'name')->whereNull('deleted_at')->ignore($course->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_in_weeks' => ['required', 'integer', 'min:1', 'max:52'],
            'module_id' => ['nullable', 'integer', Rule::exists('modules', 'id')->whereNull('deleted_at')],
            'is_workshop' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'El nombre del curso o taller es obligatorio.',
            'name.unique' => 'Ya existe otro curso o taller registrado con ese nombre.',
            'duration_in_weeks.required' => 'La duración en semanas es obligatoria.',
            'duration_in_weeks.min' => 'La duración mínima es de 1 semana.',
        ]);

        $finalIsWorkshop = $request->has('is_workshop')
            ? (bool) $validated['is_workshop']
            : (bool) $course->is_workshop;

        $finalModuleId = $finalIsWorkshop
            ? null
            : ($request->has('module_id')
                ? (! empty($validated['module_id']) ? (int) $validated['module_id'] : null)
                : $course->module_id);

        $structureChanged = ($finalModuleId !== $course->module_id) || ($finalIsWorkshop !== (bool) $course->is_workshop);

        if ($structureChanged && $this->hasActiveAcademicActivity($course)) {
            return redirect()->back()->withErrors([
                'module_id' => "No es posible cambiar el módulo o el tipo de \"{$course->name}\" porque tiene cursos aperturados vigentes o matrículas en curso.",
            ]);
        }

        $course->update([
            'name' => trim($validated['name']),
            'description' => ! empty($validated['description']) ? trim($validated['description']) : null,
            'duration_in_weeks' => (int) $validated['duration_in_weeks'],
            'module_id' => $finalModuleId,
            'is_workshop' => $finalIsWorkshop,
        ]);

        $label = $finalIsWorkshop ? 'Taller' : 'Curso';

        return redirect()->back()->with('success', "{$label} \"{$course->name}\" actualizado exitosamente.");
    }

    /**
     * Elimina un curso o taller (bloquea si tiene aperturas o clases programadas).
     */
    public function destroy(Request $request, Course $course): RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $this->canManageWorkshops($staff)) {
            abort(403, 'Acceso denegado. Solo la Dirección General puede eliminar cursos o talleres.');
        }

        if ($course->scheduledCourses()->exists()) {
            $label = $course->is_workshop ? 'taller' : 'curso';

            return redirect()->back()->withErrors([
                'workshop' => "No es posible eliminar el {$label} \"{$course->name}\" porque tiene aperturas o clases programadas.",
            ]);
        }

        $label = $course->is_workshop ? 'Taller' : 'Curso';
        $courseName = $course->name;
        $course->delete();

        return redirect()->back()->with('success', "{$label} \"{$courseName}\" eliminado exitosamente.");
    }

    /**
     * Determina si el curso tiene aperturas vigentes/programadas o matrículas activas/preinscritas.
     */
    protected function hasActiveAcademicActivity(Course $course): bool
    {
        $todayInPeru = Carbon::now('America/Lima')->toDateString();

        $hasActiveScheduledCourses = $course->scheduledCourses()
            ->whereDate('end_date', '>=', $todayInPeru)
            ->exists();

        if ($hasActiveScheduledCourses) {
            return true;
        }

        return Enrollment::query()
            ->whereIn('status', [
                EnrollmentStatus::ACTIVO->value,
                EnrollmentStatus::PREINSCRITO->value,
            ])
            ->whereHas('scheduledCourse', fn ($q) => $q->withTrashed()->where('course_id', $course->id))
            ->exists();
    }
}
