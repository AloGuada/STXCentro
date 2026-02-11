<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\CorteStoreRequest;
use App\Http\Requests\Admin\Prod\ExtraStoreRequest;
use App\Models\Prod\Corte;
use App\Models\Prod\Extra;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\Liquidacion;
use App\Models\Prod\Registro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CorteController extends Controller
{
    public function index(Request $request): Response
    {
        $cortes = Corte::query()
            ->withCount('liquidaciones')
            ->when($request->search, fn ($q, $s) => $q->where('semana', 'like', "%{$s}%"))
            ->orderByDesc('semana')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/prod/cortes/index', [
            'cortes' => $cortes,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/prod/cortes/create');
    }

    public function store(CorteStoreRequest $request): RedirectResponse
    {
        $corte = Corte::create([
            'semana' => $request->semana,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin' => $request->fecha_fin,
        ]);

        return to_route('admin.prod.cortes.show', $corte);
    }

    public function show(Corte $corte): Response
    {
        $corte->load([
            'liquidaciones.grupoTrabajo',
            'liquidaciones.detalles',
            'liquidaciones.extras',
            'liquidaciones.empleados',
            'liquidaciones.generador',
        ]);

        return Inertia::render('admin/prod/cortes/show', [
            'corte' => $corte,
        ]);
    }

    public function destroy(Corte $corte): RedirectResponse
    {
        if ($corte->cerrado) {
            return back()->withErrors(['error' => 'No se puede eliminar un corte cerrado.']);
        }

        $corte->liquidaciones()->delete();
        $corte->delete();

        return to_route('admin.prod.cortes.index');
    }

    public function cerrar(Corte $corte): RedirectResponse
    {
        if ($corte->cerrado) {
            return back()->withErrors(['error' => 'Este corte ya esta cerrado.']);
        }

        return DB::transaction(function () use ($corte) {
            // Get all registros within the date range
            $registros = Registro::query()
                ->with(['concepto', 'grupoTrabajo.empleados'])
                ->whereBetween('fecha', [$corte->fecha_inicio, $corte->fecha_fin])
                ->get();

            // Group by grupo_trabajo_id
            $porGrupo = $registros->groupBy('grupo_trabajo_id');

            foreach ($porGrupo as $grupoTrabajoId => $registrosGrupo) {
                $totalKilos = 0;
                $totalProduccion = 0;
                $detallesData = [];

                // Group registros by concepto_id within this grupo
                $porConcepto = $registrosGrupo->groupBy('concepto_id');

                foreach ($porConcepto as $conceptoId => $registrosConcepto) {
                    $concepto = $registrosConcepto->first()->concepto;
                    $cantidadTotal = $registrosConcepto->sum('cantidad');
                    $kilos = round($cantidadTotal * $concepto->peso_unitario, 3);

                    // Find the precio_kilo for this concepto
                    $grupoPrecioConcepto = GrupoPrecioConcepto::query()
                        ->whereHas('grupoPrecio', fn ($q) => $q->where('obra_id', $concepto->obra_id))
                        ->where('concepto_id', $conceptoId)
                        ->with('grupoPrecio')
                        ->first();

                    $precioKilo = $grupoPrecioConcepto?->grupoPrecio?->precio_kilo ?? 0;
                    $grupoPrecioId = $grupoPrecioConcepto?->grupo_precio_id ?? 0;
                    $total = round($kilos * $precioKilo, 2);

                    $totalKilos += $kilos;
                    $totalProduccion += $total;

                    $detallesData[] = [
                        'concepto_id' => $conceptoId,
                        'grupo_precio_id' => $grupoPrecioId,
                        'cantidad' => $cantidadTotal,
                        'kilos' => $kilos,
                        'precio_kilo_aplicado' => $precioKilo,
                        'total' => $total,
                    ];
                }

                // Create liquidacion
                $liquidacion = Liquidacion::create([
                    'corte_id' => $corte->id,
                    'grupo_trabajo_id' => $grupoTrabajoId,
                    'total_kilos' => $totalKilos,
                    'total_produccion' => $totalProduccion,
                    'total_extras' => 0,
                    'total_final' => $totalProduccion,
                    'generado_en' => now(),
                    'generado_por' => auth()->id(),
                ]);

                // Create detalles
                foreach ($detallesData as $detalle) {
                    $liquidacion->detalles()->create($detalle);
                }

                // Snapshot empleados
                $grupoTrabajo = $registrosGrupo->first()->grupoTrabajo;
                if ($grupoTrabajo) {
                    foreach ($grupoTrabajo->empleados as $empleado) {
                        $montoAsignado = round($totalProduccion * ($empleado->porcentaje / 100), 2);

                        $liquidacion->empleados()->create([
                            'nombre' => $empleado->nombre,
                            'no_empleado' => $empleado->no_empleado,
                            'porcentaje' => $empleado->porcentaje,
                            'monto_asignado' => $montoAsignado,
                        ]);
                    }
                }
            }

            $corte->update([
                'cerrado' => true,
                'fecha_cierre' => now(),
            ]);

            return back();
        });
    }

    public function storeExtra(ExtraStoreRequest $request, Corte $corte, Liquidacion $liquidacion): RedirectResponse
    {
        if ($liquidacion->corte_id !== $corte->id) {
            abort(404);
        }

        $extra = $liquidacion->extras()->create([
            'descripcion' => $request->descripcion,
            'monto' => $request->monto,
        ]);

        // Recalculate totals
        $totalExtras = $liquidacion->extras()->sum('monto');
        $liquidacion->update([
            'total_extras' => $totalExtras,
            'total_final' => $liquidacion->total_produccion + $totalExtras,
        ]);

        return back();
    }

    public function destroyExtra(Corte $corte, Liquidacion $liquidacion, Extra $extra): RedirectResponse
    {
        if ($liquidacion->corte_id !== $corte->id || $extra->liquidacion_id !== $liquidacion->id) {
            abort(404);
        }

        $extra->delete();

        // Recalculate totals
        $totalExtras = $liquidacion->extras()->sum('monto');
        $liquidacion->update([
            'total_extras' => $totalExtras,
            'total_final' => $liquidacion->total_produccion + $totalExtras,
        ]);

        return back();
    }
}
