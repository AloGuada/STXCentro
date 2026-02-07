<?php

namespace App\Http\Controllers\Admin\Sti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Sti\PlanStoreRequest;
use App\Http\Requests\Admin\Sti\PlanUpdateRequest;
use App\Models\Sti\Check;
use App\Models\Sti\CheckEjecucion;
use App\Models\Sti\Equipo;
use App\Models\Sti\Mantenimiento;
use App\Models\Sti\Plan;
use Carbon\Carbon;
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
            ->with(['equipo'])
            ->withCount(['checks', 'mantenimientos' => fn ($q) => $q->where('status', 'pendiente')])
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%"))
            ->when($request->equipo_id, fn ($q, $id) => $q->where('equipo_id', $id))
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/sti/planes/index', [
            'planes' => $planes,
            'equipos' => Equipo::orderBy('descripcion')->get(['id', 'descripcion']),
            'filters' => $request->only(['search', 'equipo_id']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/sti/planes/create', [
            'equipos' => Equipo::orderBy('descripcion')->get(['id', 'descripcion', 'serie']),
        ]);
    }

    public function store(PlanStoreRequest $request): RedirectResponse
    {
        return DB::transaction(function () use ($request) {
            $plan = Plan::create([
                'equipo_id' => $request->equipo_id,
                'descripcion' => $request->descripcion,
                'periodicidad' => $request->periodicidad,
                'fecha_inicial' => $request->fecha_inicial,
                'activo' => $request->boolean('activo', true),
            ]);

            // Crear checks iniciales si se proporcionaron
            if ($request->filled('checks')) {
                foreach ($request->checks as $index => $checkData) {
                    $plan->checks()->create([
                        'descripcion' => $checkData['descripcion'],
                        'orden' => $index,
                    ]);
                }
            }

            // Generar mantenimientos del año actual
            $this->generarMantenimientosAnioInterno($plan, Carbon::parse($request->fecha_inicial)->year);

            return to_route('admin.sti.planes.edit', $plan);
        });
    }

    public function edit(Plan $plan): Response
    {
        $plan->load(['equipo', 'checks', 'mantenimientos' => fn ($q) => $q->with('tecnico')->orderBy('fecha_programada')]);

        return Inertia::render('admin/sti/planes/edit', [
            'plan' => $plan,
            'equipos' => Equipo::orderBy('descripcion')->get(['id', 'descripcion', 'serie']),
        ]);
    }

    public function update(PlanUpdateRequest $request, Plan $plan): RedirectResponse
    {
        $plan->update([
            'descripcion' => $request->descripcion,
            'periodicidad' => $request->periodicidad,
            'fecha_inicial' => $request->fecha_inicial,
            'activo' => $request->boolean('activo', true),
        ]);

        return to_route('admin.sti.planes.index');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        // Solo eliminar si no tiene mantenimientos realizados
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

        // Agregar este check a todos los mantenimientos pendientes del plan
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

        // Solo eliminar si no tiene ejecuciones con resultado true
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

    public function generarMantenimientosAnio(Plan $plan, int $year): RedirectResponse
    {
        $count = $this->generarMantenimientosAnioInterno($plan, $year);

        return back()->with('success', "Se generaron {$count} mantenimientos para el año {$year}.");
    }

    private function generarMantenimientosAnioInterno(Plan $plan, int $year): int
    {
        $fechaInicial = Carbon::parse($plan->fecha_inicial);
        $inicioAnio = Carbon::create($year, 1, 1);
        $finAnio = Carbon::create($year, 12, 31);

        // Si la fecha inicial es después del fin del año, no generar nada
        if ($fechaInicial->gt($finAnio)) {
            return 0;
        }

        // Calcular primera fecha de mantenimiento del año
        if ($fechaInicial->year === $year) {
            $fechaActual = $fechaInicial->copy();
        } else {
            // Calcular cuántos períodos completos hay desde fecha inicial hasta inicio del año
            $diasDesdeInicio = $fechaInicial->diffInDays($inicioAnio);
            $periodosCompletos = (int) floor($diasDesdeInicio / $plan->periodicidad);
            $fechaActual = $fechaInicial->copy()->addDays($periodosCompletos * $plan->periodicidad);

            // Si quedó antes del inicio del año, avanzar un período más
            if ($fechaActual->lt($inicioAnio)) {
                $fechaActual->addDays($plan->periodicidad);
            }
        }

        $checksDelPlan = $plan->checks()->pluck('id');
        $count = 0;

        while ($fechaActual->lte($finAnio)) {
            // Verificar si ya existe un mantenimiento para esta fecha
            $existe = Mantenimiento::where('plan_id', $plan->id)
                ->whereDate('fecha_programada', $fechaActual->toDateString())
                ->exists();

            if (! $existe) {
                $mantenimiento = Mantenimiento::create([
                    'equipo_id' => $plan->equipo_id,
                    'plan_id' => $plan->id,
                    'fecha_programada' => $fechaActual->toDateString(),
                    'descripcion' => $plan->descripcion,
                    'status' => 'pendiente',
                ]);

                // Crear check ejecuciones para cada check del plan
                foreach ($checksDelPlan as $checkId) {
                    CheckEjecucion::create([
                        'mantenimiento_id' => $mantenimiento->id,
                        'check_id' => $checkId,
                        'resultado' => false,
                    ]);
                }

                $count++;
            }

            $fechaActual->addDays($plan->periodicidad);
        }

        return $count;
    }
}
