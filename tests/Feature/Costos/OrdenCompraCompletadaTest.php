<?php

use App\Enums\Costos\FacturaEstatus;
use App\Models\Costos\Entrega;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;

/**
 * Cuándo una orden de compra terminó su vida: se recibió toda la cantidad de sus
 * renglones, se facturó por el total y se pagaron todas sus facturas.
 *
 * Es un derivado y no un estatus a propósito. `OrdenCompraEstatus` sigue el
 * ciclo de la factura, no el del material: una orden de 100 con 60 recibidas y
 * su factura de 60 ya aprobada pasa a `pendiente_aprobacion` con 40 piezas
 * todavía por llegar.
 */
function ordenDe(float $cantidad = 10, float $precio = 100): array
{
    $oc = OrdenCompra::factory()->pendienteFactura()->create(['total' => $cantidad * $precio]);
    $partida = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'cantidad' => $cantidad,
        'precio_unitario' => $precio,
        'subtotal' => $cantidad * $precio,
    ]);

    return [$oc, $partida];
}

function recibirEn(OrdenCompra $oc, OrdenCompraDetalle $partida, float $cantidad): Entrega
{
    $entrega = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    $entrega->detalles()->create([
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => $cantidad,
    ]);

    return $entrega;
}

test('la orden recibida, facturada y pagada al total está completada', function () {
    [$oc, $partida] = ordenDe(10, 100);

    recibirEn($oc, $partida, 10);
    Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'total' => 1000,
        'estatus' => FacturaEstatus::Pagada->value,
    ]);

    $oc = $oc->fresh();

    expect($oc->recepcionCompleta())->toBeTrue()
        ->and($oc->facturacionCompleta())->toBeTrue()
        ->and($oc->pagoCompleto())->toBeTrue()
        ->and($oc->completada)->toBeTrue();
});

test('si falta material por recibir no está completada', function () {
    [$oc, $partida] = ordenDe(10, 100);

    recibirEn($oc, $partida, 6);
    Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'total' => 1000,
        'estatus' => FacturaEstatus::Pagada->value,
    ]);

    $oc = $oc->fresh();

    expect($oc->recepcionCompleta())->toBeFalse()
        ->and($oc->completada)->toBeFalse();
});

test('si el proveedor no facturó el total no está completada', function () {
    [$oc, $partida] = ordenDe(10, 100);

    recibirEn($oc, $partida, 10);
    Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'total' => 600,
        'estatus' => FacturaEstatus::Pagada->value,
    ]);

    $oc = $oc->fresh();

    expect($oc->facturacionCompleta())->toBeFalse()
        ->and($oc->completada)->toBeFalse();
});

test('si una factura sigue sin pagarse no está completada', function () {
    [$oc, $partida] = ordenDe(10, 100);

    recibirEn($oc, $partida, 10);
    Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'total' => 600,
        'estatus' => FacturaEstatus::Pagada->value,
    ]);
    Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'total' => 400,
        'estatus' => FacturaEstatus::PendientePago->value,
    ]);

    $oc = $oc->fresh();

    expect($oc->facturacionCompleta())->toBeTrue()
        ->and($oc->pagoCompleto())->toBeFalse()
        ->and($oc->completada)->toBeFalse();
});

test('la orden sin una sola factura no cuenta como pagada', function () {
    [$oc, $partida] = ordenDe(10, 100);

    recibirEn($oc, $partida, 10);

    expect($oc->fresh()->pagoCompleto())->toBeFalse();
});

/**
 * Lo que decide si la orden sigue esperando material es sólo el primer eje: el
 * saldo de sus renglones. Que esté pagada por adelantado no la saca de la lista.
 */
test('pendientesDeRecibir separa lo que falta de lo que ya llegó', function () {
    [$completa, $partidaCompleta] = ordenDe(10, 100);
    recibirEn($completa, $partidaCompleta, 10);

    [$parcial, $partidaParcial] = ordenDe(10, 100);
    recibirEn($parcial, $partidaParcial, 4);

    [$pagadaSinRecibir, $partidaPagada] = ordenDe(10, 100);
    $pagadaSinRecibir->update(['estatus' => 'pagada']);
    recibirEn($pagadaSinRecibir, $partidaPagada, 3);

    $abiertas = OrdenCompra::query()->pendientesDeRecibir()->pluck('id');

    expect($abiertas)->not->toContain($completa->id)
        ->and($abiertas)->toContain($parcial->id)
        ->and($abiertas)->toContain($pagadaSinRecibir->id);
});

test('la recepción cancelada devuelve la orden a la lista de por recibir', function () {
    [$oc, $partida] = ordenDe(10, 100);
    $entrega = recibirEn($oc, $partida, 10);

    expect(OrdenCompra::query()->pendientesDeRecibir()->pluck('id'))->not->toContain($oc->id);

    $entrega->update(['cancelada_at' => now()]);

    expect(OrdenCompra::query()->pendientesDeRecibir()->pluck('id'))->toContain($oc->id)
        ->and($oc->fresh()->recepcionCompleta())->toBeFalse();
});
