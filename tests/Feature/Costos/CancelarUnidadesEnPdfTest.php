<?php

use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionDetalle;
use App\Models\User;
use App\Services\Costos\CanceladorDeUnidades;
use Spatie\Permission\Models\Permission;

/**
 * Lo cancelado tiene que verse en los dos papeles que se imprimen y se mandan:
 * la orden de compra y el comparativo de su requisición.
 *
 * En la requisición no se guarda nada: se deriva de los renglones de orden que
 * nacieron de cada partida (`requisicion_detalle_id`).
 */
function usuarioQueImprime(): User
{
    foreach (['costos.ordenes-compra.ver', 'costos.ordenes-compra.ver-todas', 'costos.requisiciones.ver', 'costos.requisiciones.ver-todas'] as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }

    $usuario = User::factory()->create();
    $usuario->givePermissionTo(['costos.ordenes-compra.ver', 'costos.ordenes-compra.ver-todas', 'costos.requisiciones.ver', 'costos.requisiciones.ver-todas']);

    return $usuario;
}

/** Una requisición con su orden, ligadas por `requisicion_detalle_id`. */
function requisicionConOrden(float $cantidad = 100, float $precio = 10): array
{
    $requisicion = Requisicion::factory()->create();
    $partidaReq = RequisicionDetalle::factory()->create([
        'requisicion_id' => $requisicion->id,
        'descripcion' => 'Placa de prueba',
        'unidad' => 'pza',
        'cantidad' => $cantidad,
    ]);

    $oc = OrdenCompra::factory()->pendienteEntrega()->create([
        'requisicion_id' => $requisicion->id,
        'total' => round($cantidad * $precio * 1.16, 2),
        'moneda' => 'mxn',
        'tipo_cambio' => 1,
    ]);

    $partidaOc = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'requisicion_detalle_id' => $partidaReq->id,
        'descripcion' => 'Placa de prueba',
        'unidad' => 'pza',
        'cantidad' => $cantidad,
        'precio_unitario' => $precio,
        'subtotal' => $cantidad * $precio,
    ]);

    return [$requisicion, $partidaReq, $oc->fresh(), $partidaOc];
}

test('la requisicion deriva lo cancelado de su orden, sin guardarlo', function () {
    [, $partidaReq, , $partidaOc] = requisicionConOrden(100, 10);

    expect($partidaReq->cantidadCanceladaEnOc())->toBe(0.0)
        ->and($partidaReq->cantidadVigenteEnOc())->toBe(100.0);

    $cancelacion = app(CanceladorDeUnidades::class)->solicitar($partidaOc, 40, 'El proveedor ya no surte el resto');
    app(CanceladorDeUnidades::class)->autorizar($cancelacion);

    $partidaReq = $partidaReq->fresh();

    expect($partidaReq->cantidadCanceladaEnOc())->toBe(40.0)
        ->and($partidaReq->cantidadVigenteEnOc())->toBe(60.0)
        // la cantidad de la requisición no se movió: es lo que se pidió
        ->and((float) $partidaReq->cantidad)->toBe(100.0);
});

test('lo pendiente de firma todavia no cuenta en la requisicion', function () {
    [, $partidaReq, , $partidaOc] = requisicionConOrden(100, 10);

    app(CanceladorDeUnidades::class)->solicitar($partidaOc, 40, 'El proveedor ya no surte el resto');

    expect($partidaReq->fresh()->cantidadCanceladaEnOc())->toBe(0.0);
});

test('el pdf de la orden imprime lo cancelado y cuadra sus importes con el total', function () {
    [, , $oc, $partidaOc] = requisicionConOrden(100, 10);

    $cancelacion = app(CanceladorDeUnidades::class)->solicitar($partidaOc, 40, 'El proveedor descontinuó la medida');
    app(CanceladorDeUnidades::class)->autorizar($cancelacion);

    $respuesta = $this->actingAs(usuarioQueImprime())
        ->get(route('admin.costos.ordenes-compra.pdf-oc', $oc));

    $respuesta->assertOk();
    expect($respuesta->headers->get('content-type'))->toContain('application/pdf');

    // El total de la orden ya bajó: el papel tiene que sumar lo mismo.
    expect((float) $oc->fresh()->total)->toBe(696.0);
});

test('el comparativo de la requisicion imprime lo cancelado en la orden', function () {
    [$requisicion, , , $partidaOc] = requisicionConOrden(100, 10);

    $cancelacion = app(CanceladorDeUnidades::class)->solicitar($partidaOc, 40, 'El proveedor descontinuó la medida');
    app(CanceladorDeUnidades::class)->autorizar($cancelacion);

    $this->actingAs(usuarioQueImprime())
        ->get(route('admin.costos.requisiciones.pdf', $requisicion))
        ->assertOk();
});

test('la pantalla de la requisicion trae lo cancelado de cada partida', function () {
    [$requisicion, $partidaReq, $oc, $partidaOc] = requisicionConOrden(100, 10);

    $cancelacion = app(CanceladorDeUnidades::class)->solicitar($partidaOc, 40, 'El proveedor descontinuó la medida');
    app(CanceladorDeUnidades::class)->autorizar($cancelacion);

    $this->actingAs(usuarioQueImprime())
        ->get(route('admin.costos.requisiciones.show', $requisicion))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('requisicion.detalles.0.cantidad', '100.0000')
            ->where('requisicion.detalles.0.orden_compra_detalles.0.cantidad_cancelada', '40.0000')
            ->where('requisicion.detalles.0.orden_compra_detalles.0.orden_compra.folio', $oc->folio));
});
