<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Costos\ComplementoPagoEstatus;
use App\Enums\Costos\DocumentoTipo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\PortalComplementoPagoStoreRequest;
use App\Models\Costos\ComplementoPago;
use App\Models\Costos\ComplementoRecibido;
use App\Models\Costos\Factura;
use App\Services\Costos\CfdiXmlParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PortalComplementoPagoController extends Controller
{
    public function __construct(private readonly CfdiXmlParser $cfdiParser) {}

    /**
     * Lista las obligaciones de complemento de pago del proveedor autenticado.
     */
    public function index(): Response
    {
        $proveedor = Auth::guard('proveedor')->user();

        $prioridad = ['vencido' => 0, 'pendiente' => 1, 'cumplido' => 2];

        $complementos = ComplementoPago::query()
            ->where('proveedor_id', $proveedor->id)
            ->with(['factura:id,folio,uuid_fiscal,total'])
            ->latest('fecha_pago')
            ->get()
            ->sortBy(fn (ComplementoPago $c) => $prioridad[$c->estatus->value] ?? 9)
            ->values();

        return Inertia::render('portal/complementos/index', [
            'complementos' => $complementos,
            // Resultado del ultimo complemento subido: un REP puede tocar varias
            // facturas y conviene ver renglon por renglon cual entro y cual no.
            'resultado' => session('resultado_complemento'),
        ]);
    }

    /**
     * Recibe el XML del complemento de pago (CFDI tipo P) y lo aplica a las
     * obligaciones del proveedor.
     *
     * Un REP puede referenciar varias facturas y puede cubrir sólo una parte del
     * pago, porque el SAT pide un complemento por parcialidad. Por eso cada
     * `DoctoRelacionado` se resuelve por su cuenta: los que empatan por UUID se
     * aplican y los que no se reportan, en vez de tirar el archivo completo por
     * un solo renglón.
     */
    public function store(PortalComplementoPagoStoreRequest $request): RedirectResponse
    {
        $proveedor = Auth::guard('proveedor')->user();

        try {
            $parsed = $this->cfdiParser->parseComplementoPago(
                file_get_contents($request->file('xml')->getRealPath()) ?: ''
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['xml' => $e->getMessage()]);
        }

        if ($parsed['tipo_comprobante'] !== 'P') {
            return back()->withErrors(['xml' => 'El XML no es un complemento de pago (CFDI tipo "P").']);
        }

        if (empty($parsed['docs_relacionados'])) {
            return back()->withErrors(['xml' => 'El complemento no referencia ninguna factura.']);
        }

        $uuidRep = $parsed['uuid_fiscal'] ?? null;

        if ($uuidRep && ComplementoRecibido::where('uuid', $uuidRep)->exists()) {
            return back()->withErrors(['xml' => 'Este complemento de pago ya fue registrado.']);
        }

        $resultados = [];

        DB::transaction(function () use ($parsed, $proveedor, $request, $uuidRep, &$resultados): void {
            foreach ($parsed['docs_relacionados'] as $doc) {
                $resultados[] = $this->aplicarDocumento($doc, $proveedor, $request, $uuidRep);
            }
        });

        $aplicados = collect($resultados)->where('aplicado', true);

        if ($aplicados->isEmpty()) {
            return back()->withErrors([
                'xml' => 'No se pudo aplicar el complemento: '.collect($resultados)->pluck('detalle')->implode(' '),
            ]);
        }

        $mensaje = $aplicados->count() === count($resultados)
            ? "Complemento aplicado a {$aplicados->count()} factura(s)."
            : "Complemento aplicado a {$aplicados->count()} de ".count($resultados).' factura(s).';

        return back()
            ->with('success', $mensaje)
            ->with('resultado_complemento', $resultados);
    }

    /**
     * Aplica un `DoctoRelacionado` del REP a la obligación que le toca.
     *
     * @param  array{uuid: string, imp_pagado: float}  $doc
     * @return array{uuid: string, aplicado: bool, factura: ?string, detalle: string}
     */
    private function aplicarDocumento(array $doc, $proveedor, PortalComplementoPagoStoreRequest $request, ?string $uuidRep): array
    {
        $factura = Factura::where('proveedor_id', $proveedor->id)
            ->where('uuid_fiscal', $doc['uuid'])
            ->first();

        if (! $factura) {
            return $this->resultado($doc['uuid'], false, null, 'No corresponde a ninguna factura tuya.');
        }

        // La más vieja primero: si el pago se partió en varias obligaciones, se
        // cubren en el orden en que se generaron.
        $obligacion = ComplementoPago::where('factura_id', $factura->id)
            ->whereIn('estatus', [ComplementoPagoEstatus::Pendiente->value, ComplementoPagoEstatus::Vencido->value])
            ->orderBy('fecha_pago')
            ->first();

        if (! $obligacion) {
            return $this->resultado($doc['uuid'], false, $factura->folio, 'Esa factura no tiene complementos pendientes.');
        }

        $obligacion->recibidos()->create([
            'uuid' => $uuidRep ?? $doc['uuid'],
            'imp_pagado' => $doc['imp_pagado'],
            'recibido_at' => now(),
        ]);

        $obligacion->update([
            'complemento_uuid' => $uuidRep,
            'monto_cubierto' => (float) $obligacion->monto_cubierto + (float) $doc['imp_pagado'],
            'recibido_at' => now(),
        ]);

        $this->adjuntarArchivos($obligacion, $request, $proveedor);

        // Marcar cumplida con el primer REP parcial liberaría al proveedor
        // debiendo todavía los complementos del resto del pago.
        if (! $obligacion->fresh()->estaCubierto()) {
            $falta = number_format($obligacion->fresh()->saldoPorComplementar(), 2);

            return $this->resultado(
                $doc['uuid'],
                true,
                $factura->folio,
                "Aplicado parcialmente: faltan {$falta} por complementar.",
            );
        }

        $obligacion->transitionTo(ComplementoPagoEstatus::Cumplido);

        return $this->resultado($doc['uuid'], true, $factura->folio, 'Complemento cumplido.');
    }

    private function adjuntarArchivos(ComplementoPago $obligacion, PortalComplementoPagoStoreRequest $request, $proveedor): void
    {
        $carpeta = "costos/complementos/{$proveedor->id}/{$obligacion->id}";

        $xml = $request->file('xml');
        $obligacion->media()->create([
            'descripcion' => DocumentoTipo::XmlComplementoPago->value,
            'nombre_original' => $xml->getClientOriginalName(),
            'path' => $xml->store($carpeta, 'public'),
            'mime' => $xml->getMimeType(),
            'size' => $xml->getSize(),
        ]);

        if ($request->hasFile('pdf')) {
            $pdf = $request->file('pdf');
            $obligacion->media()->create([
                'descripcion' => DocumentoTipo::PdfComplementoPago->value,
                'nombre_original' => $pdf->getClientOriginalName(),
                'path' => $pdf->store($carpeta, 'public'),
                'mime' => $pdf->getMimeType(),
                'size' => $pdf->getSize(),
            ]);
        }
    }

    /**
     * @return array{uuid: string, aplicado: bool, factura: ?string, detalle: string}
     */
    private function resultado(string $uuid, bool $aplicado, ?string $factura, string $detalle): array
    {
        return [
            'uuid' => $uuid,
            'aplicado' => $aplicado,
            'factura' => $factura,
            'detalle' => $detalle,
        ];
    }
}
