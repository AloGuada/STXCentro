<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Costos\ComplementoPagoEstatus;
use App\Enums\Costos\DocumentoTipo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\PortalComplementoPagoStoreRequest;
use App\Models\Costos\ComplementoPago;
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
        ]);
    }

    /**
     * Recibe el XML del complemento de pago (CFDI tipo P), lo valida contra las
     * facturas PPD del proveedor y marca las obligaciones correspondientes como
     * cumplidas. Rechaza complementos huérfanos (sin factura conocida) y UUID
     * duplicados.
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

        if (! empty($parsed['uuid_fiscal'])
            && ComplementoPago::where('complemento_uuid', $parsed['uuid_fiscal'])->exists()) {
            return back()->withErrors(['xml' => 'Este complemento de pago ya fue registrado.']);
        }

        if (empty($parsed['docs_relacionados'])) {
            return back()->withErrors(['xml' => 'El complemento no referencia ninguna factura.']);
        }

        // Empareja cada documento relacionado con una obligación pendiente del
        // proveedor (por UUID de factura + importe del pago).
        $obligaciones = [];
        foreach ($parsed['docs_relacionados'] as $doc) {
            $factura = Factura::where('proveedor_id', $proveedor->id)
                ->where('uuid_fiscal', $doc['uuid'])
                ->first();

            if (! $factura) {
                return back()->withErrors(['xml' => "El complemento referencia una factura desconocida (UUID {$doc['uuid']})."]);
            }

            $obligacion = ComplementoPago::where('factura_id', $factura->id)
                ->whereIn('estatus', [ComplementoPagoEstatus::Pendiente->value, ComplementoPagoEstatus::Vencido->value])
                ->whereRaw('ABS(monto_pago - ?) < 0.01', [$doc['imp_pagado']])
                ->orderBy('fecha_pago')
                ->first();

            if (! $obligacion) {
                return back()->withErrors(['xml' => "No hay un pago pendiente de complemento que coincida con el importe {$doc['imp_pagado']} de la factura {$factura->folio}."]);
            }

            $obligaciones[] = $obligacion;
        }

        DB::transaction(function () use ($obligaciones, $parsed, $request, $proveedor): void {
            foreach ($obligaciones as $obligacion) {
                $obligacion->update([
                    'complemento_uuid' => $parsed['uuid_fiscal'],
                    'recibido_at' => now(),
                ]);
                $obligacion->transitionTo(ComplementoPagoEstatus::Cumplido);

                $xml = $request->file('xml');
                $obligacion->media()->create([
                    'descripcion' => DocumentoTipo::XmlComplementoPago->value,
                    'nombre_original' => $xml->getClientOriginalName(),
                    'path' => $xml->store("costos/complementos/{$proveedor->id}/{$obligacion->id}", 'public'),
                    'mime' => $xml->getMimeType(),
                    'size' => $xml->getSize(),
                ]);

                if ($request->hasFile('pdf')) {
                    $pdf = $request->file('pdf');
                    $obligacion->media()->create([
                        'descripcion' => DocumentoTipo::PdfComplementoPago->value,
                        'nombre_original' => $pdf->getClientOriginalName(),
                        'path' => $pdf->store("costos/complementos/{$proveedor->id}/{$obligacion->id}", 'public'),
                        'mime' => $pdf->getMimeType(),
                        'size' => $pdf->getSize(),
                    ]);
                }
            }
        });

        return back()->with('success', 'Complemento de pago registrado correctamente.');
    }
}
