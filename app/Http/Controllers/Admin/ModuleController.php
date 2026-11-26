<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ModuleController extends Controller
{
    /**
     * Valida si el personal tiene autorización para gestionar módulos formativos (catálogo institucional).
     * Exclusivo de Dirección General / Administrador General (Nivel 4 — branch_id === null).
     */
    protected function canManageModules(?Staff $staff): bool
    {
        return (bool) ($staff && $staff->isAdmin());
    }

    /**
     * Devuelve en JSON únicamente los cursos asignables a un módulo:
     * excluye talleres libres (is_workshop = true) y cursos que ya pertenecen a otro módulo.
     * Si se envía ?module_id, incluye además los cursos que ya pertenecen a ese módulo.
     */
    public function assignableCourses(Request $request): JsonResponse
    {
        $staff = $request->user()?->staff;

        if (! $this->canManageModules($staff)) {
            abort(403, 'Acceso denegado. Solo la Dirección General puede gestionar módulos formativos.');
        }

        $validated = $request->validate([
            'module_id' => ['nullable', 'integer', Rule::exists('modules', 'id')->whereNull('deleted_at')],
        ]);

        $query = Course::query()
            ->select(['id', 'name', 'duration_in_weeks', 'module_id'])
            ->where('is_workshop', false)
            ->orderBy('id');

        if (! empty($validated['module_id'])) {
            $moduleId = (int) $validated['module_id'];
            $query->where(fn ($q) => $q->whereNull('module_id')->orWhere('module_id', $moduleId));
        } else {
            $query->whereNull('module_id');
        }

        $courses = array_values($query->get()->map(fn (Course $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'duration_in_weeks' => $c->duration_in_weeks ?? 4,
            'module_id' => $c->module_id,
        ])->all());

        return response()->json([
            'courses' => $courses,
        ]);
    }

    /**
     * Registra un nuevo módulo formativo.
     */
    public function store(Request $request): RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $this->canManageModules($staff)) {
            abort(403, 'Acceso denegado. Solo la Dirección General puede crear módulos.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('modules', 'name')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_in_weeks' => ['required', 'integer', 'min:1', 'max:52'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['required', 'integer', 'distinct', Rule::exists('courses', 'id')->whereNull('deleted_at')],
        ], [
            'name.required' => 'El nombre del módulo formativo es obligatorio.',
            'name.unique' => 'Ya existe un módulo formativo registrado con ese nombre.',
            'duration_in_weeks.required' => 'La duración en semanas es obligatoria.',
            'duration_in_weeks.min' => 'La duración mínima es de 1 semana.',
        ]);

        $courseIds = array_values(array_map('intval', $validated['course_ids'] ?? []));

        if ($error = $this->validateAssignableCourseIds($courseIds, null)) {
            return redirect()->back()->withErrors(['course_ids' => $error]);
        }

        $module = DB::transaction(function () use ($validated, $courseIds): Module {
            $module = Module::create([
                'name' => trim($validated['name']),
                'description' => ! empty($validated['description']) ? trim($validated['description']) : null,
                'duration_in_weeks' => (int) $validated['duration_in_weeks'],
            ]);

            if (! empty($courseIds)) {
                Course::whereIn('id', $courseIds)->update(['module_id' => $module->id]);
            }

            return $module;
        });

        return redirect()->back()->with('success', "Módulo \"{$module->name}\" registrado exitosamente.");
    }

    /**
     * Actualiza la información de un módulo formativo existente.
     */
    public function update(Request $request, Module $module): RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $this->canManageModules($staff)) {
            abort(403, 'Acceso denegado. Solo la Dirección General puede modificar módulos.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('modules', 'name')->whereNull('deleted_at')->ignore($module->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_in_weeks' => ['required', 'integer', 'min:1', 'max:52'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['required', 'integer', 'distinct', Rule::exists('courses', 'id')->whereNull('deleted_at')],
        ], [
            'name.required' => 'El nombre del módulo formativo es obligatorio.',
            'name.unique' => 'Ya existe otro módulo formativo registrado con ese nombre.',
            'duration_in_weeks.required' => 'La duración en semanas es obligatoria.',
            'duration_in_weeks.min' => 'La duración mínima es de 1 semana.',
        ]);

        $hasCourseIds = array_key_exists('course_ids', $validated);
        $courseIds = $hasCourseIds ? array_values(array_map('intval', $validated['course_ids'] ?? [])) : [];

        if ($hasCourseIds && ($error = $this->validateAssignableCourseIds($courseIds, $module->id))) {
            return redirect()->back()->withErrors(['course_ids' => $error]);
        }

        DB::transaction(function () use ($module, $validated, $hasCourseIds, $courseIds): void {
            $module->update([
                'name' => trim($validated['name']),
                'description' => ! empty($validated['description']) ? trim($validated['description']) : null,
                'duration_in_weeks' => (int) $validated['duration_in_weeks'],
            ]);

            if ($hasCourseIds) {
                Course::where('module_id', $module->id)
                    ->whereNotIn('id', $courseIds)
                    ->update(['module_id' => null]);

                if (! empty($courseIds)) {
                    Course::whereIn('id', $courseIds)->update(['module_id' => $module->id]);
                }
            }
        });

        return redirect()->back()->with('success', "Módulo \"{$module->name}\" actualizado correctamente.");
    }

    /**
     * Elimina un módulo formativo siempre que no tenga cursos, carreras o matrículas asociadas.
     */
    public function destroy(Request $request, Module $module): RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $this->canManageModules($staff)) {
            abort(403, 'Acceso denegado. Solo la Dirección General puede eliminar módulos.');
        }

        if ($module->courses()->exists() || $module->careers()->exists() || $module->enrollments()->exists()) {
            return back()->withErrors([
                'error' => 'No se puede eliminar el módulo formativo porque tiene cursos, carreras o matrículas asociadas.',
            ]);
        }

        $module->delete();

        return redirect()->back()->with('success', 'Módulo formativo eliminado correctamente.');
    }

    /**
     * Verifica que ninguno de los cursos seleccionados sea un taller libre ni pertenezca a otro módulo.
     *
     * @param  list<int>  $courseIds
     */
    protected function validateAssignableCourseIds(array $courseIds, ?int $currentModuleId): ?string
    {
        if (empty($courseIds)) {
            return null;
        }

        $hasWorkshops = Course::query()
            ->whereIn('id', $courseIds)
            ->where('is_workshop', true)
            ->exists();

        if ($hasWorkshops) {
            return 'No es posible asignar talleres libres dentro de un módulo formativo.';
        }

        $assignedToOtherQuery = Course::query()
            ->whereIn('id', $courseIds)
            ->whereNotNull('module_id');

        if ($currentModuleId !== null) {
            $assignedToOtherQuery->where('module_id', '!=', $currentModuleId);
        }

        if ($assignedToOtherQuery->exists()) {
            return 'Uno o más cursos seleccionados ya pertenecen a otro módulo formativo y no pueden pertenecer a dos módulos a la vez.';
        }

        return null;
    }
}
