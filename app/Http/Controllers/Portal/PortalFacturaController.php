<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\FacturaEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\PortalFacturaPreviewRequest;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Services\Costos\CfdiXmlParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PortalFacturaController extends Controller
{
    private const SESSION_KEY = 'portal.factura.preview';

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

    /**
     * Paso 1: recibe XML + PDF opcional + notas, parsea, valida y guarda
     * archivos en storage temporal + datos en session. Redirige al paso 2.
     */
    public function previewXml(PortalFacturaPreviewRequest $request): RedirectResponse
    {
        if (! now()->isDayOfWeek(\Carbon\Carbon::THURSDAY)) {
            return back()
                ->withErrors(['xml' => 'La carga de facturas solo está permitida los días jueves.']);
        }

        $proveedor = Auth::guard('proveedor')->user();
        $validated = $request->validated();

        $oc = OrdenCompra::with('facturas')->findOrFail($validated['orden_compra_id']);
        abort_if($oc->proveedor_id !== $proveedor->id, 403);

        if (! $oc->entregas()->exists()) {
            return back()
                ->withErrors(['orden_compra_id' => 'Esta orden de compra aún no tiene recepción de almacén. No es posible facturar.']);
        }

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

        $saldoFacturable = (float) $oc->total - (float) $oc->total_facturado;
        $totalCfdi = (float) ($fiscal['total'] ?? 0);
        if ($totalCfdi > $saldoFacturable + 0.01) {
            return back()
                ->withErrors(['xml' => sprintf(
                    'El monto del CFDI ($%s) excede el saldo facturable de la OC ($%s).',
                    number_format($totalCfdi, 2),
                    number_format($saldoFacturable, 2),
                )]);
        }

        // Limpia preview anterior si existía
        $this->limpiarPreview();

        $token = (string) Str::uuid();
        $tmpDir = "tmp_facturas/{$proveedor->id}/{$token}";

        $xmlFile = $request->file('xml');
        $xmlPath = $xmlFile->storeAs($tmpDir, 'cfdi.xml', 'public');

        $pdfPath = null;
        $pdfOriginal = null;
        if ($request->hasFile('pdf')) {
            $pdfFile = $request->file('pdf');
            $pdfPath = $pdfFile->storeAs($tmpDir, 'cfdi.pdf', 'public');
            $pdfOriginal = $pdfFile->getClientOriginalName();
        }

        $request->session()->put(self::SESSION_KEY, [
            'token' => $token,
            'orden_compra_id' => $oc->id,
            'fiscal' => $fiscal,
            'xml_path' => $xmlPath,
            'xml_original' => $xmlFile->getClientOriginalName(),
            'pdf_path' => $pdfPath,
            'pdf_original' => $pdfOriginal,
            'notas' => $validated['notas'] ?? null,
        ]);

        return redirect()->route('portal.facturas.preview');
    }

    /**
     * Paso 2: render del preview con los datos del CFDI parseado.
     */
    public function preview(Request $request): Response|RedirectResponse
    {
        $preview = $request->session()->get(self::SESSION_KEY);
        if (! $preview) {
            return redirect()->route('portal.ordenes-compra.index');
        }

        $proveedor = Auth::guard('proveedor')->user();
        $oc = OrdenCompra::findOrFail($preview['orden_compra_id']);
        abort_if($oc->proveedor_id !== $proveedor->id, 403);

        return Inertia::render('portal/facturas/preview', [
            'ordenCompra' => $oc->only(['id', 'folio', 'total', 'moneda']),
            'fiscal' => $preview['fiscal'],
            'archivos' => [
                'xml_original' => $preview['xml_original'],
                'pdf_original' => $preview['pdf_original'],
            ],
            'notas' => $preview['notas'],
        ]);
    }

    /**
     * Paso 2 — confirmar: crea la Factura con los datos del preview,
     * mueve los archivos a la carpeta definitiva y limpia la session.
     */
    public function store(Request $request): RedirectResponse
    {
        $preview = $request->session()->get(self::SESSION_KEY);
        if (! $preview) {
            return redirect()->route('portal.ordenes-compra.index')
                ->withErrors(['preview' => 'No hay una factura en preview. Vuelva a subir el CFDI.']);
        }

        $validated = $request->validate([
            'notas' => ['nullable', 'string'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $proveedor = Auth::guard('proveedor')->user();
        $oc = OrdenCompra::findOrFail($preview['orden_compra_id']);
        abort_if($oc->proveedor_id !== $proveedor->id, 403);

        $fiscal = $preview['fiscal'];

        // Re-valida saldo (puede haber otra factura aprobada entre paso 1 y 2)
        $saldoFacturable = (float) $oc->total - (float) $oc->total_facturado;
        $totalCfdi = (float) ($fiscal['total'] ?? 0);
        if ($totalCfdi > $saldoFacturable + 0.01) {
            $this->limpiarPreview();

            return redirect()->route('portal.ordenes-compra.show', $oc)
                ->withErrors(['xml' => sprintf(
                    'El monto del CFDI ($%s) ya excede el saldo facturable disponible ($%s).',
                    number_format($totalCfdi, 2),
                    number_format($saldoFacturable, 2),
                )]);
        }

        // Re-valida UUID único
        if (! empty($fiscal['uuid_fiscal'])
            && Factura::where('uuid_fiscal', $fiscal['uuid_fiscal'])->exists()) {
            $this->limpiarPreview();

            return redirect()->route('portal.ordenes-compra.show', $oc)
                ->withErrors(['xml' => 'Ya existe una factura registrada con ese UUID fiscal.']);
        }

        $factura = Factura::create([
            'orden_compra_id' => $oc->id,
            'proveedor_id' => $proveedor->id,
            'uuid_fiscal' => $fiscal['uuid_fiscal'] ?? null,
            'folio_fiscal' => $fiscal['folio_fiscal'] ?? null,
            'subtotal' => $fiscal['subtotal'] ?? 0,
            'iva' => $fiscal['iva_trasladado'] ?? 0,
            'iva_trasladado' => $fiscal['iva_trasladado'] ?? 0,
            'iva_retenido' => $fiscal['iva_retenido'] ?? 0,
            'isr_retenido' => $fiscal['isr_retenido'] ?? 0,
            'impuestos_detalle' => $fiscal['impuestos_detalle'] ?? null,
            'total' => $fiscal['total'] ?? 0,
            'moneda' => $oc->moneda,
            'fecha_factura' => $fiscal['fecha_factura'] ?? null,
            'estatus' => FacturaEstatus::PendienteAprobacion,
            'notas' => $validated['notas'] ?? null,
        ]);

        // Mover XML temporal a definitivo
        $xmlDest = "facturas/{$proveedor->id}/{$factura->id}/cfdi.xml";
        Storage::disk('public')->move($preview['xml_path'], $xmlDest);
        $factura->media()->create([
            'descripcion' => DocumentoTipo::XmlFactura->value,
            'nombre_original' => $preview['xml_original'],
            'path' => $xmlDest,
            'mime' => 'application/xml',
            'size' => Storage::disk('public')->size($xmlDest),
        ]);

        // PDF: usa el del preview o el del paso 2 (puede haberse agregado/reemplazado)
        $pdfFile = $request->file('pdf');
        if ($pdfFile) {
            // Reemplaza/agrega: descarta el del preview si existía
            if (! empty($preview['pdf_path'])) {
                Storage::disk('public')->delete($preview['pdf_path']);
            }
            $pdfDest = $pdfFile->storeAs("facturas/{$proveedor->id}/{$factura->id}", 'cfdi.pdf', 'public');
            $factura->media()->create([
                'descripcion' => DocumentoTipo::PdfFactura->value,
                'nombre_original' => $pdfFile->getClientOriginalName(),
                'path' => $pdfDest,
                'mime' => $pdfFile->getMimeType(),
                'size' => $pdfFile->getSize(),
            ]);
        } elseif (! empty($preview['pdf_path'])) {
            $pdfDest = "facturas/{$proveedor->id}/{$factura->id}/cfdi.pdf";
            Storage::disk('public')->move($preview['pdf_path'], $pdfDest);
            $factura->media()->create([
                'descripcion' => DocumentoTipo::PdfFactura->value,
                'nombre_original' => $preview['pdf_original'],
                'path' => $pdfDest,
                'mime' => 'application/pdf',
                'size' => Storage::disk('public')->size($pdfDest),
            ]);
        }

        $oc->recalcularEstatus();

        $this->limpiarPreview();

        return redirect()
            ->route('portal.facturas.index')
            ->with('success', 'Factura subida correctamente.');
    }

    /**
     * Cancela el preview: elimina archivos temporales y limpia session.
     */
    public function cancelPreview(Request $request): RedirectResponse
    {
        $oc = null;
        $preview = $request->session()->get(self::SESSION_KEY);
        if ($preview) {
            $oc = OrdenCompra::find($preview['orden_compra_id']);
        }

        $this->limpiarPreview();

        if ($oc) {
            return redirect()->route('portal.ordenes-compra.show', $oc);
        }

        return redirect()->route('portal.ordenes-compra.index');
    }

    private function limpiarPreview(): void
    {
        $preview = session(self::SESSION_KEY);
        if (! $preview) {
            return;
        }

        foreach (['xml_path', 'pdf_path'] as $key) {
            if (! empty($preview[$key]) && Storage::disk('public')->exists($preview[$key])) {
                Storage::disk('public')->delete($preview[$key]);
            }
        }

        // Limpia el directorio temporal si quedó vacío
        if (! empty($preview['token'])) {
            $proveedor = Auth::guard('proveedor')->user();
            if ($proveedor) {
                $dir = "tmp_facturas/{$proveedor->id}/{$preview['token']}";
                if (Storage::disk('public')->exists($dir) && empty(Storage::disk('public')->files($dir))) {
                    Storage::disk('public')->deleteDirectory($dir);
                }
            }
        }

        session()->forget(self::SESSION_KEY);
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
