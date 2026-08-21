<?php

namespace App\Http\Controllers\Admin\Drive;

use App\Http\Controllers\Controller;
use App\Models\Drive\Archivo;
use App\Models\Drive\Carpeta;
use App\Models\Drive\Externo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DriveDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Carpeta::class);

        $usuario = $request->user();
        $esAdmin = $usuario->can('drive.gestionar');

        $carpetaIds = Carpeta::query()->visiblesPara($usuario)->pluck('id');
        $archivos = Archivo::query()->whereIn('carpeta_id', $carpetaIds);

        $stats = [
            'total_carpetas' => $carpetaIds->count(),
            'total_archivos' => (clone $archivos)->count(),
            'total_externos' => $esAdmin ? Externo::count() : 0,
            'espacio_usado' => (int) (clone $archivos)->sum('size'),
        ];

        return Inertia::render('admin/drive/dashboard', [
            'stats' => $stats,
            'esAdmin' => $esAdmin,
        ]);
    }
}
