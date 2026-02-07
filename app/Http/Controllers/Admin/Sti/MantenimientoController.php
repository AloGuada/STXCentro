<?php

namespace App\Http\Controllers\Admin\Sti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Sti\CostoStoreRequest;
use App\Http\Requests\Admin\Sti\MantenimientoStoreRequest;
use App\Http\Requests\Admin\Sti\MantenimientoUpdateRequest;
use App\Http\Requests\Admin\Sti\MediaStoreRequest;
use App\Models\Media;
use App\Models\Sti\CheckEjecucion;
use App\Models\Sti\CostoMantenimiento;
use App\Models\Sti\Equipo;
use App\Models\Sti\Mantenimiento;
use App\Models\Sti\Plan;
use App\Models\Sti\Tecnico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MantenimientoController extends Controller
{
    public function index(Request $request): Response
    {
        $mantenimientos = Mantenimiento::query()
            ->with(['equipo', 'tecnico', 'plan'])
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%"))
            ->when($request->plan_id, fn ($q, $id) => $q->where('plan_id', $id))
            ->orderBy('fecha_programada', 'asc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/sti/mantenimientos/index', [
            'mantenimientos' => $mantenimientos,
            'planes' => Plan::with('equipo')->orderBy('descripcion')->get(['id', 'equipo_id', 'descripcion']),
            'filters' => $request->only(['search', 'plan_id']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/sti/mantenimientos/create', [
            'equipos' => Equipo::orderBy('descripcion')->get(['id', 'descripcion', 'serie']),
            'tecnicos' => Tecnico::where('activo', true)->orderBy('descripcion')->get(['id', 'descripcion']),
        ]);
    }

    public function store(MantenimientoStoreRequest $request): RedirectResponse
    {
        Mantenimiento::create([
            'equipo_id' => $request->equipo_id,
            'fecha_programada' => $request->fecha_programada,
            'descripcion' => $request->descripcion,
            'tecnico_id' => $request->tecnico_id,
            'status' => 'pendiente',
        ]);

        return to_route('admin.sti.mantenimientos.index');
    }

    public function edit(Mantenimiento $mantenimiento): Response
    {
        $mantenimiento->load(['equipo', 'tecnico', 'media', 'costos', 'plan', 'checkEjecuciones.check', 'checkEjecuciones.tecnico']);

        return Inertia::render('admin/sti/mantenimientos/edit', [
            'mantenimiento' => $mantenimiento,
            'equipos' => Equipo::orderBy('descripcion')->get(['id', 'descripcion', 'serie']),
            'tecnicos' => Tecnico::where('activo', true)->orderBy('descripcion')->get(['id', 'descripcion']),
        ]);
    }

    public function update(MantenimientoUpdateRequest $request, Mantenimiento $mantenimiento): RedirectResponse
    {
        $mantenimiento->update([
            'equipo_id' => $request->equipo_id,
            'fecha_programada' => $request->fecha_programada,
            'descripcion' => $request->descripcion,
            'tecnico_id' => $request->tecnico_id,
        ]);

        return to_route('admin.sti.mantenimientos.index');
    }

    public function destroy(Mantenimiento $mantenimiento): RedirectResponse
    {
        // Eliminar archivos asociados
        foreach ($mantenimiento->media as $media) {
            Storage::disk('public')->delete($media->path);
        }
        $mantenimiento->media()->delete();
        $mantenimiento->costos()->delete();
        $mantenimiento->delete();

        return to_route('admin.sti.mantenimientos.index');
    }

    public function completar(Request $request, Mantenimiento $mantenimiento): RedirectResponse
    {
        // Verificar que todos los checks estén completos (si tiene plan)
        if ($mantenimiento->plan_id && ! $mantenimiento->estaCompleto()) {
            return back()->withErrors(['error' => 'Todos los checks deben estar completados antes de marcar como realizado.']);
        }

        $mantenimiento->update([
            'status' => 'realizado',
            'fecha_realizado' => now(),
        ]);

        // Si tiene plan, verificar si es el último del año y generar siguiente año
        if ($mantenimiento->plan_id) {
            $plan = $mantenimiento->plan;
            $year = $mantenimiento->fecha_programada->year;

            // Verificar si quedan mantenimientos pendientes del mismo plan en el año
            $pendientesAnio = Mantenimiento::where('plan_id', $plan->id)
                ->where('status', 'pendiente')
                ->whereYear('fecha_programada', $year)
                ->count();

            if ($pendientesAnio === 0 && $plan->activo) {
                // Generar mantenimientos del siguiente año
                $this->generarMantenimientosAnioInterno($plan, $year + 1);
            }
        } elseif ($request->boolean('crear_siguiente')) {
            // Lógica anterior para mantenimientos sin plan
            $siguienteFecha = $request->fecha_siguiente
                ? $request->fecha_siguiente
                : now()->addDays(30);

            Mantenimiento::create([
                'equipo_id' => $mantenimiento->equipo_id,
                'fecha_programada' => $siguienteFecha,
                'descripcion' => $mantenimiento->descripcion,
                'tecnico_id' => $mantenimiento->tecnico_id,
                'status' => 'pendiente',
            ]);
        }

        return to_route('admin.sti.mantenimientos.index')->with('success', 'Mantenimiento marcado como realizado.');
    }

    public function toggleCheck(Request $request, Mantenimiento $mantenimiento, CheckEjecucion $checkEjecucion): RedirectResponse
    {
        if ($checkEjecucion->mantenimiento_id !== $mantenimiento->id) {
            abort(404);
        }

        $request->validate([
            'resultado' => ['required', 'boolean'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $checkEjecucion->update([
            'resultado' => $request->boolean('resultado'),
            'observaciones' => $request->observaciones,
            'tecnico_id' => $request->boolean('resultado') ? auth()->user()?->tecnico?->id : null,
        ]);

        return back();
    }

    public function ganttAnual(Request $request): Response
    {
        $year = $request->input('year', now()->year);

        $mantenimientos = Mantenimiento::query()
            ->with(['equipo', 'tecnico', 'plan'])
            ->whereYear('fecha_programada', $year)
            ->when($request->equipo_id, fn ($q, $id) => $q->where('equipo_id', $id))
            ->when($request->plan_id, fn ($q, $id) => $q->where('plan_id', $id))
            ->orderBy('fecha_programada')
            ->get();

        return Inertia::render('admin/sti/mantenimientos/gantt-anual', [
            'mantenimientos' => $mantenimientos,
            'year' => (int) $year,
            'equipos' => Equipo::orderBy('descripcion')->get(['id', 'descripcion']),
            'planes' => Plan::with('equipo')->where('activo', true)->orderBy('descripcion')->get(['id', 'equipo_id', 'descripcion']),
            'filters' => $request->only(['year', 'equipo_id', 'plan_id']),
        ]);
    }

    private function generarMantenimientosAnioInterno(Plan $plan, int $year): int
    {
        $fechaInicial = \Carbon\Carbon::parse($plan->fecha_inicial);
        $inicioAnio = \Carbon\Carbon::create($year, 1, 1);
        $finAnio = \Carbon\Carbon::create($year, 12, 31);

        if ($fechaInicial->gt($finAnio)) {
            return 0;
        }

        if ($fechaInicial->year === $year) {
            $fechaActual = $fechaInicial->copy();
        } else {
            $diasDesdeInicio = $fechaInicial->diffInDays($inicioAnio);
            $periodosCompletos = (int) floor($diasDesdeInicio / $plan->periodicidad);
            $fechaActual = $fechaInicial->copy()->addDays($periodosCompletos * $plan->periodicidad);

            if ($fechaActual->lt($inicioAnio)) {
                $fechaActual->addDays($plan->periodicidad);
            }
        }

        $checksDelPlan = $plan->checks()->pluck('id');
        $count = 0;

        while ($fechaActual->lte($finAnio)) {
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

    public function gantt(Request $request): Response
    {
        $mes = $request->input('mes', now()->format('Y-m'));

        $mantenimientos = Mantenimiento::query()
            ->with(['equipo', 'tecnico'])
            ->whereYear('fecha_programada', substr($mes, 0, 4))
            ->whereMonth('fecha_programada', substr($mes, 5, 2))
            ->when($request->equipo_id, fn ($q, $id) => $q->where('equipo_id', $id))
            ->when($request->tecnico_id, fn ($q, $id) => $q->where('tecnico_id', $id))
            ->orderBy('fecha_programada')
            ->get();

        return Inertia::render('admin/sti/mantenimientos/gantt', [
            'mantenimientos' => $mantenimientos,
            'mes' => $mes,
            'equipos' => Equipo::orderBy('descripcion')->get(['id', 'descripcion']),
            'tecnicos' => Tecnico::where('activo', true)->orderBy('descripcion')->get(['id', 'descripcion']),
            'filters' => $request->only(['mes', 'equipo_id', 'tecnico_id']),
        ]);
    }

    public function storeMedia(MediaStoreRequest $request, Mantenimiento $mantenimiento): RedirectResponse
    {
        $file = $request->file('archivo');
        $path = $file->store('sti/mantenimientos', 'public');

        $mantenimiento->media()->create([
            'descripcion' => $request->descripcion ?? $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return back()->with('success', 'Imagen subida correctamente.');
    }

    public function destroyMedia(Mantenimiento $mantenimiento, Media $media): RedirectResponse
    {
        if ($media->mediable_id !== $mantenimiento->id || $media->mediable_type !== Mantenimiento::class) {
            abort(404);
        }

        Storage::disk('public')->delete($media->path);
        $media->delete();

        return back()->with('success', 'Imagen eliminada correctamente.');
    }

    public function storeCosto(CostoStoreRequest $request, Mantenimiento $mantenimiento): RedirectResponse
    {
        $mantenimiento->costos()->create([
            'descripcion' => $request->descripcion,
            'cantidad' => $request->cantidad,
        ]);

        return back()->with('success', 'Costo agregado correctamente.');
    }

    public function destroyCosto(Mantenimiento $mantenimiento, CostoMantenimiento $costo): RedirectResponse
    {
        if ($costo->costeable_id !== $mantenimiento->id || $costo->costeable_type !== Mantenimiento::class) {
            abort(404);
        }

        $costo->delete();

        return back()->with('success', 'Costo eliminado correctamente.');
    }
}
