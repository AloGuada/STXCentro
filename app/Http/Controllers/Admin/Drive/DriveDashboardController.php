<?php

namespace App\Http\Controllers\Admin\Drive;

use App\Http\Controllers\Controller;
use App\Models\Drive\Archivo;
use App\Models\Drive\Carpeta;
use App\Models\Drive\Externo;
use Inertia\Inertia;
use Inertia\Response;

class DriveDashboardController extends Controller
{
    public function index(): Response
    {
        $this->authorize('drive.gestionar');

        $stats = [
            'total_carpetas' => Carpeta::count(),
            'total_archivos' => Archivo::count(),
            'total_externos' => Externo::count(),
            'espacio_usado' => Archivo::sum('size'),
        ];

        return Inertia::render('admin/drive/dashboard', [
            'stats' => $stats,
        ]);
    }
}
