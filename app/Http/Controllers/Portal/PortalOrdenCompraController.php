<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Costos\OrdenCompra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PortalOrdenCompraController extends Controller
{
    public function index(Request $request): Response
    {
        $proveedor = Auth::guard('proveedor')->user();

        $ordenes = OrdenCompra::query()
            ->where('proveedor_id', $proveedor->id)
            ->whereIn('estatus', ['pendiente_entrega', 'pendiente_factura', 'pendiente_aprobacion', 'pendiente_pago', 'pagada'])
            ->with([
                'obra:id,no,descripcion',
                'proveedor:id,razon_social,nombre_comercial',
                'detalles:id,orden_compra_id,precio_unitario,cantidad,cantidad_cancelada',
                'entregas.detalles.devoluciones',
                'facturas.pago',
            ])
            ->withCount(['facturas', 'entregas' => fn ($q) => $q->activa(), 'detalles'])
            ->when($request->search, function ($query, $search) {
                $query->where('folio', 'like', "%{$search}%");
            })
            ->when($request->estatus, fn ($q, $e) => $q->where('estatus', $e))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('portal/ordenes-compra/index', [
            'ordenes' => $ordenes,
            'filters' => $request->only('search', 'estatus'),
        ]);
    }

    public function show(OrdenCompra $ordenCompra): Response
    {
        $proveedor = Auth::guard('proveedor')->user();

        abort_if($ordenCompra->proveedor_id !== $proveedor->id, 403);

        $ordenCompra->load([
            'obra:id,no,descripcion',
            'departamento:id,descripcion',
            'detalles',
            'facturas',
            'entregas',
        ]);

        $ordenCompra->append('saldo_facturable');

        // La factura puede subirse cualquier día y sin recepción previa: basta
        // con que la OC no esté cancelada y tenga saldo por facturar.
        $puedeFacturar = $ordenCompra->estatus->value !== 'cancelada'
            && $ordenCompra->saldo_facturable > (float) config('costos.epsilon_monto');

        return Inertia::render('portal/ordenes-compra/show', [
            'ordenCompra' => $ordenCompra,
            'puedeFacturar' => $puedeFacturar,
        ]);
    }
}
