<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\FacturaEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\PortalFacturaStoreRequest;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Services\Costos\CfdiXmlParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PortalFacturaController extends Controller
{
    public function __construct(private readonly CfdiXmlParser $cfdiParser) {}

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

        $fiscal = [];
        if ($request->hasFile('xml')) {
            try {
                $fiscal = $this->cfdiParser->parse(
                    file_get_contents($request->file('xml')->getRealPath()) ?: ''
                );
            } catch (RuntimeException $e) {
                return back()
                    ->withInput()
                    ->withErrors(['xml' => $e->getMessage()]);
            }

            if (! empty($fiscal['uuid_fiscal'])
                && Factura::where('uuid_fiscal', $fiscal['uuid_fiscal'])->exists()) {
                return back()
                    ->withInput()
                    ->withErrors(['xml' => 'Ya existe una factura registrada con el UUID fiscal del XML.']);
            }
        }

        $subtotal = $fiscal['subtotal'] ?? $validated['total'];
        $total = $fiscal['total'] ?? $validated['total'];
        $ivaTrasladado = $fiscal['iva_trasladado'] ?? 0;

        $factura = Factura::create([
            'orden_compra_id' => $oc->id,
            'proveedor_id' => $proveedor->id,
            'uuid_fiscal' => $fiscal['uuid_fiscal'] ?? ($validated['uuid_fiscal'] ?? null),
            'folio_fiscal' => $fiscal['folio_fiscal'] ?? ($validated['folio_fiscal'] ?? null),
            'subtotal' => $subtotal,
            'iva' => $ivaTrasladado,
            'iva_trasladado' => $ivaTrasladado,
            'iva_retenido' => $fiscal['iva_retenido'] ?? 0,
            'isr_retenido' => $fiscal['isr_retenido'] ?? 0,
            'impuestos_detalle' => $fiscal['impuestos_detalle'] ?? null,
            'total' => $total,
            'moneda' => $oc->moneda,
            'fecha_factura' => $fiscal['fecha_factura'] ?? ($validated['fecha_factura'] ?? null),
            'estatus' => FacturaEstatus::PendienteAprobacion,
            'notas' => $validated['notas'] ?? null,
        ]);

        if ($request->hasFile('xml')) {
            $file = $request->file('xml');
            $factura->media()->create([
                'descripcion' => DocumentoTipo::XmlFactura->value,
                'nombre_original' => $file->getClientOriginalName(),
                'path' => $file->store("facturas/{$proveedor->id}", 'public'),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        if ($request->hasFile('pdf')) {
            $file = $request->file('pdf');
            $factura->media()->create([
                'descripcion' => DocumentoTipo::PdfFactura->value,
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
            'notasCredito' => fn ($q) => $q->latest(),
            'pago',
        ]);

        $factura->append(['monto_notas_credito', 'monto_anticipos', 'saldo_facturado']);

        return Inertia::render('portal/facturas/show', [
            'factura' => $factura,
        ]);
    }
}
