<?php

namespace App\Http\Controllers\Admin\Dg;

use App\Http\Controllers\Controller;
use App\Models\Dg\Carpeta;
use App\Models\Dg\Reporte;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MisReportesController extends Controller
{
    public function index(): Response
    {
        $usuario = request()->user();

        if (! $usuario) {
            throw new AccessDeniedHttpException;
        }

        $carpetas = Carpeta::query()
            ->whereHas('usuarios', fn ($q) => $q->where('usuario_id', $usuario->getKey())->where('puede_escribir', true))
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        if ($carpetas->isEmpty() && ! $usuario->can('dg.reportes.administrar')) {
            throw new AccessDeniedHttpException('No tienes carpetas con permiso de escritura.');
        }

        $reportes = Reporte::query()
            ->whereIn('carpeta_id', $carpetas->pluck('id'))
            ->with([
                'carpeta:id,nombre',
                'archivos' => fn ($q) => $q->orderByDesc('created_at'),
                'archivos.subidoPor:id,name',
            ])
            ->orderByDesc('anio')
            ->orderByDesc('semana')
            ->paginate(15);

        $hoy = CarbonImmutable::now();

        return Inertia::render('admin/dg/mis-reportes', [
            'reportes' => $reportes,
            'carpetas' => $carpetas,
            'semana_actual' => [
                'anio' => $hoy->isoWeekYear,
                'semana' => $hoy->isoWeek,
            ],
        ]);
    }
}
