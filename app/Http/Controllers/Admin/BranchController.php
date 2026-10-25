<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Classroom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    /**
     * Muestra la vista de administración de Sedes y Aulas con datos reales de la BD.
     */
    public function index(): Response
    {
        $branches = Branch::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'code' => $b->code ?? 'SED-'.$b->id,
                'address' => $b->address ?? 'Dirección por registrar',
                'phone' => $b->phone ?? 'Sin teléfono',
                'is_active' => $b->is_active,
            ]);

        $classrooms = Classroom::query()
            ->with(['branch', 'courseSchedules.scheduledCourse.course'])
            ->orderBy('id')
            ->get()
            ->map(function (Classroom $c) {
                $coursesList = $c->courseSchedules
                    ->map(function ($schedule) {
                        $courseName = $schedule->scheduledCourse?->course?->name ?? 'Curso';
                        $day = $schedule->day_of_week->label();
                        $time = "{$schedule->start_time} - {$schedule->end_time}";

                        return "{$courseName} ({$day} {$time})";
                    })
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'id' => $c->id,
                    'branch_id' => $c->branch_id,
                    'branch_name' => $c->branch?->name ?? 'Sin Sede',
                    'name' => $c->name,
                    'capacity' => $c->capacity,
                    'current_scheduled_courses' => $coursesList,
                ];
            });

        return Inertia::render('Admin/SedesAulas', [
            'branches' => $branches,
            'classrooms' => $classrooms,
        ]);
    }

    /**
     * Registra una nueva sede en el sistema.
     * Solo la Dirección General / Administrador General cuenta con autorización para crear sedes.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Doble validación en backend: confirmar que quien solicita la creación es Admin (no tiene sede asignada)
        if (! $user || ! $user->staff || ! $user->staff->isAdmin()) {
            abort(403, 'Acceso denegado. Solo el Administrador General cuenta con permisos para crear nuevas sedes.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:branches,name'],
            'code' => ['nullable', 'string', 'max:50', 'unique:branches,code'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ], [
            'name.required' => 'El nombre de la sede es obligatorio.',
            'name.unique' => 'Ya existe una sede registrada con ese nombre.',
            'code.unique' => 'El código de sede ingresado ya se encuentra en uso.',
        ]);

        // Autogenerar código si no fue provisto
        if (empty($validated['code'])) {
            $lettersOnly = preg_replace('/[^A-Za-z]/', '', $validated['name']) ?? 'SED';
            $validated['code'] = strtoupper(substr($lettersOnly, 0, 3));

            // Asegurar unicidad básica en autogeneración
            if (Branch::where('code', $validated['code'])->exists()) {
                $validated['code'] .= '-'.rand(10, 99);
            }
        } else {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        $branch = Branch::create([
            'name' => trim($validated['name']),
            'code' => $validated['code'],
            'address' => ! empty($validated['address']) ? trim($validated['address']) : null,
            'phone' => ! empty($validated['phone']) ? trim($validated['phone']) : null,
            'is_active' => true,
        ]);

        return redirect()->route('admin.sedes-aulas')->with('success', "Sede \"{$branch->name}\" registrada exitosamente.");
    }
}
