<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\PortalFacturaStoreRequest;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PortalFacturaController extends Controller
{
    public function index(Request $request): Response
    {
        $proveedor = Auth::guard('proveedor')->user();

        $facturas = Factura::query()
            ->where('proveedor_id', $proveedor->id)
            ->with(['ordenCompra:id,folio'])
            ->when($request->search, function ($query, $search) {
                $query->where('folio', 'like', "%{$search}%");
            })
            ->when($request->estatus, fn ($q, $e) => $q->where('estatus', $e))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('portal/facturas/index', [
            'facturas' => $facturas,
            'filters' => $request->only('search', 'estatus'),
        ]);
    }

    public function store(PortalFacturaStoreRequest $request): RedirectResponse
    {
        $proveedor = Auth::guard('proveedor')->user();
        $validated = $request->validated();

        $oc = OrdenCompra::findOrFail($validated['orden_compra_id']);

        abort_if($oc->proveedor_id !== $proveedor->id, 403);

        $factura = Factura::create([
            'orden_compra_id' => $oc->id,
            'proveedor_id' => $proveedor->id,
            'uuid_fiscal' => $validated['uuid_fiscal'] ?? null,
            'folio_fiscal' => $validated['folio_fiscal'] ?? null,
            'subtotal' => $validated['total'],
            'iva' => 0,
            'total' => $validated['total'],
            'moneda' => $oc->moneda,
            'fecha_factura' => $validated['fecha_factura'] ?? null,
            'notas' => $validated['notas'] ?? null,
        ]);

        if ($request->hasFile('xml')) {
            $file = $request->file('xml');
            $factura->media()->create([
                'descripcion' => 'xml',
                'nombre_original' => $file->getClientOriginalName(),
                'path' => $file->store("facturas/{$proveedor->id}", 'public'),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        if ($request->hasFile('pdf')) {
            $file = $request->file('pdf');
            $factura->media()->create([
                'descripcion' => 'pdf',
                'nombre_original' => $file->getClientOriginalName(),
                'path' => $file->store("facturas/{$proveedor->id}", 'public'),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        $oc->recalcularEstatus();

        return redirect()
            ->route('portal.facturas.index')
            ->with('success', 'Factura subida correctamente.');
    }

    public function show(Factura $factura): Response
    {
        $proveedor = Auth::guard('proveedor')->user();

        abort_if($factura->proveedor_id !== $proveedor->id, 403);

        $factura->load([
            'ordenCompra:id,folio',
            'entregas.recibidoPor:id,name',
            'pago',
        ]);

        return Inertia::render('portal/facturas/show', [
            'factura' => $factura,
        ]);
    }
}
