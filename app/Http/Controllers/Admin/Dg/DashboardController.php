<?php

namespace App\Http\Controllers\Admin\Dg;

use App\Http\Controllers\Controller;
use App\Models\Dg\Carpeta;
use App\Models\Dg\Reporte;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $this->authorize('dg.reportes.ver');

        $hoy = CarbonImmutable::now();
        $anioActual = $hoy->isoWeekYear;
        $semanaActual = $hoy->isoWeek;

        $semanaAnteriorFecha = $hoy->subWeek();
        $anioAnterior = $semanaAnteriorFecha->isoWeekYear;
        $semanaAnterior = $semanaAnteriorFecha->isoWeek;

        $usuario = request()->user();
        $esAdmin = $usuario?->can('dg.reportes.administrar');

        $carpetasQuery = Carpeta::query()->orderBy('orden')->orderBy('nombre');
        if (! $esAdmin) {
            $carpetasQuery->whereHas('usuarios', fn ($q) => $q->where('usuario_id', $usuario?->getKey()));
        }

        $carpetas = $carpetasQuery->get()->map(function (Carpeta $carpeta) use ($anioActual, $semanaActual, $anioAnterior, $semanaAnterior, $usuario) {
            $actual = Reporte::query()
                ->where('carpeta_id', $carpeta->id)
                ->where('anio', $anioActual)
                ->where('semana', $semanaActual)
                ->withCount('archivos')
                ->first();

            $anterior = Reporte::query()
                ->where('carpeta_id', $carpeta->id)
                ->where('anio', $anioAnterior)
                ->where('semana', $semanaAnterior)
                ->withCount('archivos')
                ->first();

            $noLeidos = \App\Models\Dg\ReporteArchivo::query()
                ->whereNull('visto_por_dg_en')
                ->whereHas('reporte', fn ($q) => $q->where('carpeta_id', $carpeta->id))
                ->count();

            return [
                'id' => $carpeta->id,
                'nombre' => $carpeta->nombre,
                'descripcion' => $carpeta->descripcion,
                'puede_escribir' => $usuario ? $carpeta->usuarioPuedeEscribir($usuario) : false,
                'no_leidos' => $noLeidos,
                'semana_actual' => [
                    'subido' => $actual !== null && $actual->archivos_count > 0,
                    'archivos' => $actual?->archivos_count ?? 0,
                ],
                'semana_anterior' => [
                    'subido' => $anterior !== null && $anterior->archivos_count > 0,
                    'archivos' => $anterior?->archivos_count ?? 0,
                ],
            ];
        });

        return Inertia::render('admin/dg/dashboard', [
            'carpetas' => $carpetas,
            'periodo' => [
                'anio_actual' => $anioActual,
                'semana_actual' => $semanaActual,
                'anio_anterior' => $anioAnterior,
                'semana_anterior' => $semanaAnterior,
            ],
        ]);
    }
}
