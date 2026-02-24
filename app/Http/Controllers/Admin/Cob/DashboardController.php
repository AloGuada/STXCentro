<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Models\Obra;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $obras = Obra::query()
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

        return Inertia::render('admin/cob/dashboard/index', [
            'obras' => $obras,
        ]);
    }
}
