<?php

namespace App\Http\Controllers\Admin\Dg;

use App\Http\Controllers\Controller;
use App\Models\Dg\Carpeta;
use App\Models\Dg\Reporte;
use Inertia\Inertia;
use Inertia\Response;

class MisReportesController extends Controller
{
    public function index(): Response
    {
        $this->authorize('dg.reportes.subir');

        $usuario = request()->user();

        $carpetas = Carpeta::query()
            ->whereHas('usuarios', fn ($q) => $q->where('usuario_id', $usuario?->getKey())->where('puede_escribir', true))
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

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

        return Inertia::render('admin/dg/mis-reportes', [
            'reportes' => $reportes,
            'carpetas' => $carpetas,
        ]);
    }
}
