<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Career;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CareerController extends Controller
{
    /**
     * Valida si el personal tiene autorización para modificar carreras técnicas.
     * Solo Coordinador de Sede ('coord') para arriba (Coordinador y Director General / Admin).
     */
    protected function canManageCareers(?Staff $staff): bool
    {
        return (bool) ($staff && ($staff->isAdmin() || $staff->isCoordinador()));
    }

    /**
     * Registra una nueva carrera técnica en el catálogo.
     */
    public function store(Request $request): RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $this->canManageCareers($staff)) {
            abort(403, 'Acceso denegado. Solo el personal con cargo de Coordinación de Sede o superior puede crear carreras técnicas.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_in_weeks' => ['nullable', 'integer', 'min:1', 'max:200'],
            'modules' => ['nullable', 'array'],
            'modules.*.id' => ['required', 'integer', 'exists:modules,id'],
            'modules.*.order' => ['nullable', 'integer'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'available_branch_ids' => ['nullable', 'array'],
            'available_branch_ids.*' => ['integer', 'exists:branches,id'],
        ], [
            'name.required' => 'El nombre de la carrera técnica es obligatorio.',
        ]);

        $career = Career::create([
            'name' => trim($validated['name']),
            'description' => ! empty($validated['description']) ? trim($validated['description']) : null,
            'duration_in_weeks' => ! empty($validated['duration_in_weeks']) ? (int) $validated['duration_in_weeks'] : null,
        ]);

        if (! empty($validated['modules'])) {
            $syncData = [];
            foreach ($validated['modules'] as $index => $mod) {
                $syncData[$mod['id']] = ['order' => $mod['order'] ?? ($index + 1)];
            }
            $career->modules()->sync($syncData);
        }

        $branchIds = $validated['branch_ids'] ?? $validated['available_branch_ids'] ?? null;
        if ($branchIds !== null) {
            $career->branches()->sync($branchIds);
        }

        return redirect()->back()->with('success', "Carrera \"{$career->name}\" registrada exitosamente.");
    }

    /**
     * Actualiza la información de una carrera técnica y sus módulos formativos asignados.
     */
    public function update(Request $request, Career $career): RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $this->canManageCareers($staff)) {
            abort(403, 'Acceso denegado. Solo el personal con cargo de Coordinación de Sede o superior puede modificar carreras técnicas.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_in_weeks' => ['nullable', 'integer', 'min:1', 'max:200'],
            'modules' => ['nullable', 'array'],
            'modules.*.id' => ['required', 'integer', 'exists:modules,id'],
            'modules.*.order' => ['nullable', 'integer'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'available_branch_ids' => ['nullable', 'array'],
            'available_branch_ids.*' => ['integer', 'exists:branches,id'],
        ], [
            'name.required' => 'El nombre de la carrera técnica es obligatorio.',
        ]);

        $career->update([
            'name' => trim($validated['name']),
            'description' => ! empty($validated['description']) ? trim($validated['description']) : null,
            'duration_in_weeks' => ! empty($validated['duration_in_weeks']) ? (int) $validated['duration_in_weeks'] : null,
        ]);

        if (array_key_exists('modules', $validated)) {
            $syncData = [];
            if (! empty($validated['modules'])) {
                foreach ($validated['modules'] as $index => $mod) {
                    $syncData[$mod['id']] = ['order' => $mod['order'] ?? ($index + 1)];
                }
            }
            $career->modules()->sync($syncData);
        }

        if (array_key_exists('branch_ids', $validated) || array_key_exists('available_branch_ids', $validated)) {
            $branchIds = $validated['branch_ids'] ?? $validated['available_branch_ids'] ?? [];
            $career->branches()->sync($branchIds);
        }

        return redirect()->back()->with('success', "Carrera \"{$career->name}\" actualizada exitosamente.");
    }

    /**
     * Elimina una carrera técnica siempre que no tenga estudiantes matriculados o dependencias activas.
     */
    public function destroy(Request $request, Career $career): RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $this->canManageCareers($staff)) {
            abort(403, 'Acceso denegado. Solo el personal con cargo de Coordinación de Sede o superior puede eliminar carreras técnicas.');
        }

        if ($career->studentCareers()->exists() || $career->students()->exists()) {
            return back()->withErrors([
                'error' => 'No se puede eliminar la carrera técnica porque cuenta con estudiantes matriculados o dependencias activas.',
            ]);
        }

        $career->delete();

        return redirect()->back()->with('success', 'Carrera técnica eliminada correctamente.');
    }
}
