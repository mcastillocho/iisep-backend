<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    /**
     * Valida si el personal tiene autorización para modificar módulos formativos.
     * Solo Coordinador de Sede ('coord') para arriba (Coordinador y Director General / Admin).
     */
    protected function canManageModules(?Staff $staff): bool
    {
        return (bool) ($staff && ($staff->isAdmin() || $staff->isCoordinador()));
    }

    /**
     * Registra un nuevo módulo formativo.
     */
    public function store(Request $request): RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $this->canManageModules($staff)) {
            abort(403, 'Acceso denegado. Solo el personal con cargo de Coordinación de Sede o superior puede crear módulos.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_in_weeks' => ['required', 'integer', 'min:1', 'max:52'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['required', 'integer', 'exists:courses,id'],
        ], [
            'name.required' => 'El nombre del módulo formativo es obligatorio.',
            'duration_in_weeks.required' => 'La duración en semanas es obligatoria.',
            'duration_in_weeks.min' => 'La duración mínima es de 1 semana.',
        ]);

        $module = Module::create([
            'name' => trim($validated['name']),
            'description' => ! empty($validated['description']) ? trim($validated['description']) : null,
            'duration_in_weeks' => (int) $validated['duration_in_weeks'],
        ]);

        if (! empty($validated['course_ids'])) {
            Course::whereIn('id', $validated['course_ids'])->update(['module_id' => $module->id]);
        }

        return redirect()->back()->with('success', "Módulo \"{$module->name}\" registrado exitosamente.");
    }

    /**
     * Actualiza la información de un módulo formativo existente.
     */
    public function update(Request $request, Module $module): RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $this->canManageModules($staff)) {
            abort(403, 'Acceso denegado. Solo el personal con cargo de Coordinación de Sede o superior puede modificar módulos.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_in_weeks' => ['required', 'integer', 'min:1', 'max:52'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['required', 'integer', 'exists:courses,id'],
        ], [
            'name.required' => 'El nombre del módulo formativo es obligatorio.',
            'duration_in_weeks.required' => 'La duración en semanas es obligatoria.',
            'duration_in_weeks.min' => 'La duración mínima es de 1 semana.',
        ]);

        $module->update([
            'name' => trim($validated['name']),
            'description' => ! empty($validated['description']) ? trim($validated['description']) : null,
            'duration_in_weeks' => (int) $validated['duration_in_weeks'],
        ]);

        if (array_key_exists('course_ids', $validated)) {
            $courseIds = $validated['course_ids'] ?? [];
            Course::where('module_id', $module->id)
                ->whereNotIn('id', $courseIds)
                ->update(['module_id' => null]);

            if (! empty($courseIds)) {
                Course::whereIn('id', $courseIds)->update(['module_id' => $module->id]);
            }
        }

        return redirect()->back()->with('success', "Módulo \"{$module->name}\" actualizado correctamente.");
    }

    /**
     * Elimina un módulo formativo siempre que no tenga cursos, carreras o matrículas asociadas.
     */
    public function destroy(Request $request, Module $module): RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $this->canManageModules($staff)) {
            abort(403, 'Acceso denegado. Solo el personal con cargo de Coordinación de Sede o superior puede eliminar módulos.');
        }

        if ($module->courses()->exists() || $module->careers()->exists() || $module->enrollments()->exists()) {
            return back()->withErrors([
                'error' => 'No se puede eliminar el módulo formativo porque tiene cursos, carreras o matrículas asociadas.',
            ]);
        }

        $module->delete();

        return redirect()->back()->with('success', 'Módulo formativo eliminado correctamente.');
    }
}
