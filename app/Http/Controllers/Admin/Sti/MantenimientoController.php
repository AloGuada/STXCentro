<?php

namespace App\Http\Controllers\Admin\Sti;

use App\Exports\Sti\GanttAnualExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Sti\CostoStoreRequest;
use App\Http\Requests\Admin\Sti\GenerarMantenimientosRequest;
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
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MantenimientoController extends Controller
{
    public function index(Request $request): Response
    {
        $mantenimientos = Mantenimiento::query()
            ->with(['equipo.asignaciones' => fn ($q) => $q->where('estado', 'activo'), 'tecnico', 'plan'])
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%"))
            ->when($request->plan_id, fn ($q, $id) => $q->where('plan_id', $id))
            ->orderBy('fecha_programada', 'asc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/sti/mantenimientos/index', [
            'mantenimientos' => $mantenimientos,
            'planes' => Plan::orderBy('descripcion')->get(['id', 'descripcion']),
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
        if ($mantenimiento->plan_id && ! $mantenimiento->estaCompleto()) {
            return back()->withErrors(['error' => 'Todos los checks deben estar completados antes de marcar como realizado.']);
        }

        $mantenimiento->update([
            'status' => 'realizado',
            'fecha_realizado' => now(),
        ]);

        if ($mantenimiento->plan_id) {
            $plan = $mantenimiento->plan;
            $year = $mantenimiento->fecha_programada->year;

            $pendientesAnio = Mantenimiento::where('plan_id', $plan->id)
                ->where('equipo_id', $mantenimiento->equipo_id)
                ->where('status', 'pendiente')
                ->whereYear('fecha_programada', $year)
                ->count();

            if ($pendientesAnio === 0 && $plan->activo) {
                $primerMant = Mantenimiento::where('plan_id', $plan->id)
                    ->where('equipo_id', $mantenimiento->equipo_id)
                    ->orderBy('fecha_programada')
                    ->first();

                $fechaInicial = $primerMant ? Carbon::parse($primerMant->fecha_programada) : Carbon::now();
                $this->generarMantenimientosAnioInterno($plan, $mantenimiento->equipo_id, $year + 1, $fechaInicial);
            }
        } elseif ($request->boolean('crear_siguiente')) {
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

    public function programacion(Request $request): Response
    {
        $year = (int) $request->input('year', now()->year);

        $equipos = Equipo::query()
            ->withCount(['mantenimientos' => fn ($q) => $q->whereYear('fecha_programada', $year)])
            ->orderBy('descripcion')
            ->get();

        return Inertia::render('admin/sti/mantenimientos/programacion', [
            'equipos' => $equipos,
            'planes' => Plan::where('activo', true)->orderBy('descripcion')->get(['id', 'descripcion', 'periodicidad']),
            'year' => $year,
        ]);
    }

    public function generar(GenerarMantenimientosRequest $request): RedirectResponse
    {
        $plan = Plan::findOrFail($request->plan_id);
        $year = $request->year;
        $fechaInicial = Carbon::parse($request->fecha_inicial);
        $totalCount = 0;

        DB::transaction(function () use ($plan, $request, $year, $fechaInicial, &$totalCount) {
            $totalCount = $this->generarMantenimientosAnioInterno($plan, (int) $request->equipo_id, $year, $fechaInicial);
        });

        return back()->with('success', "Se generaron {$totalCount} mantenimientos para el año {$year}.");
    }

    public function ganttAnual(Request $request): Response
    {
        $year = $request->input('year', now()->year);

        $mantenimientos = Mantenimiento::query()
            ->with(['equipo.asignaciones' => fn ($q) => $q->where('estado', 'activo')->select('id', 'equipo_id', 'empleado', 'departamento_id')->with('departamento:id,descripcion'), 'tecnico', 'plan'])
            ->whereYear('fecha_programada', $year)
            ->when($request->equipo_id, fn ($q, $id) => $q->where('equipo_id', $id))
            ->when($request->plan_id, fn ($q, $id) => $q->where('plan_id', $id))
            ->orderBy('fecha_programada')
            ->get();

        return Inertia::render('admin/sti/mantenimientos/gantt-anual', [
            'mantenimientos' => $mantenimientos,
            'year' => (int) $year,
            'equipos' => Equipo::orderBy('descripcion')->get(['id', 'descripcion']),
            'planes' => Plan::where('activo', true)->orderBy('descripcion')->get(['id', 'descripcion']),
            'filters' => $request->only(['year', 'equipo_id', 'plan_id']),
        ]);
    }

    public function exportarGanttAnual(Request $request): HttpResponse
    {
        $year = $request->input('year', now()->year);

        $mantenimientos = Mantenimiento::query()
            ->with(['equipo.asignaciones' => fn ($q) => $q->where('estado', 'activo')->with('departamento:id,descripcion'), 'plan'])
            ->whereYear('fecha_programada', $year)
            ->when($request->equipo_id, fn ($q, $id) => $q->where('equipo_id', $id))
            ->when($request->plan_id, fn ($q, $id) => $q->where('plan_id', $id))
            ->orderBy('fecha_programada')
            ->get();

        $porEquipo = $mantenimientos->groupBy('equipo_id')->map(function ($mants) {
            $equipo = $mants->first()->equipo;
            $asignacion = $equipo?->asignaciones?->first();

            return [
                'equipo' => $equipo?->descripcion ?? '-',
                'asignado_a' => $asignacion?->empleado ?? '-',
                'departamento' => $asignacion?->departamento?->descripcion ?? '-',
                'meses' => collect(range(1, 12))->map(fn ($mes) => $mants->filter(fn ($m) => Carbon::parse($m->fecha_programada)->month === $mes)->map(fn ($m) => [
                    'dia' => Carbon::parse($m->fecha_programada)->day,
                    'status' => $m->status,
                ]))->toArray(),
            ];
        })->values();

        $pdf = Pdf::loadView('pdf.sti.gantt-anual', [
            'year' => $year,
            'datos' => $porEquipo,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("gantt-anual-{$year}.pdf");
    }

    public function exportarGanttAnualExcel(Request $request): BinaryFileResponse
    {
        $year = (int) $request->input('year', now()->year);

        return Excel::download(
            new GanttAnualExport(
                year: $year,
                equipoId: $request->equipo_id ? (int) $request->equipo_id : null,
                planId: $request->plan_id ? (int) $request->plan_id : null,
            ),
            "gantt-anual-{$year}.xlsx"
        );
    }

    private function generarMantenimientosAnioInterno(Plan $plan, int $equipoId, int $year, Carbon $fechaInicial): int
    {
        $inicioAnio = Carbon::create($year, 1, 1);
        $finAnio = Carbon::create($year, 12, 31);

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
                ->where('equipo_id', $equipoId)
                ->whereDate('fecha_programada', $fechaActual->toDateString())
                ->exists();

            if (! $existe) {
                $mantenimiento = Mantenimiento::create([
                    'equipo_id' => $equipoId,
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
            ->with(['equipo.asignaciones' => fn ($q) => $q->where('estado', 'activo')->select('id', 'equipo_id', 'empleado', 'departamento_id')->with('departamento:id,descripcion'), 'tecnico'])
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
