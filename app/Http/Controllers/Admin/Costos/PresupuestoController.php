<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\PlantaStoreRequest;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Rubro;
use App\Models\Costos\TipoRubro;
use App\Models\Obra;
use App\Support\OrdenaColumnas;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PresupuestoController extends Controller
{
    use OrdenaColumnas;

    public function index(Request $request): Response
    {
        $umbral = (int) config('costos.umbral_alerta_porcentaje', 90);

        $query = Obra::query()
            ->sinPlanta()
            ->withSum('obraRubros', 'presupuestado')
            ->withSum('obraRubros', 'acumulado')
            ->withCount('obraRubros')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('no', 'like', "%{$s}%")
                ->orWhere('descripcion', 'like', "%{$s}%")
            ));

        $orden = $this->aplicarOrden($query, $request, [
            'no' => 'no',
            'descripcion' => 'descripcion',
            'estatus' => 'estatus',
            'obra_rubros_count' => 'obra_rubros_count',
            'obra_rubros_sum_presupuestado' => 'obra_rubros_sum_presupuestado',
            'obra_rubros_sum_acumulado' => 'obra_rubros_sum_acumulado',
        ], 'created_at', 'desc');

        $obras = $query->paginate(15)->withQueryString();

        $planta = Obra::query()
            ->where('es_planta', true)
            ->withSum('obraRubros', 'presupuestado')
            ->withSum('obraRubros', 'acumulado')
            ->withCount('obraRubros')
            ->first();

        // Calcular en PHP en lugar de SQL para evitar quirks de SQLite con
        // division decimal. El COUNT total no es enorme (rubros por obra
        // suelen ser <100) así que es aceptable. Stats de obras y planta
        // se reportan por separado.
        $obraRubros = ObraRubro::query()
            ->select('id', 'obra_id', 'presupuestado', 'acumulado')
            ->get();

        $statsObras = $this->calcularStats(
            $planta ? $obraRubros->where('obra_id', '!=', $planta->id) : $obraRubros,
            $umbral,
        );
        $statsPlanta = $planta
            ? $this->calcularStats($obraRubros->where('obra_id', $planta->id), $umbral)
            : null;

        return Inertia::render('admin/costos/presupuestos/index', [
            'obras' => $obras,
            'planta' => $planta,
            'statsPlanta' => $statsPlanta,
            'filters' => $request->only('search'),
            'stats' => [
                ...$statsObras,
                'umbral_alerta' => $umbral,
                'bloquear_sobregiro' => (bool) config('costos.bloquear_sobregiro', false),
            ],
            'sortBy' => $orden['by'],
            'sortDir' => $orden['dir'],
        ]);
    }

    public function storePlanta(PlantaStoreRequest $request): RedirectResponse
    {
        $planta = Obra::create([
            'no' => 'PLANTA',
            'descripcion' => $request->validated('descripcion'),
            'estatus' => 'abierta',
            'activa' => true,
            'es_planta' => true,
        ]);

        return to_route('admin.costos.presupuestos.edit', $planta)
            ->with('success', 'Proyecto de planta creado.');
    }

    /**
     * @param  Collection<int, ObraRubro>  $obraRubros
     * @return array{total_rubros: int, sobregiros: int, criticos: int, total_presupuestado: float, total_acumulado: float}
     */
    private function calcularStats(Collection $obraRubros, int $umbral): array
    {
        $sobregiros = 0;
        $criticos = 0;
        $totalPresupuestado = 0.0;
        $totalAcumulado = 0.0;
        $umbralPct = (float) $umbral;

        foreach ($obraRubros as $r) {
            $presup = (float) $r->presupuestado;
            $acum = (float) $r->acumulado;
            $totalPresupuestado += $presup;
            $totalAcumulado += $acum;

            if ($presup <= 0.0) {
                if ($acum > 0.0) {
                    $sobregiros++;
                }

                continue;
            }

            $pct = ($acum / $presup) * 100.0;
            if ($pct > 100.0) {
                $sobregiros++;
            } elseif ($pct >= $umbralPct) {
                $criticos++;
            }
        }

        return [
            'total_rubros' => $obraRubros->count(),
            'sobregiros' => $sobregiros,
            'criticos' => $criticos,
            'total_presupuestado' => $totalPresupuestado,
            'total_acumulado' => $totalAcumulado,
        ];
    }

    public function edit(Obra $obra): Response
    {
        $obra->load([
            'obraRubros.rubro.tipoRubro',
        ]);

        $rubros = Rubro::query()
            ->where('ambito', $obra->es_planta ? 'planta' : 'obra')
            ->with('tipoRubro')
            ->orderBy('codigo')
            ->get();

        return Inertia::render('admin/costos/presupuestos/edit', [
            'obra' => $obra,
            'rubros' => $rubros,
        ]);
    }

    public function generarReportePdf(): HttpResponse
    {
        $tipos = TipoRubro::with(['rubros' => fn ($q) => $q->where('ambito', 'obra')
            ->where('ocultar_en_reporte', false)
            ->orderBy('codigo')])
            ->orderBy('descripcion')
            ->get();

        $obras = Obra::sinPlanta()
            ->with('obraRubros')
            ->orderBy('no')
            ->get();

        $filas = $obras->map(function (Obra $obra) use ($tipos) {
            $obraRubrosPorRubro = $obra->obraRubros->keyBy('rubro_id');

            $ingresosPresup = (float) $obra->presupuesto_total;
            $ingresosReal = (float) ($obra->ingreso_real ?? 0);
            $ingresosDif = $ingresosPresup - $ingresosReal;

            $totalPresup = 0.0;
            $totalReal = 0.0;

            $tiposData = $tipos->map(function (TipoRubro $tipo) use ($obraRubrosPorRubro, &$totalPresup, &$totalReal) {
                $rubrosData = $tipo->rubros->map(function (Rubro $rubro) use ($obraRubrosPorRubro) {
                    $or = $obraRubrosPorRubro->get($rubro->id);
                    $presup = $or ? (float) $or->presupuestado : 0.0;
                    $real = $or ? (float) $or->acumulado : 0.0;

                    return [
                        'rubro' => $rubro,
                        'presup' => $presup,
                        'real' => $real,
                        'dif' => $presup - $real,
                    ];
                });

                $tipoPresup = $rubrosData->sum('presup');
                $tipoReal = $rubrosData->sum('real');
                $totalPresup += $tipoPresup;
                $totalReal += $tipoReal;

                return [
                    'tipo' => $tipo,
                    'rubros' => $rubrosData,
                    'total_presup' => $tipoPresup,
                    'total_real' => $tipoReal,
                    'total_dif' => $tipoPresup - $tipoReal,
                ];
            });

            // Calcular % por tipo sobre el total_real de la obra
            $tiposData = $tiposData->map(function ($td) use ($totalReal) {
                $td['porcentaje'] = $totalReal > 0 ? ($td['total_real'] / $totalReal) * 100 : 0;

                return $td;
            });

            $totalDif = $totalPresup - $totalReal;
            $utilidad = $ingresosReal - $totalReal;
            $utilVtsPct = $ingresosReal > 0 ? ($utilidad / $ingresosReal) * 100 : 0;
            $utilidadPct = $utilVtsPct - 5.5;

            return [
                'obra' => $obra,
                'ingresos_presup' => $ingresosPresup,
                'ingresos_real' => $ingresosReal,
                'ingresos_dif' => $ingresosDif,
                'tipos' => $tiposData,
                'total_presup' => $totalPresup,
                'total_real' => $totalReal,
                'total_dif' => $totalDif,
                'total_pct' => $totalReal > 0 ? 100 : 0,
                'utilidad' => $utilidad,
                'util_vts_pct' => $utilVtsPct,
                'utilidad_pct' => $utilidadPct,
            ];
        });

        $pdf = Pdf::loadView('pdf.costos.reporte-presupuestos', [
            'tipos' => $tipos,
            'filas' => $filas,
            'fechaGeneracion' => now(),
        ])->setPaper('tabloid', 'landscape')
            ->setOption('margin-top', 20)
            ->setOption('margin-bottom', 20)
            ->setOption('margin-left', 20)
            ->setOption('margin-right', 20);

        return $pdf->download('reporte-presupuestos-'.now()->format('Y-m-d').'.pdf');
    }
}
