<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\NotaCreditoEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\PortalNotaCreditoStoreRequest;
use App\Models\Costos\Factura;
use App\Models\Costos\NotaCredito;
use App\Services\Costos\CfdiXmlParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PortalNotaCreditoController extends Controller
{
    public function __construct(private readonly CfdiXmlParser $cfdiParser) {}

    /**
     * Alta de nota de crédito desde el portal del proveedor: solo permite
     * subirla contra facturas propias. Replica las validaciones del flujo
     * admin (parseo XML, monto + NC previas + anticipos ≤ total factura).
     */
    public function store(PortalNotaCreditoStoreRequest $request): RedirectResponse
    {
        $proveedor = Auth::guard('proveedor')->user();
        $factura = Factura::findOrFail($request->integer('factura_id'));

        abort_if($factura->proveedor_id !== $proveedor->id, 403);

        if ($factura->estatus->value === 'cancelada') {
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

        DB::transaction(function () use ($request, $factura, $monto, $fiscal, $proveedor): void {
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
                'creado_por' => null,
            ]);

            if ($request->hasFile('xml')) {
                $file = $request->file('xml');
                $nota->media()->create([
                    'descripcion' => DocumentoTipo::XmlNotaCredito->value,
                    'nombre_original' => $file->getClientOriginalName(),
                    'path' => $file->store("notas-credito/{$proveedor->id}", 'public'),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            if ($request->hasFile('pdf')) {
                $file = $request->file('pdf');
                $nota->media()->create([
                    'descripcion' => DocumentoTipo::PdfNotaCredito->value,
                    'nombre_original' => $file->getClientOriginalName(),
                    'path' => $file->store("notas-credito/{$proveedor->id}", 'public'),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        });

        return back()->with('success', 'Nota de crédito registrada correctamente.');
    }
}
