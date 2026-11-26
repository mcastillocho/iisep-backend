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
     * @return array{branches: list<array<string, mixed>>, careers: list<array<string, mixed>>, allModulesPool: list<array<string, mixed>>, coursesList: list<array<string, mixed>>, assignableCourses: list<array<string, mixed>>}
     */
    public static function getOfferData(Request $request): array
    {
        $user = $request->user();
        $staff = $user?->staff;

        $branchQuery = Branch::query()
            ->select(['id', 'name', 'code', 'address', 'phone'])
            ->where('is_active', true)
            ->orderBy('id');
        if ($staff && $staff->branch_id !== null) {
            $branchQuery->where('id', $staff->branch_id);
        }

        $branches = array_values($branchQuery->get()->map(fn (Branch $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'code' => $b->code ?? 'SED-'.$b->id,
            'address' => $b->address ?? '',
            'phone' => $b->phone ?? '',
        ])->all());

        $careers = array_values(Career::query()
            ->with([
                'modules' => fn ($q) => $q->orderBy('career_module.order'),
                'branches' => fn ($q) => $q->select(['branches.id'])->where('branches.is_active', true),
            ])
            ->withCount('studentCareers')
            ->orderBy('id')
            ->get()
            ->map(fn (Career $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description ?? '',
                'duration_in_weeks' => $c->duration_in_weeks ?? 48,
                'available_branch_ids' => $c->branches->pluck('id')->all(),
                'has_dependencies' => $c->student_careers_count > 0,
                'students_count' => (int) $c->student_careers_count,
                'modules' => array_values($c->modules->map(fn (Module $m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'duration_in_weeks' => $m->duration_in_weeks ?? 8,
                    'order' => $m->pivot->order ?? 1,
                ])->all()),
            ])
            ->all());

        $modules = array_values(Module::query()
            ->withCount(['courses', 'careers', 'enrollments'])
            ->with(['careers:id,name', 'courses:id,module_id,name,description,duration_in_weeks,is_workshop'])
            ->orderBy('id')
            ->get()
            ->map(fn (Module $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'description' => $m->description ?? '',
                'duration_in_weeks' => $m->duration_in_weeks ?? 8,
                'careers_associated' => $m->careers->pluck('name')->all(),
                'courses_count' => (int) $m->courses_count,
                'has_dependencies' => ($m->courses_count > 0 || $m->careers_count > 0 || $m->enrollments_count > 0),
                'courses' => array_values($m->courses->map(fn (Course $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'description' => $c->description ?? '',
                    'duration_in_weeks' => $c->duration_in_weeks ?? 4,
                    'is_workshop' => (bool) $c->is_workshop,
                ])->all()),
            ])
            ->all());

        $courses = array_values(Course::query()
            ->with('module:id,name')
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
            ->all());

        $assignableCourses = array_values(Course::query()
            ->select(['id', 'name', 'duration_in_weeks'])
            ->whereNull('module_id')
            ->where('is_workshop', false)
            ->orderBy('id')
            ->get()
            ->map(fn (Course $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'duration_in_weeks' => $c->duration_in_weeks ?? 4,
            ])
            ->all());

        return [
            'branches' => $branches,
            'careers' => $careers,
            'allModulesPool' => $modules,
            'coursesList' => $courses,
            'assignableCourses' => $assignableCourses,
        ];
    }

    /**
     * Muestra el catálogo de la Oferta Académica con datos reales de la base de datos.
     * Solo accesible para personal administrativo (staff).
     */
    public function index(Request $request): Response
    {
        $staff = $request->user()?->staff;

        if (! $staff) {
            abort(403, 'Acceso denegado: no cuenta con perfil administrativo.');
        }

        return Inertia::render('Admin/OfertaAcademica', self::getOfferData($request));
    }
}
