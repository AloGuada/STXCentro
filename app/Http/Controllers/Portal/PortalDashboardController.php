<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PortalDashboardController extends Controller
{
    public function index(): Response
    {
        $proveedor = Auth::guard('proveedor')->user();

        $ordenesCompra = OrdenCompra::query()
            ->where('proveedor_id', $proveedor->id)
            ->whereIn('estatus', ['pendiente_entrega', 'pendiente_factura', 'pendiente_aprobacion', 'pendiente_pago', 'pagada'])
            ->withCount('facturas')
            ->with('obra:id,no,descripcion')
            ->latest()
            ->limit(10)
            ->get();

        $stats = [
            'ordenes_activas' => OrdenCompra::where('proveedor_id', $proveedor->id)
                ->whereIn('estatus', ['pendiente_entrega', 'pendiente_factura', 'pendiente_aprobacion', 'pendiente_pago'])
                ->count(),
            'facturas_pendientes' => Factura::where('proveedor_id', $proveedor->id)
                ->whereIn('estatus', ['pendiente_aprobacion', 'pendiente_pago'])
                ->count(),
            'total_facturado' => Factura::where('proveedor_id', $proveedor->id)
                ->whereNotIn('estatus', ['cancelada'])
                ->sum('total'),
        ];

        return Inertia::render('portal/dashboard', [
            'ordenesCompra' => $ordenesCompra,
            'stats' => $stats,
        ]);
    }
}
