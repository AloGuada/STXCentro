<?php

namespace App\Http\Controllers\Drive;

use App\Http\Controllers\Controller;
use App\Models\Drive\Archivo;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DriveDashboardController extends Controller
{
    public function index(): Response
    {
        $externo = Auth::guard('externo')->user();

        $carpetas = $externo->carpetas()
            ->withCount('archivos')
            ->withSum('archivos', 'size')
            ->get();

        $carpetaIds = $carpetas->pluck('id');

        $archivosRecientes = Archivo::query()
            ->whereIn('carpeta_id', $carpetaIds)
            ->with('carpeta:id,nombre')
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('drive/dashboard', [
            'carpetas' => $carpetas,
            'archivosRecientes' => $archivosRecientes,
        ]);
    }
}
