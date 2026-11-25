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
     * Obtiene el resumen liviano de sedes y conteo de horarios por aula para el Dashboard.
     *
     * @return array{branches: list<array<string, mixed>>, classrooms: list<array<string, mixed>>}
     */
    protected function getDashboardBranchData(Request $request): array
    {
        $staff = $request->user()?->staff;

        $branchQuery = Branch::query()
            ->select(['id', 'name', 'code', 'address', 'phone'])
            ->with([
                'classrooms' => fn ($q) => $q->select(['id', 'branch_id', 'name', 'capacity'])->orderBy('id'),
            ])
            ->withCount('classrooms')
            ->orderBy('id');

        $classroomQuery = Classroom::query()
            ->select(['id', 'branch_id', 'name', 'capacity'])
            ->with(['branch:id,name'])
            ->withCount('courseSchedules')
            ->orderBy('id');

        if ($staff && $staff->branch_id !== null) {
            $branchQuery->where('id', $staff->branch_id);
            $classroomQuery->where('branch_id', $staff->branch_id);
        }

        $branches = $branchQuery->get()->map(fn (Branch $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'code' => $b->code ?? 'SED-'.$b->id,
            'address' => $b->address ?? '',
            'phone' => $b->phone ?? '',
            'classrooms_count' => (int) $b->classrooms_count,
            'total_capacity' => (int) $b->classrooms->sum('capacity'),
            'classrooms' => $b->classrooms->map(fn (Classroom $c) => [
                'id' => $c->id,
                'branch_id' => $c->branch_id,
                'name' => $c->name,
                'capacity' => $c->capacity,
            ])->values()->all(),
        ])->values()->all();

        $classrooms = $classroomQuery->get()->map(fn (Classroom $c) => [
            'id' => $c->id,
            'branch_id' => $c->branch_id,
            'branch_name' => $c->branch?->name ?? 'Sin Sede',
            'name' => $c->name,
            'capacity' => $c->capacity,
            'schedules_count' => (int) $c->course_schedules_count,
        ])->values()->all();

        return [
            'branches' => $branches,
            'classrooms' => $classrooms,
        ];
    }

    /**
     * Muestra el resumen liviano de todas las sedes (/admin/sedes).
     * Si el personal pertenece a una sede específica, redirige directamente a su sede (/admin/sedes/{branch}).
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $staff) {
            abort(403, 'Acceso denegado: no cuenta con perfil administrativo.');
        }

        if ($staff->branch_id !== null) {
            return redirect()->route('admin.sedes.show', $staff->branch_id);
        }

        $branches = Branch::query()
            ->select(['id', 'name', 'code', 'address', 'phone'])
            ->with([
                'classrooms' => fn ($q) => $q->select(['id', 'branch_id', 'name', 'capacity'])->orderBy('id'),
            ])
            ->withCount('classrooms')
            ->orderBy('id')
            ->get()
            ->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'code' => $b->code ?? 'SED-'.$b->id,
                'address' => $b->address ?? '',
                'phone' => $b->phone ?? '',
                'classrooms_count' => (int) $b->classrooms_count,
                'total_capacity' => (int) $b->classrooms->sum('capacity'),
                'classrooms' => $b->classrooms->map(fn (Classroom $c) => [
                    'id' => $c->id,
                    'branch_id' => $c->branch_id,
                    'name' => $c->name,
                    'capacity' => $c->capacity,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        $totalClassroomsCount = array_sum(array_column($branches, 'classrooms_count'));

        return Inertia::render('Admin/SedesAulas', [
            'branches' => $branches,
            'selectedBranch' => null,
            'classrooms' => [],
            'totalClassroomsCount' => $totalClassroomsCount,
            'initialEditing' => false,
        ]);
    }

    /**
     * Muestra el detalle y gestión de aulas de una sede específica (/admin/sedes/{branch}).
     */
    public function show(Request $request, Branch $branch): Response
    {
        $staff = $request->user()?->staff;

        if (! $staff || (! $staff->isAdmin() && $staff->branch_id !== $branch->id)) {
            abort(403, 'Acceso denegado. No tienes autorización para consultar esta sede.');
        }

        $branchQuery = Branch::query()
            ->select(['id', 'name', 'code'])
            ->orderBy('id');

        if (! $staff->isAdmin() && $staff->branch_id !== null) {
            $branchQuery->where('id', $staff->branch_id);
        }

        $branches = $branchQuery->get()->map(fn (Branch $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'code' => $b->code ?? 'SED-'.$b->id,
            'address' => $b->id === $branch->id ? ($branch->address ?? '') : '',
            'phone' => $b->id === $branch->id ? ($branch->phone ?? '') : '',
        ])->values()->all();

        $classrooms = Classroom::query()
            ->where('branch_id', $branch->id)
            ->select(['id', 'branch_id', 'name', 'capacity'])
            ->withCount('courseSchedules')
            ->orderBy('id')
            ->get()
            ->map(fn (Classroom $c) => [
                'id' => $c->id,
                'branch_id' => $c->branch_id,
                'branch_name' => $branch->name,
                'name' => $c->name,
                'capacity' => $c->capacity,
                'schedules_count' => (int) $c->course_schedules_count,
            ])
            ->values()
            ->all();

        $totalClassroomsCount = $staff->isAdmin()
            ? Classroom::count()
            : count($classrooms);

        return Inertia::render('Admin/SedesAulas', [
            'branches' => $branches,
            'selectedBranch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code ?? 'SED-'.$branch->id,
                'address' => $branch->address ?? '',
                'phone' => $branch->phone ?? '',
            ],
            'classrooms' => $classrooms,
            'totalClassroomsCount' => $totalClassroomsCount,
            'initialEditing' => $request->boolean('edit'),
        ]);
    }

    /**
     * Muestra el Dashboard Central (/admin) con datos reales de la BD.
     */
    public function dashboard(Request $request): Response
    {
        $branchData = $this->getDashboardBranchData($request);

        $data = array_merge(
            AcademicOfferController::getOfferData($request),
            ScheduledCourseController::getScheduledCourseData($request),
            $branchData,
        );

        return Inertia::render('Admin/Index', $data);
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

        return redirect()->route('admin.sedes.index')->with('success', "Sede \"{$branch->name}\" registrada exitosamente.");
    }

    /**
     * Actualiza la información de una sede.
     * Solo la Dirección General o el Coordinador de la sede pueden editarla.
     */
    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $staff || (! $staff->isAdmin() && ! $staff->isCoordinador($branch->id))) {
            abort(403, 'Acceso denegado. No tienes autorización para modificar esta sede.');
        }

        $rules = [
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ];

        if ($staff->isAdmin()) {
            $rules['name'] = ['required', 'string', 'max:255', 'unique:branches,name,'.$branch->id];
            $rules['code'] = ['required', 'string', 'max:50', 'unique:branches,code,'.$branch->id];
        }

        $validated = $request->validate($rules, [
            'name.required' => 'El nombre de la sede es obligatorio.',
            'name.unique' => 'Ya existe otra sede con ese nombre.',
            'code.required' => 'El código de sede es obligatorio.',
            'code.unique' => 'El código de sede ya está en uso.',
        ]);

        $updateData = [
            'address' => ! empty($validated['address']) ? trim($validated['address']) : null,
            'phone' => ! empty($validated['phone']) ? trim($validated['phone']) : null,
        ];

        if ($staff->isAdmin()) {
            $updateData['name'] = trim($validated['name']);
            $updateData['code'] = strtoupper(trim($validated['code']));
        }

        $branch->update($updateData);

        return redirect()->back()->with('success', "Sede \"{$branch->name}\" actualizada exitosamente.");
    }
}
