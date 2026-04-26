<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\NotaCreditoEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\CancelarRequest;
use App\Http\Requests\Admin\Costos\NotaCreditoStoreRequest;
use App\Models\Costos\Factura;
use App\Models\Costos\NotaCredito;
use App\Services\Costos\CfdiXmlParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class NotaCreditoController extends Controller
{
    public function __construct(private readonly CfdiXmlParser $cfdiParser) {}

    public function index(Request $request): Response
    {
        Gate::authorize('costos.notas-credito.ver');

        $notas = NotaCredito::query()
            ->with(['factura:id,folio,proveedor_id', 'factura.proveedor:id,razon_social', 'creador:id,name'])
            ->when($request->search, fn ($q, $s) => $q->where(function ($qq) use ($s) {
                $qq->where('folio', 'like', "%{$s}%")
                    ->orWhere('uuid_fiscal', 'like', "%{$s}%")
                    ->orWhere('concepto', 'like', "%{$s}%");
            }))
            ->when($request->estatus, fn ($q, $e) => $q->where('estatus', $e))
            ->when($request->factura_id, fn ($q, $f) => $q->where('factura_id', $f))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/costos/notas-credito/index', [
            'notas' => $notas,
            'filters' => $request->only('search', 'estatus', 'factura_id'),
        ]);
    }

    /**
     * Crea una nota de crédito ligada a una factura. Si se sube XML CFDI,
     * autocompleta uuid_fiscal/folio_fiscal/subtotal/monto/iva/impuestos
     * desde el XML (sobreescribiendo lo capturado por el usuario).
     */
    public function store(NotaCreditoStoreRequest $request): RedirectResponse
    {
        $factura = Factura::findOrFail($request->integer('factura_id'));

        if (in_array($factura->estatus->value, ['cancelada'], true)) {
            return back()->withErrors(['factura_id' => 'No se pueden registrar notas de crédito sobre facturas canceladas.']);
        }

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
                && NotaCredito::where('uuid_fiscal', $fiscal['uuid_fiscal'])->exists()) {
                return back()
                    ->withInput()
                    ->withErrors(['xml' => 'Ya existe una nota de crédito registrada con el UUID del XML.']);
            }
        }

        $monto = isset($fiscal['total']) ? (float) $fiscal['total'] : (float) $request->input('monto');

        // Validar que monto + notas previas + anticipos no exceda total
        $facturaTotal = (float) $factura->total;
        $notasPreviasMonto = (float) $factura->notasCredito()
            ->where('estatus', NotaCreditoEstatus::Vigente->value)
            ->sum('monto');
        $anticiposMonto = (float) $factura->anticiposAplicados()->sum('monto');

        if ($monto + $notasPreviasMonto + $anticiposMonto > $facturaTotal + 0.001) {
            return back()
                ->withInput()
                ->withErrors([
                    'monto' => sprintf(
                        'El monto excede el saldo facturado disponible (%.2f).',
                        max(0.0, $facturaTotal - $notasPreviasMonto - $anticiposMonto),
                    ),
                ]);
        }

        $nota = DB::transaction(function () use ($request, $factura, $monto, $fiscal) {
            $nota = NotaCredito::create([
                'factura_id' => $factura->id,
                'uuid_fiscal' => $fiscal['uuid_fiscal'] ?? $request->input('uuid_fiscal'),
                'folio_fiscal' => $fiscal['folio_fiscal'] ?? $request->input('folio_fiscal'),
                'subtotal' => $fiscal['subtotal'] ?? round($monto / 1.16, 2),
                'iva_trasladado' => $fiscal['iva_trasladado'] ?? 0,
                'monto' => $monto,
                'impuestos_detalle' => $fiscal['impuestos_detalle'] ?? null,
                'concepto' => $request->string('concepto'),
                'fecha_emision' => $fiscal['fecha_factura'] ?? $request->input('fecha_emision'),
                'estatus' => NotaCreditoEstatus::Vigente->value,
                'creado_por' => $request->user()->id,
            ]);

            $this->guardarMedia($request, $nota, $factura->proveedor_id);

            return $nota;
        });

        return to_route('admin.costos.notas-credito.show', $nota)
            ->with('success', 'Nota de crédito registrada correctamente.');
    }

    public function show(NotaCredito $notaCredito): Response
    {
        Gate::authorize('costos.notas-credito.ver');

        $notaCredito->load([
            'factura:id,folio,total,proveedor_id',
            'factura.proveedor:id,razon_social',
            'creador:id,name',
            'media',
            'mediaXml',
            'mediaPdf',
            'activities.causer',
        ]);

        return Inertia::render('admin/costos/notas-credito/show', [
            'nota' => $notaCredito,
        ]);
    }

    public function cancelar(CancelarRequest $request, NotaCredito $notaCredito): RedirectResponse
    {
        Gate::authorize('costos.notas-credito.cancelar');

        if ($notaCredito->estatus !== NotaCreditoEstatus::Vigente) {
            return back()->withErrors(['estatus' => 'Solo se pueden cancelar notas de crédito vigentes.']);
        }

        DB::transaction(function () use ($notaCredito, $request) {
            $notaCredito->update(['motivo_cancelacion' => $request->validated('motivo')]);
            $notaCredito->transitionTo(NotaCreditoEstatus::Cancelada);
        });

        return back()->with('success', 'Nota de crédito cancelada.');
    }

    private function guardarMedia(Request $request, NotaCredito $nota, int $proveedorId): void
    {
        if ($request->hasFile('xml')) {
            $file = $request->file('xml');
            $nota->media()->create([
                'descripcion' => DocumentoTipo::XmlNotaCredito->value,
                'nombre_original' => $file->getClientOriginalName(),
                'path' => $file->store("notas-credito/{$proveedorId}", 'public'),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        if ($request->hasFile('pdf')) {
            $file = $request->file('pdf');
            $nota->media()->create([
                'descripcion' => DocumentoTipo::PdfNotaCredito->value,
                'nombre_original' => $file->getClientOriginalName(),
                'path' => $file->store("notas-credito/{$proveedorId}", 'public'),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }
}
