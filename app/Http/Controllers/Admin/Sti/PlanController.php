<?php

namespace App\Http\Controllers\Admin\Sti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Sti\PlanStoreRequest;
use App\Http\Requests\Admin\Sti\PlanUpdateRequest;
use App\Models\Sti\Check;
use App\Models\Sti\CheckEjecucion;
use App\Models\Sti\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function index(Request $request): Response
    {
        $planes = Plan::query()
            ->withCount(['checks', 'mantenimientos' => fn ($q) => $q->where('status', 'pendiente')])
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%"))
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/sti/planes/index', [
            'planes' => $planes,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/sti/planes/create');
    }

    public function store(PlanStoreRequest $request): RedirectResponse
    {
        return DB::transaction(function () use ($request) {
            $plan = Plan::create([
                'descripcion' => $request->descripcion,
                'periodicidad' => $request->periodicidad,
                'activo' => $request->boolean('activo', true),
            ]);

            if ($request->filled('checks')) {
                foreach ($request->checks as $index => $checkData) {
                    $plan->checks()->create([
                        'descripcion' => $checkData['descripcion'],
                        'orden' => $index,
                    ]);
                }
            }

            return to_route('admin.sti.planes.edit', $plan);
        });
    }

    public function edit(Plan $plan): Response
    {
        $plan->load(['checks', 'mantenimientos' => fn ($q) => $q->with(['tecnico', 'equipo'])->orderBy('fecha_programada')]);

        return Inertia::render('admin/sti/planes/edit', [
            'plan' => $plan,
        ]);
    }

    public function update(PlanUpdateRequest $request, Plan $plan): RedirectResponse
    {
        $plan->update([
            'descripcion' => $request->descripcion,
            'periodicidad' => $request->periodicidad,
            'activo' => $request->boolean('activo', true),
        ]);

        return to_route('admin.sti.planes.index');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->mantenimientos()->where('status', 'realizado')->exists()) {
            return back()->withErrors(['error' => 'No se puede eliminar un plan con mantenimientos realizados.']);
        }

        $plan->mantenimientos()->delete();
        $plan->checks()->delete();
        $plan->delete();

        return to_route('admin.sti.planes.index');
    }

    public function storeCheck(Request $request, Plan $plan): RedirectResponse
    {
        $request->validate([
            'descripcion' => ['required', 'string', 'max:255'],
        ]);

        $maxOrden = $plan->checks()->max('orden') ?? -1;

        $check = $plan->checks()->create([
            'descripcion' => $request->descripcion,
            'orden' => $maxOrden + 1,
        ]);

        $mantenimientosPendientes = $plan->mantenimientos()->where('status', 'pendiente')->get();
        foreach ($mantenimientosPendientes as $mantenimiento) {
            CheckEjecucion::create([
                'mantenimiento_id' => $mantenimiento->id,
                'check_id' => $check->id,
                'resultado' => false,
            ]);
        }

        return back();
    }

    public function updateCheck(Request $request, Plan $plan, Check $check): RedirectResponse
    {
        if ($check->plan_id !== $plan->id) {
            abort(404);
        }

        $request->validate([
            'descripcion' => ['required', 'string', 'max:255'],
        ]);

        $check->update([
            'descripcion' => $request->descripcion,
        ]);

        return back();
    }

    public function destroyCheck(Plan $plan, Check $check): RedirectResponse
    {
        if ($check->plan_id !== $plan->id) {
            abort(404);
        }

        if ($check->ejecuciones()->where('resultado', true)->exists()) {
            return back()->withErrors(['error' => 'No se puede eliminar un check que ya fue completado en algún mantenimiento.']);
        }

        $check->ejecuciones()->delete();
        $check->delete();

        return back();
    }

    public function reorderChecks(Request $request, Plan $plan): RedirectResponse
    {
        $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:sti_checks,id'],
        ]);

        foreach ($request->order as $index => $checkId) {
            Check::where('id', $checkId)
                ->where('plan_id', $plan->id)
                ->update(['orden' => $index]);
        }

        return back();
    }
}
