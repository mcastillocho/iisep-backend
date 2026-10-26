<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Career;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcademicOfferController extends Controller
{
    /**
     * Obtiene los datos de la Oferta Académica desde la base de datos.
     *
     * @return array{branches: list<array<string, mixed>>, careers: list<array<string, mixed>>, allModulesPool: list<array<string, mixed>>, coursesList: list<array<string, mixed>>}
     */
    public static function getOfferData(Request $request): array
    {
        $user = $request->user();
        $staff = $user?->staff;

        $branchQuery = Branch::query()->orderBy('id');
        if ($staff && $staff->branch_id !== null) {
            $branchQuery->where('id', $staff->branch_id);
        }

        $branches = $branchQuery->get()->map(fn (Branch $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'code' => $b->code ?? 'SED-'.$b->id,
            'address' => $b->address ?? '',
            'phone' => $b->phone ?? '',
        ])->values()->all();

        $allBranchIds = Branch::pluck('id')->all();

        $careers = Career::query()
            ->with(['modules' => fn ($q) => $q->orderBy('career_module.order')])
            ->orderBy('id')
            ->get()
            ->map(fn (Career $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description ?? '',
                'estimated_capacity' => 30,
                'available_branch_ids' => $allBranchIds,
                'modules' => $c->modules->map(fn (Module $m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'duration_in_weeks' => $m->duration_in_weeks ?? 8,
                    'order' => $m->pivot->order ?? 1,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        $modules = Module::query()
            ->withCount('courses')
            ->with('careers')
            ->orderBy('id')
            ->get()
            ->map(fn (Module $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'description' => $m->description ?? '',
                'duration_in_weeks' => $m->duration_in_weeks ?? 8,
                'careers_associated' => $m->careers->pluck('name')->all(),
                'courses_count' => $m->courses_count,
            ])
            ->values()
            ->all();

        $courses = Course::query()
            ->with('module')
            ->withCount('scheduledCourses')
            ->orderBy('id')
            ->get()
            ->map(fn (Course $c) => [
                'id' => $c->id,
                'module_id' => $c->module_id,
                'module_name' => $c->module?->name,
                'name' => $c->name,
                'description' => $c->description ?? '',
                'duration_in_weeks' => $c->duration_in_weeks ?? 4,
                'is_workshop' => (bool) $c->is_workshop,
                'has_scheduled_courses' => $c->scheduled_courses_count > 0,
                'scheduled_courses_count' => (int) $c->scheduled_courses_count,
            ])
            ->values()
            ->all();

        return [
            'branches' => $branches,
            'careers' => $careers,
            'allModulesPool' => $modules,
            'coursesList' => $courses,
        ];
    }

    /**
     * Muestra el catálogo de la Oferta Académica con datos reales de la base de datos.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/OfertaAcademica', self::getOfferData($request));
    }
}
