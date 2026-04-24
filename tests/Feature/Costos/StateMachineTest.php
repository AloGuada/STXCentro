<?php

use App\Enums\Costos\AfectacionEstatus;
use App\Enums\Costos\AprobacionEstatus;
use App\Enums\Costos\FacturaEstatus;
use App\Enums\Costos\OrdenCompraEstatus;
use App\Enums\Costos\PagoEstatus;
use App\Enums\Costos\RubroAfectadoEstatus;
use App\Enums\Costos\SolicitudPagoEstatus;
use App\Exceptions\Costos\InvalidStateTransitionException;
use App\Models\Costos\AfectacionPresupuestal;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Pago;
use App\Models\Costos\SolicitudPago;

describe('allowedTransitions por enum', function () {
    test('OrdenCompra: estados no terminales solo permiten Cancelada', function () {
        expect(OrdenCompraEstatus::PendienteFactura->allowedTransitions())->toBe([OrdenCompraEstatus::Cancelada]);
        expect(OrdenCompraEstatus::PendienteEntrega->allowedTransitions())->toBe([OrdenCompraEstatus::Cancelada]);
        expect(OrdenCompraEstatus::PendienteAprobacion->allowedTransitions())->toBe([OrdenCompraEstatus::Cancelada]);
        expect(OrdenCompraEstatus::PendientePago->allowedTransitions())->toBe([]);
        expect(OrdenCompraEstatus::Pagada->allowedTransitions())->toBe([]);
        expect(OrdenCompraEstatus::Cancelada->allowedTransitions())->toBe([]);
    });

    test('Factura: flujo lineal con escape a Cancelada salvo estados terminales', function () {
        expect(FacturaEstatus::PendienteEntrega->allowedTransitions())
            ->toBe([FacturaEstatus::PendienteAprobacion, FacturaEstatus::Cancelada]);
        expect(FacturaEstatus::PendienteAprobacion->allowedTransitions())
            ->toBe([FacturaEstatus::PendientePago, FacturaEstatus::Cancelada]);
        expect(FacturaEstatus::PendientePago->allowedTransitions())
            ->toBe([FacturaEstatus::Pagada, FacturaEstatus::Cancelada]);
        expect(FacturaEstatus::Pagada->allowedTransitions())->toBe([]);
        expect(FacturaEstatus::Cancelada->allowedTransitions())->toBe([]);
    });

    test('Pago: programado puede ir a pagado, parcial o cancelado', function () {
        expect(PagoEstatus::Programado->allowedTransitions())
            ->toBe([PagoEstatus::Pagado, PagoEstatus::Parcial, PagoEstatus::Cancelado]);
        expect(PagoEstatus::Parcial->allowedTransitions())
            ->toBe([PagoEstatus::Pagado, PagoEstatus::Cancelado]);
        expect(PagoEstatus::Pagado->allowedTransitions())->toBe([]);
    });

    test('SolicitudPago: borrador → pendiente_firma → aprobada → pagada', function () {
        expect(SolicitudPagoEstatus::Borrador->allowedTransitions())
            ->toBe([SolicitudPagoEstatus::PendienteFirma, SolicitudPagoEstatus::Cancelada]);
        expect(SolicitudPagoEstatus::PendienteFirma->allowedTransitions())
            ->toBe([SolicitudPagoEstatus::Aprobada, SolicitudPagoEstatus::Cancelada]);
        expect(SolicitudPagoEstatus::Aprobada->allowedTransitions())
            ->toBe([SolicitudPagoEstatus::Pagada, SolicitudPagoEstatus::Cancelada]);
        expect(SolicitudPagoEstatus::Pagada->allowedTransitions())->toBe([]);
    });

    test('Aprobacion: pendiente puede aprobarse, rechazarse o cancelarse', function () {
        expect(AprobacionEstatus::Pendiente->allowedTransitions())->toBe([
            AprobacionEstatus::Aprobada,
            AprobacionEstatus::Rechazada,
            AprobacionEstatus::Cancelada,
        ]);
        expect(AprobacionEstatus::Aprobada->allowedTransitions())->toBe([]);
        expect(AprobacionEstatus::Rechazada->allowedTransitions())->toBe([]);
    });

    test('Afectacion: borrador -> pendiente_firma -> aprobada, cancelable hasta aprobada', function () {
        expect(AfectacionEstatus::Borrador->allowedTransitions())
            ->toBe([AfectacionEstatus::PendienteFirma, AfectacionEstatus::Cancelada]);
        expect(AfectacionEstatus::PendienteFirma->allowedTransitions())
            ->toBe([AfectacionEstatus::Aprobada, AfectacionEstatus::Cancelada]);
        expect(AfectacionEstatus::Aprobada->allowedTransitions())->toBe([AfectacionEstatus::Cancelada]);
        expect(AfectacionEstatus::Cancelada->allowedTransitions())->toBe([]);
    });

    test('RubroAfectado: aplicado puede cancelarse', function () {
        expect(RubroAfectadoEstatus::Aplicado->allowedTransitions())
            ->toBe([RubroAfectadoEstatus::Cancelado]);
        expect(RubroAfectadoEstatus::Cancelado->allowedTransitions())->toBe([]);
    });
});

describe('transitionTo en modelos', function () {
    test('OrdenCompra permite transicionar a Cancelada desde PendienteFactura', function () {
        $oc = OrdenCompra::factory()->create(['estatus' => 'pendiente_factura']);

        $oc->transitionTo(OrdenCompraEstatus::Cancelada);

        expect($oc->fresh()->estatus)->toBe(OrdenCompraEstatus::Cancelada);
    });

    test('OrdenCompra rechaza transicion no permitida (pendiente_factura -> pagada)', function () {
        $oc = OrdenCompra::factory()->create(['estatus' => 'pendiente_factura']);

        expect(fn () => $oc->transitionTo(OrdenCompraEstatus::Pagada))
            ->toThrow(InvalidStateTransitionException::class);
    });

    test('OrdenCompra en estado terminal Pagada no permite ninguna transicion', function () {
        $oc = OrdenCompra::factory()->create(['estatus' => 'pagada']);

        expect(fn () => $oc->transitionTo(OrdenCompraEstatus::Cancelada))
            ->toThrow(InvalidStateTransitionException::class);
    });

    test('Factura transiciona pendiente_entrega -> pendiente_aprobacion', function () {
        $factura = Factura::factory()->create(['estatus' => 'pendiente_entrega']);

        $factura->transitionTo(FacturaEstatus::PendienteAprobacion);

        expect($factura->fresh()->estatus)->toBe(FacturaEstatus::PendienteAprobacion);
    });

    test('Factura no puede saltar de pendiente_entrega a pagada', function () {
        $factura = Factura::factory()->create(['estatus' => 'pendiente_entrega']);

        expect(fn () => $factura->transitionTo(FacturaEstatus::Pagada))
            ->toThrow(InvalidStateTransitionException::class);
    });

    test('Pago: programado -> pagado', function () {
        $pago = Pago::factory()->create(['estatus' => 'programado']);

        $pago->transitionTo(PagoEstatus::Pagado);

        expect($pago->fresh()->estatus)->toBe(PagoEstatus::Pagado);
    });

    test('SolicitudPago: borrador -> pendiente_firma', function () {
        $solicitud = SolicitudPago::factory()->create(['estatus' => 'borrador']);

        $solicitud->transitionTo(SolicitudPagoEstatus::PendienteFirma);

        expect($solicitud->fresh()->estatus)->toBe(SolicitudPagoEstatus::PendienteFirma);
    });

    test('Afectacion: borrador -> pendiente_firma', function () {
        $afectacion = AfectacionPresupuestal::factory()->create(['estatus' => 'borrador']);

        $afectacion->transitionTo(AfectacionEstatus::PendienteFirma);

        expect($afectacion->fresh()->estatus)->toBe(AfectacionEstatus::PendienteFirma);
    });

    test('excepcion incluye fromState, toState y entity', function () {
        $oc = OrdenCompra::factory()->create(['estatus' => 'pagada']);

        try {
            $oc->transitionTo(OrdenCompraEstatus::Cancelada);
            expect(false)->toBeTrue('Se esperaba excepcion');
        } catch (InvalidStateTransitionException $e) {
            expect($e->fromState)->toBe('pagada');
            expect($e->toState)->toBe('cancelada');
            expect($e->entity)->toBe('OrdenCompra');
        }
    });
});
