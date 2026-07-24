<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\RubroAfectadoEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\EntregaStoreRequest;
use App\Models\Costos\Entrega;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Services\Costos\ApartadoPresupuestal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EntregaController extends Controller
{
    public function __construct(private readonly ApartadoPresupuestal $apartado) {}

    /**
     * Listado global de recepciones (folio REC-…), cada una ligada a su OC y a
     * la Solicitud de Pago de esa OC (solo las de contado la generan). Misma
     * visibilidad que las OC: quien no puede ver todas solo ve las recepciones
     * de OC nacidas de sus propias requisiciones.
     */
    public function index(Request $request): Response
    {
        $recepciones = Entrega::query()
            ->with([
                'ordenCompra',
                'ordenCompra.proveedor:id,razon_social,nombre_comercial',
                'ordenCompra.obra:id,no,descripcion',
                'ordenCompra.solicitudesPago:id,orden_compra_id,folio,estatus',
                'factura:id,folio',
                'recibidoPor:id,name',
            ])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('folio', 'like', "%{$search}%")
                        ->orWhereHas('ordenCompra', fn ($oc) => $oc->where('folio', 'like', "%{$search}%"))
                        ->orWhereHas('ordenCompra.proveedor', fn ($p) => $p->where('razon_social', 'like', "%{$search}%"));
                });
            })
            ->when($request->tipo, fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->unless($request->user()->can('costos.ordenes-compra.ver-todas'), function ($q) use ($request) {
                $q->whereHas('ordenCompra.requisicion', fn ($r) => $r->where('solicitante_id', $request->user()->id));
            })
            ->latest('fecha_entrega')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Entrega $entrega) => $this->filaRecepcion($entrega));

        return Inertia::render('admin/costos/recepciones/index', [
            'recepciones' => $recepciones,
            'filters' => $request->only('search', 'tipo'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filaRecepcion(Entrega $entrega): array
    {
        $oc = $entrega->ordenCompra;
        $proveedor = $oc?->proveedor;
        $obra = $oc?->obra;

        return [
            'id' => $entrega->id,
            'folio' => $entrega->folio,
            'fecha_entrega' => $entrega->fecha_entrega?->toDateString(),
            'tipo' => $entrega->tipo,
            'recibido_por' => $entrega->recibidoPor?->name,
            'oc' => $oc ? [
                'id' => $oc->id,
                'folio' => $oc->folio,
                'tipo_pago' => $oc->tipo_pago?->value,
                'url' => route('admin.costos.ordenes-compra.show', $oc),
            ] : null,
            'proveedor' => $proveedor ? ($proveedor->razon_social ?: $proveedor->nombre_comercial) : null,
            'obra' => $obra ? trim("{$obra->no} {$obra->descripcion}") : null,
            'solicitudes_pago' => $oc
                ? $oc->solicitudesPago->map(fn ($sp) => [
                    'id' => $sp->id,
                    'folio' => $sp->folio,
                    'estatus' => $sp->estatus?->value,
                    'url' => route('admin.costos.solicitudes-pago.show', $sp),
                ])->values()
                : [],
            'factura' => $entrega->factura ? [
                'id' => $entrega->factura->id,
                'folio' => $entrega->factura->folio,
            ] : null,
            'pdf_url' => route('admin.costos.entregas.pdf', $entrega),
        ];
    }

    public function store(EntregaStoreRequest $request, OrdenCompra $ordenCompra): RedirectResponse
    {
        $partidasOrden = $ordenCompra->detalles()->pluck('id')->all();
        $detallesInput = $request->input('detalles', []);

        // Factura (opcional) a la que se liga la recepción: debe ser de esta OC.
        $factura = null;
        if ($facturaId = $request->integer('factura_id')) {
            $factura = $ordenCompra->facturas()->find($facturaId);
            if ($factura === null) {
                return back()->withErrors(['factura_id' => 'La factura no pertenece a esta orden de compra.']);
            }
        }

        // 1. Validar que todas las partidas enviadas pertenezcan a esta OC
        foreach ($detallesInput as $i => $detalle) {
            if (! in_array((int) $detalle['orden_compra_detalle_id'], $partidasOrden, true)) {
                return back()->withErrors([
                    "detalles.{$i}.orden_compra_detalle_id" => 'La partida no pertenece a esta orden de compra.',
                ]);
            }
        }

        // 2. Validar que cantidad_recibida <= cantidad_ordenada - ya_recibida (por partida)
        $yaRecibidoPorPartida = EntregaDetalle::query()
            ->whereIn('orden_compra_detalle_id', $partidasOrden)
            ->selectRaw('orden_compra_detalle_id, SUM(cantidad_recibida) as total')
            ->groupBy('orden_compra_detalle_id')
            ->pluck('total', 'orden_compra_detalle_id')
            ->map(fn ($v) => (float) $v);

        $ordenCompraDetalles = OrdenCompraDetalle::whereIn('id', $partidasOrden)
            ->get()
            ->keyBy('id');

        $acumuladoEnviado = [];
        foreach ($detallesInput as $i => $detalle) {
            $ocdId = (int) $detalle['orden_compra_detalle_id'];
            $ocd = $ordenCompraDetalles->get($ocdId);
            $cantidadRecibida = (float) $detalle['cantidad_recibida'];

            $acumuladoEnviado[$ocdId] = ($acumuladoEnviado[$ocdId] ?? 0) + $cantidadRecibida;

            $saldoPendiente = (float) $ocd->cantidad - (float) ($yaRecibidoPorPartida[$ocdId] ?? 0);
            $totalEnviado = $acumuladoEnviado[$ocdId];

            if ($totalEnviado > $saldoPendiente + config('costos.epsilon_cantidad')) {
                return back()->withErrors([
                    "detalles.{$i}.cantidad_recibida" => sprintf(
                        'Excede el saldo pendiente (%.2f %s) de la partida "%s".',
                        $saldoPendiente,
                        $ocd->unidad,
                        $ocd->descripcion,
                    ),
                ]);
            }
        }

        DB::transaction(function () use ($request, $ordenCompra, $detallesInput, $ordenCompraDetalles, $factura) {
            $entrega = $ordenCompra->entregas()->create([
                'recibido_por' => $request->user()->id,
                'fecha_entrega' => $request->input('fecha_entrega'),
                'factura_id' => $factura?->id,
                'tipo' => $request->input('tipo'),
                'observaciones' => $request->input('observaciones'),
            ]);

            if ($request->hasFile('archivo')) {
                $file = $request->file('archivo');
                $entrega->media()->create([
                    'descripcion' => DocumentoTipo::EvidenciaRecepcion->value,
                    'nombre_original' => $file->getClientOriginalName(),
                    'path' => $file->store('costos/entregas', 'public'),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            foreach ($detallesInput as $detalle) {
                $ocd = $ordenCompraDetalles->get((int) $detalle['orden_compra_detalle_id']);
                $precioRecibido = isset($detalle['precio_unitario']) && $detalle['precio_unitario'] !== ''
                    ? (float) $detalle['precio_unitario']
                    : null;

                $entrega->detalles()->create([
                    'orden_compra_detalle_id' => $detalle['orden_compra_detalle_id'],
                    'cantidad_recibida' => $detalle['cantidad_recibida'],
                    'precio_unitario' => $precioRecibido,
                    'observaciones' => $detalle['observaciones'] ?? null,
                ]);

                $this->ajustarPresupuestoPorDiferenciaPrecio(
                    $ordenCompra,
                    $ocd,
                    $precioRecibido,
                    (float) $detalle['cantidad_recibida'],
                    $request->user()->id,
                );
            }

            // Si esta recepción completa la factura, marcarla como entregada e
            // intentar avanzarla a aprobación (requiere además el comprobante).
            if ($factura !== null && $request->boolean('completa_factura')) {
                $factura->update(['completamente_entregada' => true]);
                $factura->intentarPasarAAprobacion();
            }
        });

        return back()->with('success', 'Entrega registrada correctamente.');
    }

    /**
     * Formato PDF de la recepción (folio REC-…), con layout de la solicitud de
     * pago pero listando las partidas recibidas de esta entrega.
     */
    public function pdf(Entrega $entrega): HttpResponse
    {
        $entrega->load([
            'ordenCompra.proveedor',
            'ordenCompra.obra',
            'factura:id,folio,folio_fiscal,uuid_fiscal',
            'recibidoPor:id,name',
            'detalles.ordenCompraDetalle',
        ]);

        $pdf = Pdf::loadView('pdf.costos.formato-recepcion', [
            'entrega' => $entrega,
            'moneda' => $entrega->ordenCompra?->moneda ?? $entrega->factura?->moneda ?? 'mxn',
        ])->setPaper('letter', 'portrait')
            ->setOption('margin-top', 30)
            ->setOption('margin-bottom', 40)
            ->setOption('margin-left', 40)
            ->setOption('margin-right', 40);

        return $pdf->stream("recepcion-{$entrega->folio}.pdf");
    }

    /**
     * Al recibir a un precio distinto del de la OC (ej. acero que se iguala a la
     * factura), el acumulado del rubro ya trae el cargo al precio de la OC. Se
     * registra solo la diferencia: delta = (PU recibido − PU OC) × cantidad. El
     * cargo se liga a la OC (RubroAfectado) para que se revierta si se cancela.
     */
    private function ajustarPresupuestoPorDiferenciaPrecio(
        OrdenCompra $ordenCompra,
        ?OrdenCompraDetalle $ocd,
        ?float $precioRecibido,
        float $cantidadRecibida,
        string $userId,
    ): void {
        if ($precioRecibido === null || $ocd === null || $ocd->obra_rubro_id === null) {
            return;
        }

        $delta = ($precioRecibido - (float) $ocd->precio_unitario) * $cantidadRecibida;

        if (abs($delta) < 0.005) {
            return;
        }

        $this->apartado->aplicarCargo(
            entrada: $ordenCompra,
            obraRubroId: (int) $ocd->obra_rubro_id,
            monto: $delta,
            estatus: RubroAfectadoEstatus::Aplicado,
            descripcion: "Ajuste PU recepción · {$ocd->descripcion}",
            userId: $userId,
            allowSobregiro: true,
            moneda: $ordenCompra->moneda ?? 'mxn',
        );
    }
}
