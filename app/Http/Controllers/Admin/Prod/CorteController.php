<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\CorteStoreRequest;
use App\Models\Prod\Corte;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\Liquidacion;
use App\Models\Prod\PagoExtra;
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
            'liquidaciones.empleados',
            'liquidaciones.generador',
        ]);

        $data = ['corte' => $corte];

        if (! $corte->cerrado) {
            $registros = Registro::query()
                ->with(['concepto.obra', 'grupoTrabajo'])
                ->whereBetween('fecha', [$corte->fecha_inicio, $corte->fecha_fin])
                ->get()
                ->groupBy('grupo_trabajo_id');

            $pagosExtra = PagoExtra::query()
                ->with(['tipo', 'grupoTrabajo'])
                ->where('corte_id', $corte->id)
                ->get()
                ->groupBy('grupo_trabajo_id');

            $data['registrosPreview'] = $registros;
            $data['pagosExtraPreview'] = $pagosExtra;
        } else {
            $pagosExtra = PagoExtra::query()
                ->with(['tipo', 'grupoTrabajo'])
                ->where('corte_id', $corte->id)
                ->get()
                ->groupBy('grupo_trabajo_id');

            $data['pagosExtraPreview'] = $pagosExtra;
        }

        return Inertia::render('admin/prod/cortes/show', $data);
    }

    public function destroy(Corte $corte): RedirectResponse
    {
        if ($corte->cerrado) {
            return back()->withErrors(['error' => 'No se puede eliminar un corte cerrado.']);
        }

        $corte->liquidaciones()->delete();
        $corte->pagosExtra()->delete();
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

            // Get all pagos extra for this corte grouped by grupo_trabajo_id
            $pagosExtraPorGrupo = PagoExtra::query()
                ->where('corte_id', $corte->id)
                ->get()
                ->groupBy('grupo_trabajo_id');

            // Collect all grupo_trabajo_ids from both registros and pagos extra
            $allGrupoIds = $porGrupo->keys()->merge($pagosExtraPorGrupo->keys())->unique();

            foreach ($allGrupoIds as $grupoTrabajoId) {
                $registrosGrupo = $porGrupo->get($grupoTrabajoId, collect());
                $pagosExtraGrupo = $pagosExtraPorGrupo->get($grupoTrabajoId, collect());

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

                // Calculate total extras
                $totalExtras = $pagosExtraGrupo->sum(fn ($pe) => $pe->precio * $pe->dias * $pe->personas);

                // Create liquidacion
                $liquidacion = Liquidacion::create([
                    'corte_id' => $corte->id,
                    'grupo_trabajo_id' => $grupoTrabajoId,
                    'total_kilos' => $totalKilos,
                    'total_produccion' => $totalProduccion,
                    'total_extras' => $totalExtras,
                    'total_final' => $totalProduccion + $totalExtras,
                    'generado_en' => now(),
                    'generado_por' => auth()->id(),
                ]);

                // Create detalles
                foreach ($detallesData as $detalle) {
                    $liquidacion->detalles()->create($detalle);
                }

                // Snapshot empleados
                $grupoTrabajo = $registrosGrupo->isNotEmpty()
                    ? $registrosGrupo->first()->grupoTrabajo
                    : \App\Models\Prod\GrupoTrabajo::with('empleados')->find($grupoTrabajoId);

                if ($grupoTrabajo) {
                    $totalFinal = $totalProduccion + $totalExtras;

                    foreach ($grupoTrabajo->empleados as $empleado) {
                        $montoAsignado = round($totalFinal * ($empleado->porcentaje / 100), 2);

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
}
