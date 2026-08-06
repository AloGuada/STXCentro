<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\FacturaEstatus;
use App\Enums\Costos\RubroAfectadoEstatus;
use App\Exports\Costos\RecepcionesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\EntregaCancelarRequest;
use App\Http\Requests\Admin\Costos\EntregaStoreRequest;
use App\Http\Requests\Admin\Costos\EntregaUpdateRequest;
use App\Http\Requests\Admin\Costos\RecepcionesReporteRequest;
use App\Models\Costos\Entrega;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Usuario;
use App\Services\Costos\ApartadoPresupuestal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
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
        $filtros = $this->filtrosDeListado($request);

        $recepciones = Entrega::query()
            ->with([
                // El importe recibido sale de los renglones; el precio unitario
                // cae al de la OC cuando la recepción no lo capturó.
                'detalles',
                'detalles.ordenCompraDetalle:id,precio_unitario',
                'ordenCompra',
                'ordenCompra.proveedor:id,razon_social,nombre_comercial',
                // El destino presupuestal vive en las partidas, no en la OC:
                // `ordenes_compra.obra_id` quedó sin uso al volverse polimórfico
                // el presupuesto y hoy llega null en toda OC nueva. Se precargan
                // los tres caminos que resuelve `nombresDePresupuesto()`.
                'ordenCompra.detalles:id,orden_compra_id,obra_rubro_id',
                'ordenCompra.detalles.obraRubro.presupuesto.presupuestable',
                'ordenCompra.solicitudesPago',
                'ordenCompra.solicitudesPago.detalles.obraRubro.presupuesto.presupuestable',
                'ordenCompra.requisicion.presupuesto.presupuestable',
                'factura:id,folio',
                // `pago` es polimórfica (`pagable`), no tiene `factura_id`: el
                // select debe traer la llave morph o Eloquent no puede emparejar.
                'factura.pago:id,pagable_id,pagable_type',
                'recibidoPor:id,name',
            ])
            ->filtradas($filtros)
            ->latest('fecha_entrega')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Entrega $entrega) => $this->filaRecepcion($entrega));

        return Inertia::render('admin/costos/recepciones/index', [
            'recepciones' => $recepciones,
            'filters' => $request->only('search', 'tipo'),
            'totales_recibidos' => $this->totalesRecibidos($filtros),
            'usuarios' => $this->usuariosQuePuedenRecibir(),
        ]);
    }

    /**
     * Importe de todo lo recibido bajo los filtros activos, no solo de la página
     * en pantalla. Se calcula en SQL (una suma, sin traer los renglones) y deja
     * fuera las recepciones canceladas, que no representan material recibido.
     * Se agrupa por moneda de la OC para no sumar pesos con dólares.
     *
     * @param  array{search: ?string, tipo: ?string, solicitante_id: ?string}  $filtros
     * @return array<string, float>
     */
    private function totalesRecibidos(array $filtros): array
    {
        return EntregaDetalle::query()
            ->whereHas('entrega', fn ($q) => $q->filtradas($filtros)->whereNull('cancelada_at'))
            ->leftJoin(
                'costos_ordenes_compra_detalle',
                'costos_ordenes_compra_detalle.id',
                '=',
                'costos_entrega_detalle.orden_compra_detalle_id',
            )
            ->leftJoin('costos_entregas', 'costos_entregas.id', '=', 'costos_entrega_detalle.entrega_id')
            ->leftJoin('costos_ordenes_compra', 'costos_ordenes_compra.id', '=', 'costos_entregas.orden_compra_id')
            ->selectRaw("COALESCE(costos_ordenes_compra.moneda, 'mxn') as moneda")
            ->selectRaw(
                'SUM(costos_entrega_detalle.cantidad_recibida * COALESCE(costos_entrega_detalle.precio_unitario, costos_ordenes_compra_detalle.precio_unitario, 0)) as total',
            )
            ->groupByRaw("COALESCE(costos_ordenes_compra.moneda, 'mxn')")
            ->pluck('total', 'moneda')
            ->map(fn ($total): float => round((float) $total, 2))
            ->all();
    }

    /**
     * Reporte en Excel del listado, acotado por fecha de recepción. Arrastra los
     * filtros activos de la pantalla para que el archivo sea lo que el usuario
     * está viendo, recortado al rango que pidió.
     */
    public function exportar(RecepcionesReporteRequest $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $filtros = [
            ...$this->filtrosDeListado($request),
            'fecha_inicio' => $request->validated('fecha_inicio'),
            'fecha_fin' => $request->validated('fecha_fin'),
        ];

        $nombre = sprintf(
            'recepciones-%s-a-%s.xlsx',
            str_replace('-', '', $filtros['fecha_inicio']),
            str_replace('-', '', $filtros['fecha_fin']),
        );

        return Excel::download(new RecepcionesExport($filtros), $nombre);
    }

    /**
     * Filtros comunes a la pantalla y a su reporte, incluida la visibilidad:
     * quien no puede ver todas las OC solo ve las recepciones de sus propias
     * requisiciones.
     *
     * @return array{search: ?string, tipo: ?string, solicitante_id: ?string}
     */
    private function filtrosDeListado(Request $request): array
    {
        return [
            'search' => $request->string('search')->toString() ?: null,
            'tipo' => $request->string('tipo')->toString() ?: null,
            'solicitante_id' => $request->user()->can('costos.ordenes-compra.ver-todas')
                ? null
                : $request->user()->id,
        ];
    }

    /**
     * Candidatos para "recibió" al corregir una recepción. Se manda plano
     * (id + nombre) porque sólo alimenta un selector.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Usuario>
     */
    private function usuariosQuePuedenRecibir(): \Illuminate\Support\Collection
    {
        return Usuario::query()->activos()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return array<string, mixed>
     */
    private function filaRecepcion(Entrega $entrega): array
    {
        $oc = $entrega->ordenCompra;
        $proveedor = $oc?->proveedor;

        return [
            'id' => $entrega->id,
            'folio' => $entrega->folio,
            'fecha_entrega' => $entrega->fecha_entrega?->toDateString(),
            'tipo' => $entrega->tipo,
            'recibido_por' => $entrega->recibidoPor?->name,
            'recibido_por_id' => $entrega->recibido_por,
            'observaciones' => $entrega->observaciones,
            'cancelada' => $entrega->estaCancelada(),
            // La regla de edicion vive aqui para que la pantalla no la reinvente.
            'puede_editar' => ! $entrega->estaCancelada() && $entrega->factura?->pago === null,
            'total' => $entrega->importeRecibido(),
            'oc' => $oc ? [
                'id' => $oc->id,
                'folio' => $oc->folio,
                'tipo_pago' => $oc->tipo_pago?->value,
                'moneda' => $oc->moneda,
                'url' => route('admin.costos.ordenes-compra.show', $oc),
            ] : null,
            'proveedor' => $proveedor ? ($proveedor->razon_social ?: $proveedor->nombre_comercial) : null,
            'obras' => $oc?->nombresDePresupuesto() ?? [],
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
            ->whereHas('entrega', fn ($q) => $q->activa())
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
                'completa_factura' => $factura !== null && $request->boolean('completa_factura'),
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
     * Corrige los datos de captura de una recepción: fecha, quién recibió,
     * observaciones y evidencia.
     *
     * Deliberadamente NO toca cantidades, precios ni la factura ligada. Esos
     * mueven saldo de partidas, presupuesto y el estatus de la factura; para
     * corregirlos el camino sigue siendo cancelar y volver a capturar, que ya
     * sabe revertir cada efecto.
     *
     * Se bloquea sólo cuando la factura ya está pagada: a partir de ahí el dato
     * respalda dinero que ya salió.
     */
    public function update(EntregaUpdateRequest $request, Entrega $entrega): RedirectResponse
    {
        $entrega->loadMissing(['factura.pago', 'media']);

        if ($entrega->estaCancelada()) {
            return back()->withErrors(['error' => 'No se puede editar una recepción cancelada.']);
        }

        if ($entrega->factura?->pago !== null) {
            return back()->withErrors(['error' => 'No se puede editar: la factura ligada ya está pagada.']);
        }

        DB::transaction(function () use ($request, $entrega) {
            $entrega->update([
                'fecha_entrega' => $request->input('fecha_entrega'),
                'recibido_por' => $request->input('recibido_por'),
                'observaciones' => $request->input('observaciones'),
            ]);

            // La evidencia se reemplaza, no se acumula: la recepción tiene una.
            if ($request->hasFile('archivo')) {
                $entrega->media()->delete();

                $file = $request->file('archivo');
                $entrega->media()->create([
                    'descripcion' => DocumentoTipo::EvidenciaRecepcion->value,
                    'nombre_original' => $file->getClientOriginalName(),
                    'path' => $file->store('costos/entregas', 'public'),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        });

        return back()->with('success', 'Recepción actualizada correctamente.');
    }

    /**
     * Cancela una entrega (soft) y revierte su movimiento: deshace el ajuste
     * presupuestal por diferencia de precio, revierte el avance de la factura que
     * hubiera marcado como completa, y recalcula el estatus de la OC. Bloquea si
     * la factura ya avanzó (aprobada/aceptada/pagada) o si hay devoluciones vigentes.
     */
    public function cancelar(EntregaCancelarRequest $request, Entrega $entrega): RedirectResponse
    {
        $entrega->loadMissing(['factura.pago', 'detalles.ordenCompraDetalle', 'detalles.devoluciones', 'ordenCompra']);

        if ($entrega->estaCancelada()) {
            return back()->withErrors(['error' => 'Esta entrega ya está cancelada.']);
        }

        $factura = $entrega->factura;
        if ($factura !== null && ($factura->aprobada_costos || $factura->aceptada_contabilidad || $factura->pago !== null)) {
            return back()->withErrors(['error' => 'No se puede cancelar: la factura ligada ya avanzó (aprobada, aceptada por contabilidad o pagada).']);
        }

        $conDevoluciones = $entrega->detalles->contains(fn (EntregaDetalle $d) => $d->cantidad_devuelta > 0);
        if ($conDevoluciones) {
            return back()->withErrors(['error' => 'No se puede cancelar: hay devoluciones vigentes sobre esta entrega. Cancélalas primero.']);
        }

        DB::transaction(function () use ($request, $entrega, $factura) {
            // 1. Revertir el ajuste presupuestal por diferencia de precio de cada partida.
            foreach ($entrega->detalles as $detalle) {
                $ocd = $detalle->ordenCompraDetalle;
                if ($ocd === null || $ocd->obra_rubro_id === null || $detalle->precio_unitario === null) {
                    continue;
                }

                $delta = ((float) $detalle->precio_unitario - (float) $ocd->precio_unitario) * (float) $detalle->cantidad_recibida;
                if (abs($delta) < 0.005) {
                    continue;
                }

                $this->apartado->aplicarCargo(
                    entrada: $entrega->ordenCompra,
                    obraRubroId: (int) $ocd->obra_rubro_id,
                    monto: -$delta,
                    estatus: RubroAfectadoEstatus::Aplicado,
                    descripcion: "Reverso ajuste PU · recepción {$entrega->folio} cancelada",
                    userId: $request->user()->id,
                    allowSobregiro: true,
                    moneda: $entrega->ordenCompra->moneda ?? 'mxn',
                );
            }

            // 2. Si esta entrega marcó la factura como completa, revertir ese avance.
            if ($factura !== null && $entrega->completa_factura
                && in_array($factura->estatus, [FacturaEstatus::PendienteRecepcion, FacturaEstatus::PendienteAprobacion], true)) {
                $factura->update(['completamente_entregada' => false]);
                if ($factura->estatus === FacturaEstatus::PendienteAprobacion) {
                    $factura->transitionTo(FacturaEstatus::PendienteRecepcion);
                }
            }

            // 3. Marcar cancelada (soft) con bitácora.
            $entrega->update([
                'cancelada_at' => now(),
                'cancelada_por' => $request->user()->id,
                'motivo_cancelacion' => $request->input('motivo'),
            ]);

            // 4. Recalcular el estatus de la OC: esta entrega ya no cuenta.
            $entrega->ordenCompra?->recalcularEstatus();

            activity('costos')
                ->performedOn($entrega)
                ->withProperties(['motivo' => $request->input('motivo')])
                ->log('Entrega cancelada');
        });

        return back()->with('success', 'Entrega cancelada y movimiento revertido.');
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
            'factura:id,folio,folio_fiscal,uuid_fiscal,subtotal,iva,total,moneda',
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
