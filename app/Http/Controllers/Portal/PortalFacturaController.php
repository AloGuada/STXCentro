<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\FacturaEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\PortalFacturaPreviewRequest;
use App\Models\Costos\ConfiguracionCostos;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Services\Costos\CfdiXmlParser;
use App\Services\Costos\RegistradorFacturaCfdi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PortalFacturaController extends Controller
{
    private const SESSION_KEY = 'portal.factura.preview';

    public function __construct(
        private readonly CfdiXmlParser $cfdiParser,
        private readonly RegistradorFacturaCfdi $registrador,
    ) {}

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
        $proveedor = Auth::guard('proveedor')->user();
        $validated = $request->validated();

        // La factura puede subirse cualquier día y sin recepción previa: las
        // entregas y el comprobante de recepción se ligan después (la factura
        // arranca en pendiente_recepcion y solo avanza al cumplirse ambos).
        $oc = OrdenCompra::with('facturas')->findOrFail($validated['orden_compra_id']);
        abort_if($oc->proveedor_id !== $proveedor->id, 403);

        try {
            $fiscal = $this->cfdiParser->parse(
                file_get_contents($request->file('xml')->getRealPath()) ?: ''
            );
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['xml' => $e->getMessage()]);
        }

        if ($error = $this->registrador->validar($oc, $fiscal)) {
            return back()->withInput()->withErrors(['xml' => $error]);
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

        // Re-valida saldo y UUID (puede haber otra factura entre paso 1 y 2)
        if ($error = $this->registrador->validar($oc, $fiscal)) {
            $this->limpiarPreview();

            return redirect()->route('portal.ordenes-compra.show', $oc)
                ->withErrors(['xml' => $error]);
        }

        $factura = $this->registrador->registrar($oc, $fiscal, [
            'estatus' => FacturaEstatus::PendienteRecepcion,
            'notas' => $validated['notas'] ?? null,
        ], function (Factura $factura) use ($request, $preview) {
            $this->registrador->adjuntarDesdeTemporal(
                $factura, $preview['xml_path'], $preview['xml_original'],
                DocumentoTipo::XmlFactura, 'cfdi.xml', 'application/xml',
            );

            // PDF: usa el del paso 2 si se agregó/reemplazó; si no, el del preview.
            if ($pdfFile = $request->file('pdf')) {
                if (! empty($preview['pdf_path'])) {
                    Storage::disk('public')->delete($preview['pdf_path']);
                }
                $this->registrador->adjuntarArchivo($factura, $pdfFile, DocumentoTipo::PdfFactura, 'cfdi.pdf');
            } elseif (! empty($preview['pdf_path'])) {
                $this->registrador->adjuntarDesdeTemporal(
                    $factura, $preview['pdf_path'], $preview['pdf_original'],
                    DocumentoTipo::PdfFactura, 'cfdi.pdf', 'application/pdf',
                );
            }
        });

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

    /**
     * El proveedor adjunta el comprobante de recepción (acuse sellado por
     * almacén) a su factura. Solo se permite el día configurado (o cualquier día
     * si es null) y mientras la factura siga pendiente de recepción. Al subirlo,
     * intenta pasar la factura a aprobación (requiere además estar completamente
     * entregada).
     */
    public function subirComprobante(Request $request, Factura $factura): RedirectResponse
    {
        $proveedor = Auth::guard('proveedor')->user();
        abort_if($factura->proveedor_id !== $proveedor->id, 403);

        if ($factura->estatus !== FacturaEstatus::PendienteRecepcion) {
            return back()->withErrors(['comprobante' => 'La factura ya no admite comprobante de recepción.']);
        }

        if (! ConfiguracionCostos::actual()->comprobanteHoyPermitido()) {
            return back()->withErrors(['comprobante' => 'Hoy no es el día permitido para subir el comprobante de recepción.']);
        }

        $request->validate([
            'comprobante' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $anterior = $factura->mediaComprobanteRecepcion;
        $file = $request->file('comprobante');
        $dest = $file->store("facturas/{$proveedor->id}/{$factura->id}/comprobante", 'public');

        DB::transaction(function () use ($factura, $anterior, $file, $dest) {
            // Reemplaza el comprobante anterior si existía.
            $anterior?->delete();

            $factura->media()->create([
                'descripcion' => DocumentoTipo::ComprobanteRecepcion->value,
                'nombre_original' => $file->getClientOriginalName(),
                'path' => $dest,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);

            $factura->refresh()->intentarPasarAAprobacion();
        });

        // El archivo físico anterior se borra tras el commit (si la transacción
        // fallara, el registro y su archivo seguirían consistentes).
        if ($anterior) {
            Storage::disk('public')->delete($anterior->path);
        }

        return back()->with('success', 'Comprobante de recepción subido correctamente.');
    }

    public function show(Factura $factura): Response
    {
        $proveedor = Auth::guard('proveedor')->user();

        abort_if($factura->proveedor_id !== $proveedor->id, 403);

        $factura->load([
            'ordenCompra:id,folio',
            'entregas.recibidoPor:id,name',
            'entregasLigadas.recibidoPor:id,name',
            'mediaComprobanteRecepcion',
            'notasCredito' => fn ($q) => $q->latest(),
            'pago',
        ]);

        $factura->append(['monto_notas_credito', 'monto_anticipos', 'saldo_facturado']);

        $config = ConfiguracionCostos::actual();

        return Inertia::render('portal/facturas/show', [
            'factura' => $factura,
            'comprobante' => [
                'permitido_hoy' => $config->comprobanteHoyPermitido(),
                'dia' => $config->dia_comprobante_recepcion,
                'subido' => $factura->tieneComprobanteRecepcion(),
            ],
        ]);
    }
}
