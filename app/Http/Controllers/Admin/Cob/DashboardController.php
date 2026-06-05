<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Models\Cob\Disputa;
use App\Models\Cob\Retencion;
use App\Models\Obra;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $obras = Obra::query()
            ->sinPlanta()
            ->with([
                'cliente',
                'partidas',
                'estimaciones.pagos',
                'anticipos',
                'comparativos',
                'deducciones',
            ])
            ->whereNotNull('cliente_id')
            ->where('activa', true)
            ->orderBy('no')
            ->get();

        $driver = DB::connection()->getDriverName();

        $dateDiffExpr = match ($driver) {
            'pgsql' => 'AVG(EXTRACT(EPOCH FROM (cob_estimaciones_pagos.fecha_pago::timestamp - cob_estimaciones.fecha_emision::timestamp)) / 86400)',
            'sqlite' => 'AVG(JULIANDAY(cob_estimaciones_pagos.fecha_pago) - JULIANDAY(cob_estimaciones.fecha_emision))',
            default => 'AVG(DATEDIFF(cob_estimaciones_pagos.fecha_pago, cob_estimaciones.fecha_emision))',
        };

        $dsoPorObra = DB::table('cob_estimaciones')
            ->join('cob_estimaciones_pagos', 'cob_estimaciones.id', '=', 'cob_estimaciones_pagos.estimacion_id')
            ->join('obras', 'obras.id', '=', 'cob_estimaciones.obra_id')
            ->whereNotNull('cob_estimaciones.fecha_emision')
            ->select([
                'cob_estimaciones.obra_id',
                'obras.no as obra_no',
                DB::raw("ROUND($dateDiffExpr, 0) as dias_promedio"),
            ])
            ->groupBy('cob_estimaciones.obra_id', 'obras.no')
            ->orderBy('dias_promedio', 'desc')
            ->get();

        $retencionesPorTipo = Retencion::query()
            ->join('cob_tipos_retenciones', 'cob_tipos_retenciones.id', '=', 'cob_retenciones.tipo_retencion_id')
            ->select([
                'cob_tipos_retenciones.nombre as tipo',
                DB::raw('SUM(cob_retenciones.monto) as monto'),
            ])
            ->groupBy('cob_tipos_retenciones.nombre')
            ->get();

        $disputas = Disputa::query()
            ->join('obras', 'obras.id', '=', 'cob_disputas.obra_id')
            ->select([
                'cob_disputas.id',
                'obras.no as obra_no',
                'obras.descripcion as obra_descripcion',
                'cob_disputas.descripcion',
                'cob_disputas.fecha_inicio',
                'cob_disputas.fecha_resolucion',
                'cob_disputas.estado',
            ])
            ->orderByDesc('cob_disputas.created_at')
            ->get()
            ->map(function ($d) {
                $d->dias_abierta = $d->fecha_inicio
                    ? (int) Carbon::parse($d->fecha_inicio)->diffInDays($d->fecha_resolucion ? Carbon::parse($d->fecha_resolucion) : now())
                    : null;

                return $d;
            });

        return Inertia::render('admin/cob/dashboard/index', [
            'obras' => $obras,
            'dsoPorObra' => $dsoPorObra,
            'retencionesPorTipo' => $retencionesPorTipo,
            'disputas' => $disputas,
        ]);
    }
}
