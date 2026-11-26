<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Career;
use App\Models\Module;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CareerController extends Controller
{
    /**
     * Valida si el personal tiene autorización para gestionar carreras técnicas (catálogo institucional).
     * Exclusivo de Dirección General / Administrador General (Nivel 4 — branch_id === null).
     */
    protected function canManageCareers(?Staff $staff): bool
    {
        return (bool) ($staff && $staff->isAdmin());
    }

    /**
     * Devuelve en JSON los módulos activos asignables para una carrera.
     * Un mismo módulo puede compartirse entre varias carreras; solo se excluyen
     * los que ya están vinculados a la carrera indicada en ?career_id.
     */
    public function assignableModules(Request $request): JsonResponse
    {
        $staff = $request->user()?->staff;

        if (! $this->canManageCareers($staff)) {
            abort(403, 'Acceso denegado. Solo la Dirección General puede gestionar carreras técnicas.');
        }

        $validated = $request->validate([
            'career_id' => ['nullable', 'integer', Rule::exists('careers', 'id')->whereNull('deleted_at')],
        ]);

        $query = Module::query()
            ->select(['id', 'name', 'duration_in_weeks'])
            ->orderBy('id');

        if (! empty($validated['career_id'])) {
            $careerId = (int) $validated['career_id'];
            $query->whereDoesntHave('careers', fn ($q) => $q->where('careers.id', $careerId));
        }

        $modules = array_values($query->get()->map(fn (Module $m) => [
            'id' => $m->id,
            'name' => $m->name,
            'duration_in_weeks' => $m->duration_in_weeks ?? 8,
        ])->all());

        return response()->json([
            'modules' => $modules,
        ]);
    }

    /**
     * Registra una nueva carrera técnica en el catálogo.
     */
    public function store(Request $request): RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $this->canManageCareers($staff)) {
            abort(403, 'Acceso denegado. Solo la Dirección General puede crear carreras técnicas.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('careers', 'name')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_in_weeks' => ['nullable', 'integer', 'min:1', 'max:200'],
            'modules' => ['nullable', 'array'],
            'modules.*.id' => ['required', 'integer', 'distinct', Rule::exists('modules', 'id')->whereNull('deleted_at')],
            'modules.*.order' => ['nullable', 'integer', 'min:1'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', Rule::exists('branches', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'available_branch_ids' => ['nullable', 'array'],
            'available_branch_ids.*' => ['integer', Rule::exists('branches', 'id')->whereNull('deleted_at')->where('is_active', true)],
        ], [
            'name.required' => 'El nombre de la carrera técnica es obligatorio.',
            'name.unique' => 'Ya existe una carrera técnica registrada con ese nombre.',
            'modules.*.id.distinct' => 'No se puede asignar el mismo módulo más de una vez en la misma carrera.',
        ]);

        $career = DB::transaction(function () use ($validated): Career {
            $duration = ! empty($validated['duration_in_weeks']) ? (int) $validated['duration_in_weeks'] : null;

            if ($duration === null && ! empty($validated['modules'])) {
                $moduleIds = array_column($validated['modules'], 'id');
                $sumWeeks = (int) Module::query()->whereIn('id', $moduleIds)->sum('duration_in_weeks');
                $duration = $sumWeeks > 0 ? $sumWeeks : null;
            }

            $career = Career::create([
                'name' => trim($validated['name']),
                'description' => ! empty($validated['description']) ? trim($validated['description']) : null,
                'duration_in_weeks' => $duration,
            ]);

            if (! empty($validated['modules'])) {
                $syncData = [];
                foreach ($validated['modules'] as $index => $mod) {
                    $syncData[(int) $mod['id']] = ['order' => (int) ($mod['order'] ?? ($index + 1))];
                }
                $career->modules()->sync($syncData);
            }

            $branchIds = $validated['branch_ids'] ?? $validated['available_branch_ids'] ?? null;
            if ($branchIds !== null) {
                $career->branches()->sync($branchIds);
            }

            return $career;
        });

        return redirect()->back()->with('success', "Carrera \"{$career->name}\" registrada exitosamente.");
    }

    /**
     * Actualiza la información de una carrera técnica, sus módulos y sedes habilitadas.
     */
    public function update(Request $request, Career $career): RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $this->canManageCareers($staff)) {
            abort(403, 'Acceso denegado. Solo la Dirección General puede modificar carreras técnicas.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('careers', 'name')->whereNull('deleted_at')->ignore($career->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_in_weeks' => ['nullable', 'integer', 'min:1', 'max:200'],
            'modules' => ['nullable', 'array'],
            'modules.*.id' => ['required', 'integer', 'distinct', Rule::exists('modules', 'id')->whereNull('deleted_at')],
            'modules.*.order' => ['nullable', 'integer', 'min:1'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', Rule::exists('branches', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'available_branch_ids' => ['nullable', 'array'],
            'available_branch_ids.*' => ['integer', Rule::exists('branches', 'id')->whereNull('deleted_at')->where('is_active', true)],
        ], [
            'name.required' => 'El nombre de la carrera técnica es obligatorio.',
            'name.unique' => 'Ya existe otra carrera técnica registrada con ese nombre.',
            'modules.*.id.distinct' => 'No se puede asignar el mismo módulo más de una vez en la misma carrera.',
        ]);

        DB::transaction(function () use ($career, $validated): void {
            $duration = ! empty($validated['duration_in_weeks']) ? (int) $validated['duration_in_weeks'] : null;

            if ($duration === null && ! empty($validated['modules'])) {
                $moduleIds = array_column($validated['modules'], 'id');
                $sumWeeks = (int) Module::query()->whereIn('id', $moduleIds)->sum('duration_in_weeks');
                $duration = $sumWeeks > 0 ? $sumWeeks : null;
            }

            $career->update([
                'name' => trim($validated['name']),
                'description' => ! empty($validated['description']) ? trim($validated['description']) : null,
                'duration_in_weeks' => $duration,
            ]);

            if (array_key_exists('modules', $validated)) {
                $syncData = [];
                if (! empty($validated['modules'])) {
                    foreach ($validated['modules'] as $index => $mod) {
                        $syncData[(int) $mod['id']] = ['order' => (int) ($mod['order'] ?? ($index + 1))];
                    }
                }
                $career->modules()->sync($syncData);
            }

            if (array_key_exists('branch_ids', $validated) || array_key_exists('available_branch_ids', $validated)) {
                $branchIds = $validated['branch_ids'] ?? $validated['available_branch_ids'] ?? [];
                $career->branches()->sync($branchIds);
            }
        });

        return redirect()->back()->with('success', "Carrera \"{$career->name}\" actualizada exitosamente.");
    }

    /**
     * Elimina una carrera técnica siempre que no tenga alumnos admitidos en ella (student_careers).
     * Los módulos compartidos no se eliminan ni deshabilitan: solo se desvinculan de esta carrera.
     */
    public function destroy(Request $request, Career $career): RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $this->canManageCareers($staff)) {
            abort(403, 'Acceso denegado. Solo la Dirección General puede eliminar carreras técnicas.');
        }

        if ($career->studentCareers()->exists()) {
            return back()->withErrors([
                'error' => 'No se puede eliminar la carrera técnica porque cuenta con alumnos registrados en ella.',
            ]);
        }

        DB::transaction(function () use ($career): void {
            $career->modules()->detach();
            $career->branches()->detach();
            $career->delete();
        });

        return redirect()->back()->with('success', 'Carrera técnica eliminada correctamente.');
    }
}
