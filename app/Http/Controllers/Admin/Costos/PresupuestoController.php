<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Models\Costos\Rubro;
use App\Models\Costos\TipoRubro;
use App\Models\Obra;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PresupuestoController extends Controller
{
    public function index(Request $request): Response
    {
        $obras = Obra::query()
            ->withSum('obraRubros', 'presupuestado')
            ->withSum('obraRubros', 'acumulado')
            ->withCount('obraRubros')
            ->when($request->search, fn ($q, $s) => $q->where('no', 'like', "%{$s}%")
                ->orWhere('descripcion', 'like', "%{$s}%")
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/costos/presupuestos/index', [
            'obras' => $obras,
            'filters' => $request->only('search'),
        ]);
    }

    public function edit(Obra $obra): Response
    {
        $obra->load(['obraRubros.rubro.tipoRubro']);

        $rubros = Rubro::with('tipoRubro')->orderBy('codigo')->get();

        return Inertia::render('admin/costos/presupuestos/edit', [
            'obra' => $obra,
            'rubros' => $rubros,
        ]);
    }

    public function generarReportePdf(): HttpResponse
    {
        $tipos = TipoRubro::with(['rubros' => fn ($q) => $q->orderBy('codigo')])
            ->orderBy('descripcion')
            ->get();

        $obras = Obra::with('obraRubros')
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
