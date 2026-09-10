<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\FacturaEstatus;
use App\Enums\Costos\RubroAfectadoEstatus;
use App\Exports\Costos\RecepcionesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\EntregaCancelarRequest;
use App\Http\Requests\Admin\Costos\EntregaUpdateRequest;
use App\Http\Requests\Admin\Costos\RecepcionesReporteRequest;
use App\Models\Costos\Entrega;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\Factura;
use App\Models\Usuario;
use App\Services\Alm\RegistradorEntradaAlmacen;
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
    public function __construct(
        private readonly ApartadoPresupuestal $apartado,
        private readonly RegistradorEntradaAlmacen $almacen,
    ) {}

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
                // Candidatas para re-ligar la recepción desde el modal de edición.
                'ordenCompra.facturas:id,orden_compra_id,folio,total,estatus,aprobada_costos,aceptada_contabilidad',
                'ordenCompra.facturas.pago:id,pagable_id,pagable_type',
                'factura:id,folio',
                // `pago` es polimórfica (`pagable`), no tiene `factura_id`: el
                // select debe traer la llave morph o Eloquent no puede emparejar.
                'factura.pago:id,pagable_id,pagable_type',
                'recibidor:id,name',
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
            // Dos fechas distintas y ambas se muestran: la de recepción es el
            // sello del sistema (cuándo se elaboró el documento, no editable) y
            // la de entrega es la operativa, la que captura quien recibe.
            'fecha_recepcion' => $entrega->fechaRecepcionLocal()?->toDateString(),
            'fecha_entrega' => $entrega->fecha_entrega?->toDateString(),
            'tipo' => $entrega->tipo,
            'recibido_por' => $entrega->recibidor?->name,
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
            'factura_id' => $entrega->factura_id,
            'completa_factura' => (bool) $entrega->completa_factura,
            // Facturas de la OC a las que se puede re-ligar la recepción: las que
            // aún no avanzan, más la que ya tiene (para no perderla del selector).
            'facturas_disponibles' => $oc
                ? $oc->facturas
                    ->filter(fn (Factura $f) => $f->id === $entrega->factura_id || ! $this->facturaYaAvanzo($f))
                    ->map(fn (Factura $f) => [
                        'id' => $f->id,
                        'folio' => $f->folio,
                        'total' => (float) $f->total,
                        'estatus' => $f->estatus?->value,
                    ])->values()
                : [],
            'pdf_url' => route('admin.costos.entregas.pdf', $entrega),
        ];
    }

    /**
     * Corrige los datos de captura de una recepción: quién recibió,
     * observaciones, evidencia y la factura a la que se ligó (el dedazo más
     * común cuando la OC trae varias facturas del proveedor).
     *
     * Deliberadamente NO toca cantidades, precios ni las fechas. Las dos
     * primeras mueven saldo de partidas y presupuesto; la fecha de recepción es
     * el sello del sistema y la de entrega quedó capturada al recibir. Para
     * cualquiera de las tres el camino sigue siendo cancelar y volver a
     * capturar, que ya sabe revertir cada efecto.
     *
     * La factura es opcional: se puede ligar la que faltó, cambiarla o dejar la
     * recepción sin ninguna.
     *
     * Se bloquea cuando la factura ya está pagada: a partir de ahí el dato
     * respalda dinero que ya salió. Y el cambio de factura se bloquea además si
     * cualquiera de las dos (la actual o la nueva) ya avanzó, porque re-ligar
     * mueve el "completamente entregada" y con él el estatus de la factura.
     */
    public function update(EntregaUpdateRequest $request, Entrega $entrega): RedirectResponse
    {
        $entrega->loadMissing(['factura.pago', 'ordenCompra', 'media']);

        if ($entrega->estaCancelada()) {
            return back()->withErrors(['error' => 'No se puede editar una recepción cancelada.']);
        }

        if ($entrega->factura?->pago !== null) {
            return back()->withErrors(['error' => 'No se puede editar: la factura ligada ya está pagada.']);
        }

        $facturaActual = $entrega->factura;

        // Sin factura_id la recepción queda (o se deja) sin factura ligada.
        $facturaNueva = null;
        if ($facturaId = $request->integer('factura_id')) {
            $facturaNueva = $entrega->ordenCompra?->facturas()->find($facturaId);

            if ($facturaNueva === null) {
                return back()->withErrors(['factura_id' => 'La factura no pertenece a la orden de compra de esta recepción.']);
            }
        }

        $cambiaFactura = $facturaNueva?->id !== $facturaActual?->id;

        if ($cambiaFactura) {
            if ($facturaActual !== null && $this->facturaYaAvanzo($facturaActual)) {
                return back()->withErrors([
                    'factura_id' => 'No se puede cambiar la factura: la actual ya fue aprobada o aceptada por contabilidad.',
                ]);
            }

            if ($facturaNueva !== null && $this->facturaYaAvanzo($facturaNueva)) {
                return back()->withErrors([
                    'factura_id' => 'La factura seleccionada ya avanzó (aprobada, aceptada por contabilidad o pagada) y no admite recepciones nuevas.',
                ]);
            }
        }

        $completabaAntes = (bool) $entrega->completa_factura;
        $completaFactura = $facturaNueva !== null && $request->boolean('completa_factura');

        DB::transaction(function () use ($request, $entrega, $facturaActual, $facturaNueva, $cambiaFactura, $completabaAntes, $completaFactura) {
            $entrega->update([
                // Ninguna de las dos fechas se toca aquí.
                'recibido_por' => $request->input('recibido_por'),
                'observaciones' => $request->input('observaciones'),
                'factura_id' => $facturaNueva?->id,
                'completa_factura' => $completaFactura,
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

            // Si esta recepción dejó de completar a la factura anterior (porque
            // se desmarcó o porque se relingó a otra), hay que devolverle el
            // avance que le había dado.
            if ($facturaActual !== null && $completabaAntes && ($cambiaFactura || ! $completaFactura)) {
                $this->revertirAvanceDeFactura($facturaActual, $entrega);
            }

            if ($facturaNueva !== null) {
                if ($completaFactura) {
                    $facturaNueva->update(['completamente_entregada' => true]);
                }

                // Se reevalúa siempre: si la factura ya juntó entrega completa y
                // comprobante, este es el momento en que le toca avanzar. Si le
                // falta alguna, no hace nada.
                $facturaNueva->intentarPasarAAprobacion();
            }
        });

        return back()->with('success', 'Recepción actualizada correctamente.');
    }

    /**
     * Una factura que ya fue aprobada, aceptada por contabilidad o pagada no
     * admite que se le muevan las recepciones por debajo.
     */
    private function facturaYaAvanzo(Factura $factura): bool
    {
        return $factura->aprobada_costos
            || $factura->aceptada_contabilidad
            || $factura->pago !== null;
    }

    /**
     * Deshace el "completamente entregada" que esta recepción le había dado a la
     * factura y, si con eso ya no califica, la regresa a pendiente de recepción.
     * Si otra recepción vigente también la marca como completa, no se toca.
     */
    private function revertirAvanceDeFactura(Factura $factura, Entrega $entrega): void
    {
        $otraLaCompleta = $factura->entregasLigadas()
            ->activa()
            ->where('completa_factura', true)
            ->whereKeyNot($entrega->id)
            ->exists();

        if ($otraLaCompleta) {
            return;
        }

        if (! in_array($factura->estatus, [FacturaEstatus::PendienteRecepcion, FacturaEstatus::PendienteAprobacion], true)) {
            return;
        }

        $factura->update(['completamente_entregada' => false]);

        if ($factura->estatus === FacturaEstatus::PendienteAprobacion) {
            $factura->transitionTo(FacturaEstatus::PendienteRecepcion);
        }
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

            // 4. Devolver al kardex lo que esta recepción había cargado. Es un
            // movimiento espejo: si el material ya se consumió el saldo puede
            // quedar en negativo, y eso es información correcta.
            $this->almacen->revertir($entrega, (string) $request->input('motivo'), $request->user()->id);

            // 5. Recalcular el estatus de la OC: esta entrega ya no cuenta.
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
            // Las retenciones se desglosan en el bloque de totales: sin ellas
            // subtotal + IVA no cuadra contra el total del CFDI.
            'factura:id,folio,folio_fiscal,uuid_fiscal,subtotal,iva,iva_retenido,isr_retenido,total,moneda',
            'recibidor:id,name',
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
}
