<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkshopController extends Controller
{
    /**
     * Valida si el personal tiene autorización para modificar talleres.
     * Solo Coordinador de Sede ('coord') para arriba (Coordinador y Director General / Admin).
     */
    protected function canManageWorkshops(?Staff $staff): bool
    {
        return (bool) ($staff && ($staff->isAdmin() || $staff->isCoordinador()));
    }

    /**
     * Registra un nuevo taller práctico.
     */
    public function store(Request $request): RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $this->canManageWorkshops($staff)) {
            abort(403, 'Acceso denegado. Solo el personal con cargo de Coordinación de Sede o superior puede crear talleres.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_in_weeks' => ['required', 'integer', 'min:1', 'max:52'],
            'module_id' => ['nullable', 'integer', 'exists:modules,id'],
            'is_workshop' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'El nombre del curso o taller es obligatorio.',
            'duration_in_weeks.required' => 'La duración en semanas es obligatoria.',
            'duration_in_weeks.min' => 'La duración mínima es de 1 semana.',
        ]);

        $isWorkshop = $request->has('is_workshop')
            ? (bool) $validated['is_workshop']
            : empty($validated['module_id']);

        $workshop = Course::create([
            'name' => trim($validated['name']),
            'description' => ! empty($validated['description']) ? trim($validated['description']) : null,
            'duration_in_weeks' => (int) $validated['duration_in_weeks'],
            'is_workshop' => $isWorkshop,
            'module_id' => ! empty($validated['module_id']) ? (int) $validated['module_id'] : null,
        ]);

        $label = $isWorkshop ? 'Taller' : 'Curso';

        return redirect()->back()->with('success', "{$label} \"{$workshop->name}\" registrado exitosamente.");
    }

    /**
     * Actualiza la información de un taller existente.
     */
    public function update(Request $request, Course $course): RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $this->canManageWorkshops($staff)) {
            abort(403, 'Acceso denegado. Solo el personal con cargo de Coordinación de Sede o superior puede modificar talleres.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_in_weeks' => ['required', 'integer', 'min:1', 'max:52'],
            'module_id' => ['nullable', 'integer', 'exists:modules,id'],
            'is_workshop' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'El nombre del curso o taller es obligatorio.',
            'duration_in_weeks.required' => 'La duración en semanas es obligatoria.',
            'duration_in_weeks.min' => 'La duración mínima es de 1 semana.',
        ]);

        $updateData = [
            'name' => trim($validated['name']),
            'description' => ! empty($validated['description']) ? trim($validated['description']) : null,
            'duration_in_weeks' => (int) $validated['duration_in_weeks'],
        ];

        if ($request->has('module_id')) {
            $updateData['module_id'] = ! empty($validated['module_id']) ? (int) $validated['module_id'] : null;
        }

        if ($request->has('is_workshop')) {
            $updateData['is_workshop'] = (bool) $validated['is_workshop'];
        }

        $course->update($updateData);

        return redirect()->back()->with('success', "Taller \"{$course->name}\" actualizado exitosamente.");
    }

    /**
     * Elimina un taller (modo restrictivo si tiene clases o programaciones asociadas).
     */
    public function destroy(Request $request, Course $course): RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $this->canManageWorkshops($staff)) {
            abort(403, 'Acceso denegado. Solo el personal con cargo de Coordinación de Sede o superior puede eliminar talleres.');
        }

        if ($course->scheduledCourses()->exists()) {
            return redirect()->back()->withErrors([
                'workshop' => "No es posible eliminar el taller \"{$course->name}\" porque tiene aperturas o clases programadas.",
            ]);
        }

        $workshopName = $course->name;
        $course->delete();

        return redirect()->back()->with('success', "Taller \"{$workshopName}\" eliminado exitosamente.");
    }
}
