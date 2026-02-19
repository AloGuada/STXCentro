<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Costos\Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PortalPagoController extends Controller
{
    public function index(Request $request): Response
    {
        $proveedor = Auth::guard('proveedor')->user();

        $pagos = Pago::query()
            ->whereHasMorph('pagable', ['App\Models\Costos\Factura'], function ($query) use ($proveedor) {
                $query->where('proveedor_id', $proveedor->id);
            })
            ->with('pagable')
            ->when($request->search, function ($query, $search) {
                $query->where('folio', 'like', "%{$search}%");
            })
            ->when($request->estatus, fn ($q, $e) => $q->where('estatus', $e))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('portal/pagos/index', [
            'pagos' => $pagos,
            'filters' => $request->only('search', 'estatus'),
        ]);
    }

    public function show(Pago $pago): Response
    {
        $proveedor = Auth::guard('proveedor')->user();

        // Verify pago belongs to proveedor's factura
        $pago->load('pagable');
        if ($pago->pagable_type === 'App\Models\Costos\Factura') {
            abort_if($pago->pagable->proveedor_id !== $proveedor->id, 403);
        } else {
            abort(403);
        }

        $pago->load('pagosParciales');

        return Inertia::render('portal/pagos/show', [
            'pago' => $pago,
        ]);
    }
}
