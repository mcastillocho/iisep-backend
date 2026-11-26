<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentCareerStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Classroom;
use App\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
            ->whereHas('branch')
            ->select(['id', 'branch_id', 'name', 'capacity'])
            ->with(['branch:id,name'])
            ->withCount('courseSchedules')
            ->orderBy('id');

        if ($staff && $staff->branch_id !== null) {
            $branchQuery->where('id', $staff->branch_id);
            $classroomQuery->where('branch_id', $staff->branch_id);
        }

        $branches = array_values($branchQuery->get()->map(fn (Branch $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'code' => $b->code ?? 'SED-'.$b->id,
            'address' => $b->address ?? '',
            'phone' => $b->phone ?? '',
            'classrooms_count' => (int) $b->classrooms_count,
            'total_capacity' => (int) $b->classrooms->sum('capacity'),
            'classrooms' => array_values($b->classrooms->map(fn (Classroom $c) => [
                'id' => $c->id,
                'branch_id' => $c->branch_id,
                'name' => $c->name,
                'capacity' => $c->capacity,
            ])->all()),
        ])->all());

        $classrooms = array_values($classroomQuery->get()->map(fn (Classroom $c) => [
            'id' => $c->id,
            'branch_id' => $c->branch_id,
            'branch_name' => $c->branch->name,
            'name' => $c->name,
            'capacity' => $c->capacity,
            'schedules_count' => (int) $c->course_schedules_count,
        ])->all());

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

        $inactiveBranches = Branch::onlyTrashed()
            ->select(['id', 'name', 'code', 'address', 'phone', 'deleted_at'])
            ->withCount([
                'classrooms' => fn ($q) => $q->onlyTrashed()->where('deleted_with_branch', true),
            ])
            ->orderByDesc('deleted_at')
            ->get()
            ->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'code' => $b->code ?? 'SED-'.$b->id,
                'address' => $b->address ?? '',
                'phone' => $b->phone ?? '',
                'deleted_at' => $b->deleted_at?->toIso8601String(),
                'restorable_classrooms_count' => (int) $b->classrooms_count,
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/SedesAulas', [
            'branches' => $branches,
            'inactiveBranches' => $inactiveBranches,
            'selectedBranch' => null,
            'classrooms' => [],
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
            'name' => ['required', 'string', 'max:255', Rule::unique('branches', 'name')->whereNull('deleted_at')],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('branches', 'code')->whereNull('deleted_at')],
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
            $rules['name'] = ['required', 'string', 'max:255', Rule::unique('branches', 'name')->ignore($branch->id)->whereNull('deleted_at')];
            $rules['code'] = ['required', 'string', 'max:50', Rule::unique('branches', 'code')->ignore($branch->id)->whereNull('deleted_at')];
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

    /**
     * Da de baja una sede (marca is_active = false, aplica SoftDelete a la sede y en cascada lógica a sus aulas activas).
     * Solo el Administrador General puede ejecutar esta acción, y únicamente si:
     * - No es la última sede activa del instituto
     * - No tiene personal administrativo (staff) asignado
     * - No tiene alumnos vigentes o en reserva asignados a carreras en esta sede (student_careers)
     * - No tiene cursos vigentes o programados (end_date >= hoy)
     * - No tiene matrículas en curso o preinscripciones pendientes (activo / preinscrito)
     */
    public function destroy(Request $request, Branch $branch): RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $staff || ! $staff->isAdmin()) {
            abort(403, 'Acceso denegado. Solo el Administrador General cuenta con permisos para dar de baja una sede.');
        }

        $hasOtherActiveBranches = Branch::query()
            ->where('is_active', true)
            ->whereKeyNot($branch->id)
            ->exists();

        if (! $hasOtherActiveBranches) {
            return redirect()->back()->withErrors([
                'branch' => "No es posible dar de baja la sede \"{$branch->name}\" porque es la única sede activa del instituto.",
            ]);
        }

        if ($branch->staff()->exists()) {
            return redirect()->back()->withErrors([
                'branch' => "No es posible dar de baja la sede \"{$branch->name}\" porque tiene personal administrativo asignado.",
            ]);
        }

        $hasActiveCareerStudents = $branch->studentCareers()
            ->whereIn('status', [
                StudentCareerStatus::ACTIVO->value,
                StudentCareerStatus::EN_RESERVA->value,
            ])
            ->exists();

        if ($hasActiveCareerStudents) {
            return redirect()->back()->withErrors([
                'branch' => "No es posible dar de baja la sede \"{$branch->name}\" porque tiene alumnos vigentes o en reserva asignados a esta sede.",
            ]);
        }

        $todayInPeru = Carbon::now('America/Lima')->toDateString();

        $hasActiveScheduledCourses = $branch->scheduledCourses()
            ->whereDate('end_date', '>=', $todayInPeru)
            ->exists();

        if ($hasActiveScheduledCourses) {
            return redirect()->back()->withErrors([
                'branch' => "No es posible dar de baja la sede \"{$branch->name}\" porque tiene cursos vigentes o programados.",
            ]);
        }

        $hasActiveEnrollments = Enrollment::query()
            ->whereIn('status', [
                EnrollmentStatus::ACTIVO->value,
                EnrollmentStatus::PREINSCRITO->value,
            ])
            ->whereHas('scheduledCourse', fn ($q) => $q->withTrashed()->where('branch_id', $branch->id))
            ->exists();

        if ($hasActiveEnrollments) {
            return redirect()->back()->withErrors([
                'branch' => "No es posible dar de baja la sede \"{$branch->name}\" porque tiene matrículas en curso o preinscripciones pendientes.",
            ]);
        }

        $branchName = $branch->name;
        $now = Carbon::now();

        DB::transaction(function () use ($branch, $now): void {
            $branch->classrooms()->update([
                'deleted_with_branch' => true,
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);

            $branch->update(['is_active' => false]);
            $branch->delete();
        });

        return redirect()->route('admin.sedes.index')->with('success', "Sede \"{$branchName}\" dada de baja exitosamente.");
    }

    /**
     * Reactiva una sede dada de baja y restaura únicamente las aulas que fueron dadas de baja junto con ella.
     * Solo el Administrador General cuenta con autorización para reactivar sedes.
     */
    public function restore(Request $request, Branch $branch): RedirectResponse
    {
        $staff = $request->user()?->staff;

        if (! $staff || ! $staff->isAdmin()) {
            abort(403, 'Acceso denegado. Solo el Administrador General cuenta con permisos para reactivar una sede.');
        }

        if (! $branch->trashed() && $branch->is_active) {
            return redirect()->route('admin.sedes.index')->withErrors([
                'branch' => "La sede \"{$branch->name}\" ya se encuentra activa.",
            ]);
        }

        if (Branch::query()->where('name', $branch->name)->whereKeyNot($branch->id)->exists()) {
            return redirect()->back()->withErrors([
                'branch' => "No es posible reactivar la sede \"{$branch->name}\" porque ya existe otra sede activa con el mismo nombre.",
            ]);
        }

        if ($branch->code !== null && Branch::query()->where('code', $branch->code)->whereKeyNot($branch->id)->exists()) {
            return redirect()->back()->withErrors([
                'branch' => "No es posible reactivar la sede \"{$branch->name}\" porque el código \"{$branch->code}\" ya está en uso por otra sede activa.",
            ]);
        }

        $now = Carbon::now();

        DB::transaction(function () use ($branch, $now): void {
            if ($branch->trashed()) {
                $branch->restore();
            }

            $branch->update(['is_active' => true]);

            $branch->classrooms()
                ->onlyTrashed()
                ->where('deleted_with_branch', true)
                ->update([
                    'deleted_at' => null,
                    'deleted_with_branch' => false,
                    'updated_at' => $now,
                ]);
        });

        return redirect()->route('admin.sedes.index')->with('success', "Sede \"{$branch->name}\" reactivada exitosamente.");
    }
}
