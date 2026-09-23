<?php

use App\Enums\Costos\CancelacionUnidadesEstatus;
use App\Enums\Costos\FacturaEstatus;
use App\Enums\Costos\OrdenCompraEstatus;
use App\Models\Costos\Entrega;
use App\Models\Costos\Factura;
use App\Models\Costos\NotaCredito;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\OrdenCompraDetalleCancelacion;
use App\Models\User;
use App\Services\Costos\CanceladorDeUnidades;
use App\Services\Costos\RegistradorRecepcion;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

/**
 * Compras cancela las unidades que el proveedor ya no va a surtir: de una
 * partida de 100 con 60 recibidas, las 40 que faltan.
 *
 * Nada surte efecto sin la firma del jefe de compras. Mientras la cancelación
 * está pendiente, la orden se reporta pendiente de aprobación; al autorizarla
 * bajan el saldo por recibir, el total de la orden y el presupuesto.
 */
function usuarioDeCompras(array $permisos = ['costos.ordenes-compra.cancelar']): User
{
    foreach ($permisos as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }

    $usuario = User::factory()->create();
    $usuario->givePermissionTo($permisos);

    return $usuario;
}

/** Una orden con una partida, su centro de costos y su impacto ya aplicado. */
function ordenConPresupuesto(float $cantidad = 100, float $precio = 10): array
{
    $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $oc = OrdenCompra::factory()->pendienteEntrega()->create([
        'total' => $cantidad * $precio,
        'moneda' => 'mxn',
        'tipo_cambio' => 1,
    ]);
    $partida = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'obra_rubro_id' => $obraRubro->id,
        'cantidad' => $cantidad,
        'precio_unitario' => $precio,
        'subtotal' => $cantidad * $precio,
        'sin_impuestos' => true,
    ]);

    $oc->load('detalles');
    $oc->aplicarImpactoPresupuestal();

    return [$oc->fresh(), $partida->fresh(), $obraRubro->fresh()];
}

function recibirUnidades(OrdenCompra $oc, OrdenCompraDetalle $partida, float $cantidad): Entrega
{
    $entrega = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    $entrega->detalles()->create([
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => $cantidad,
    ]);

    return $entrega;
}

test('solo se puede cancelar lo que no ha llegado', function () {
    [$oc, $partida] = ordenConPresupuesto(100, 10);
    recibirUnidades($oc, $partida, 60);

    $cancelador = app(CanceladorDeUnidades::class);

    expect($cancelador->cancelable($partida->fresh()))->toBe(40.0);

    $cancelador->solicitar($partida->fresh(), 41, 'El proveedor ya no surte el resto');
})->throws(ValidationException::class, 'Sólo quedan 40.00');

test('la cancelación pendiente deja la orden en pendiente de aprobacion', function () {
    [$oc, $partida] = ordenConPresupuesto(100, 10);
    recibirUnidades($oc, $partida, 60);
    $oc->recalcularEstatus();

    expect($oc->fresh()->estatus)->toBe(OrdenCompraEstatus::PendienteFactura);

    app(CanceladorDeUnidades::class)->solicitar($partida->fresh(), 40, 'El proveedor ya no surte el resto');

    expect($oc->fresh()->estatus)->toBe(OrdenCompraEstatus::PendienteAprobacion)
        ->and($oc->fresh()->tieneCancelacionPendiente())->toBeTrue();

    // Un recálculo posterior no la saca de ahí mientras nadie firme.
    $oc->fresh()->recalcularEstatus();

    expect($oc->fresh()->estatus)->toBe(OrdenCompraEstatus::PendienteAprobacion);
});

test('mientras no se autoriza no cambia cantidad, total ni presupuesto', function () {
    [$oc, $partida, $obraRubro] = ordenConPresupuesto(100, 10);
    recibirUnidades($oc, $partida, 60);

    app(CanceladorDeUnidades::class)->solicitar($partida->fresh(), 40, 'El proveedor ya no surte el resto');

    expect((float) $partida->fresh()->cantidad_cancelada)->toBe(0.0)
        ->and((float) $oc->fresh()->total)->toBe(1000.0)
        ->and((float) $obraRubro->fresh()->acumulado)->toBe(1000.0);
});

test('autorizar cancela las unidades, baja el total y revierte el presupuesto', function () {
    [$oc, $partida, $obraRubro] = ordenConPresupuesto(100, 10);
    recibirUnidades($oc, $partida, 60);
    $jefe = usuarioDeCompras(['costos.ordenes-compra.autorizar-cancelacion']);

    $cancelacion = app(CanceladorDeUnidades::class)->solicitar($partida->fresh(), 40, 'El proveedor ya no surte el resto');
    app(CanceladorDeUnidades::class)->autorizar($cancelacion, $jefe->getKey());

    $partida = $partida->fresh();
    $oc = $oc->fresh();

    expect((float) $partida->cantidad)->toBe(100.0)
        ->and((float) $partida->cantidad_cancelada)->toBe(40.0)
        ->and($partida->cantidadVigente())->toBe(60.0)
        ->and((float) $partida->subtotal)->toBe(600.0)
        // el total baja por las 40 unidades canceladas
        ->and((float) $oc->total)->toBe(600.0)
        // y el presupuesto deja de cargar esas unidades
        ->and((float) $obraRubro->fresh()->acumulado)->toBe(600.0)
        ->and($cancelacion->fresh()->estatus)->toBe(CancelacionUnidadesEstatus::Autorizada)
        ->and($cancelacion->fresh()->autorizado_por)->toBe($jefe->getKey());
});

test('al autorizar, la orden ya no espera material y el avance de entrega queda al 100', function () {
    [$oc, $partida] = ordenConPresupuesto(100, 10);
    recibirUnidades($oc, $partida, 60);

    expect($oc->fresh()->recepcionCompleta())->toBeFalse()
        ->and($oc->fresh()->porcentaje_recepcion)->toBe(60.0);

    $cancelacion = app(CanceladorDeUnidades::class)->solicitar($partida->fresh(), 40, 'El proveedor ya no surte el resto');
    app(CanceladorDeUnidades::class)->autorizar($cancelacion);

    $oc = $oc->fresh();

    expect($oc->recepcionCompleta())->toBeTrue()
        ->and($oc->porcentaje_recepcion)->toBe(100.0)
        ->and(OrdenCompra::query()->whereKey($oc->id)->pendientesDeRecibir()->exists())->toBeFalse();
});

test('lo cancelado baja el tope de la recepcion', function () {
    [$oc, $partida] = ordenConPresupuesto(100, 10);
    recibirUnidades($oc, $partida, 60);

    $cancelacion = app(CanceladorDeUnidades::class)->solicitar($partida->fresh(), 40, 'El proveedor ya no surte el resto');
    app(CanceladorDeUnidades::class)->autorizar($cancelacion);

    app(RegistradorRecepcion::class)->validarCaptura($oc->fresh(), [
        ['orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 1],
    ]);
})->throws(ValidationException::class, 'Excede el saldo pendiente (0.00');

test('rechazar no toca nada y libera el estatus de la orden', function () {
    [$oc, $partida, $obraRubro] = ordenConPresupuesto(100, 10);
    recibirUnidades($oc, $partida, 60);
    $jefe = usuarioDeCompras(['costos.ordenes-compra.autorizar-cancelacion']);

    $cancelacion = app(CanceladorDeUnidades::class)->solicitar($partida->fresh(), 40, 'El proveedor ya no surte el resto');
    app(CanceladorDeUnidades::class)->rechazar($cancelacion, 'El proveedor confirmó que sí lo surte', $jefe->getKey());

    $cancelacion = $cancelacion->fresh();

    expect($cancelacion->estatus)->toBe(CancelacionUnidadesEstatus::Rechazada)
        ->and($cancelacion->motivo_rechazo)->toBe('El proveedor confirmó que sí lo surte')
        ->and((float) $partida->fresh()->cantidad_cancelada)->toBe(0.0)
        ->and((float) $obraRubro->fresh()->acumulado)->toBe(1000.0)
        ->and($oc->fresh()->tieneCancelacionPendiente())->toBeFalse()
        ->and($oc->fresh()->estatus)->toBe(OrdenCompraEstatus::PendienteFactura);
});

test('una cancelacion ya resuelta no se vuelve a autorizar', function () {
    [$oc, $partida] = ordenConPresupuesto(100, 10);
    recibirUnidades($oc, $partida, 60);

    $cancelacion = app(CanceladorDeUnidades::class)->solicitar($partida->fresh(), 40, 'El proveedor ya no surte el resto');
    app(CanceladorDeUnidades::class)->autorizar($cancelacion);
    app(CanceladorDeUnidades::class)->autorizar($cancelacion->fresh());
})->throws(ValidationException::class, 'ya fue resuelta');

/**
 * Cada entrada llega con su factura, así que lo recibido ya está amparado y no
 * se cancela; lo que sigue sin recibir sí, aunque la orden tenga facturas.
 */
test('lo recibido con factura queda amparado y el resto se puede cancelar', function () {
    [$oc, $partida] = ordenConPresupuesto(100, 10);
    $factura = Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'total' => 600,
        'estatus' => FacturaEstatus::PendienteAprobacion->value,
    ]);
    recibirUnidades($oc, $partida, 60)->update(['factura_id' => $factura->id]);

    $desglose = app(CanceladorDeUnidades::class)->desglose($partida->fresh());

    expect($desglose['recibido'])->toBe(60.0)
        ->and($desglose['recibido_facturado'])->toBe(60.0)
        ->and($desglose['sin_recibir'])->toBe(40.0)
        ->and($desglose['tope_facturas'])->toBe(40.0)
        ->and($desglose['cancelable'])->toBe(40.0);

    $cancelacion = app(CanceladorDeUnidades::class)->solicitar($partida->fresh(), 40, 'El proveedor ya no surte el resto');
    app(CanceladorDeUnidades::class)->autorizar($cancelacion);

    expect((float) $partida->fresh()->cantidad_cancelada)->toBe(40.0)
        ->and((float) $oc->fresh()->total)->toBe(600.0);
});

/**
 * El proveedor puede facturar antes de surtir. Esas unidades no han llegado,
 * pero ya se cobraron: cancelarlas dejaría a la orden facturada por más de lo
 * que pidió, así que salen del cancelable.
 */
test('una factura adelantada ampara unidades que no han llegado y las saca del cancelable', function () {
    [$oc, $partida] = ordenConPresupuesto(100, 10);
    $factura = Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'total' => 800,
        'estatus' => FacturaEstatus::PendienteRecepcion->value,
    ]);
    recibirUnidades($oc, $partida, 60)->update(['factura_id' => $factura->id]);

    $desglose = app(CanceladorDeUnidades::class)->desglose($partida->fresh());

    expect($desglose['sin_recibir'])->toBe(40.0)
        ->and($desglose['tope_facturas'])->toBe(20.0)
        ->and($desglose['cancelable'])->toBe(20.0);

    app(CanceladorDeUnidades::class)->solicitar($partida->fresh(), 40, 'El proveedor ya no surte el resto');
})->throws(ValidationException::class, 'caben 20.00');

test('la nota de crédito libera lo que la factura amparaba de más', function () {
    [$oc, $partida] = ordenConPresupuesto(100, 10);
    $factura = Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'total' => 1000,
        'estatus' => FacturaEstatus::PendienteAprobacion->value,
    ]);
    recibirUnidades($oc, $partida, 60)->update(['factura_id' => $factura->id]);

    expect(app(CanceladorDeUnidades::class)->cancelable($partida->fresh()))->toBe(0.0);

    NotaCredito::factory()->create(['factura_id' => $factura->id, 'monto' => 400]);

    expect(app(CanceladorDeUnidades::class)->cancelable($partida->fresh()))->toBe(40.0);
});

test('una factura cancelada no ampara nada', function () {
    [$oc, $partida] = ordenConPresupuesto(100, 10);
    $factura = Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'total' => 1000,
        'estatus' => FacturaEstatus::Cancelada->value,
    ]);
    recibirUnidades($oc, $partida, 60)->update(['factura_id' => $factura->id]);

    $desglose = app(CanceladorDeUnidades::class)->desglose($partida->fresh());

    expect($desglose['recibido_facturado'])->toBe(0.0)
        ->and($desglose['tope_facturas'])->toBeNull()
        ->and($desglose['cancelable'])->toBe(40.0);
});

test('una factura que llega entre la solicitud y la firma frena la autorización', function () {
    [$oc, $partida] = ordenConPresupuesto(100, 10);
    recibirUnidades($oc, $partida, 60);
    $cancelacion = app(CanceladorDeUnidades::class)->solicitar($partida->fresh(), 40, 'El proveedor ya no surte el resto');

    Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'total' => 1000,
        'estatus' => FacturaEstatus::PendienteRecepcion->value,
    ]);

    app(CanceladorDeUnidades::class)->autorizar($cancelacion->fresh());
})->throws(ValidationException::class, 'Llegó una factura que ampara esas unidades');

/**
 * El tope por facturas es de la orden entera: la factura de la partida B no
 * frena cancelar lo que falta de la partida A mientras el total facturado
 * quepa en lo que queda de la orden.
 */
test('el tope por facturas se mide sobre toda la orden y se reparte entre partidas', function () {
    [$oc, $partidaA] = ordenConPresupuesto(100, 10);
    $partidaB = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'obra_rubro_id' => $partidaA->obra_rubro_id,
        'cantidad' => 10,
        'precio_unitario' => 50,
        'subtotal' => 500,
        'sin_impuestos' => true,
    ]);
    $oc->update(['total' => 1500]);
    $factura = Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'total' => 500,
        'estatus' => FacturaEstatus::PendienteAprobacion->value,
    ]);
    recibirUnidades($oc, $partidaB, 10)->update(['factura_id' => $factura->id]);

    $desglose = app(CanceladorDeUnidades::class)->desglosePorOrden($oc->fresh()->load('detalles'));

    expect($desglose[$partidaA->id]['cancelable'])->toBe(100.0)
        ->and($desglose[$partidaB->id]['cancelable'])->toBe(0.0)
        ->and($desglose[$partidaB->id]['recibido_facturado'])->toBe(10.0);

    // Una cancelación pendiente de A ya toma parte del importe libre.
    app(CanceladorDeUnidades::class)->solicitar($partidaA->fresh(), 90, 'El proveedor ya no surte casi nada');

    expect(app(CanceladorDeUnidades::class)->cancelable($partidaA->fresh()))->toBe(10.0);
});

test('dos cancelaciones pendientes no pueden pasarse del saldo juntas', function () {
    [$oc, $partida] = ordenConPresupuesto(100, 10);
    recibirUnidades($oc, $partida, 60);

    app(CanceladorDeUnidades::class)->solicitar($partida->fresh(), 30, 'Primera tanda que ya no llega');

    expect(app(CanceladorDeUnidades::class)->cancelable($partida->fresh()))->toBe(10.0);

    app(CanceladorDeUnidades::class)->solicitar($partida->fresh(), 11, 'Segunda tanda');
})->throws(ValidationException::class, 'Sólo quedan 10.00');

test('compras solicita por la pantalla y el jefe autoriza', function () {
    [$oc, $partida] = ordenConPresupuesto(100, 10);
    recibirUnidades($oc, $partida, 60);

    $this->actingAs(usuarioDeCompras())
        ->post(route('admin.costos.ordenes-compra.cancelaciones.store', $partida), [
            'cantidad' => 40,
            'motivo' => 'El proveedor ya no va a surtir el resto',
        ])
        ->assertRedirect();

    $cancelacion = OrdenCompraDetalleCancelacion::query()->firstOrFail();

    expect($cancelacion->estatus)->toBe(CancelacionUnidadesEstatus::Pendiente);

    // Compras no puede autorizar su propia cancelación.
    $this->actingAs(usuarioDeCompras())
        ->post(route('admin.costos.ordenes-compra.cancelaciones.autorizar', $cancelacion))
        ->assertForbidden();

    $this->actingAs(usuarioDeCompras(['costos.ordenes-compra.autorizar-cancelacion']))
        ->post(route('admin.costos.ordenes-compra.cancelaciones.autorizar', $cancelacion))
        ->assertRedirect();

    expect($cancelacion->fresh()->estatus)->toBe(CancelacionUnidadesEstatus::Autorizada)
        ->and((float) $partida->fresh()->cantidad_cancelada)->toBe(40.0);
});

test('la pantalla de la orden trae lo cancelado y sus cancelaciones', function () {
    [$oc, $partida] = ordenConPresupuesto(100, 10);
    recibirUnidades($oc, $partida, 60);
    $cancelacion = app(CanceladorDeUnidades::class)->solicitar($partida->fresh(), 40, 'El proveedor ya no surte el resto');

    $this->actingAs(usuarioDeCompras(['costos.ordenes-compra.cancelar', 'costos.ordenes-compra.ver-todas']))
        ->get(route('admin.costos.ordenes-compra.show', $oc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/costos/ordenes-compra/show')
            ->where('ordenCompra.detalles.0.cantidad_cancelada', '0.0000')
            ->where('ordenCompra.detalles.0.cancelaciones.0.id', $cancelacion->id)
            ->where('ordenCompra.detalles.0.cancelaciones.0.estatus', 'pendiente')
            ->where('ordenCompra.detalles.0.cancelacion.recibido', 60)
            ->where('ordenCompra.detalles.0.cancelacion.sin_recibir', 0)
            ->where('ordenCompra.detalles.0.cancelacion.cancelable', 0)
            ->where('ordenCompra.estatus', 'pendiente_aprobacion'));
});

/**
 * El listado calcula el avance con los mismos accesores del modelo, pero carga
 * las partidas con un select acotado. Si ese select no trae
 * `cantidad_cancelada`, el accesor la lee como cero y el avance de entrega
 * sigue mostrando el viejo: por eso se comprueba desde la pantalla y no sólo
 * contra el modelo.
 */
test('el listado de ordenes recalcula el avance de entrega con lo cancelado', function () {
    [$oc, $partida] = ordenConPresupuesto(100, 10);
    recibirUnidades($oc, $partida, 60);
    $usuario = usuarioDeCompras(['costos.ordenes-compra.ver', 'costos.ordenes-compra.ver-todas']);

    $this->actingAs($usuario)
        ->get(route('admin.costos.ordenes-compra.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('ordenes.data.0.porcentaje_recepcion', 60));

    $cancelacion = app(CanceladorDeUnidades::class)->solicitar($partida->fresh(), 40, 'El proveedor ya no surte el resto');
    app(CanceladorDeUnidades::class)->autorizar($cancelacion);

    $this->actingAs($usuario)
        ->get(route('admin.costos.ordenes-compra.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('ordenes.data.0.porcentaje_recepcion', 100)
            ->where('ordenes.data.0.detalles.0.cantidad_cancelada', '40.0000'));
});

/**
 * En el listado, una orden con cancelaciones sin firmar se reporta pendiente de
 * aprobación igual que una que espera la aprobación de una factura. El conteo
 * es lo que deja distinguirlas y avisar al jefe de compras que hay algo suyo
 * que firmar.
 */
test('el listado cuenta las cancelaciones que esperan firma', function () {
    [$oc, $partida] = ordenConPresupuesto(100, 10);
    recibirUnidades($oc, $partida, 60);
    $usuario = usuarioDeCompras(['costos.ordenes-compra.ver', 'costos.ordenes-compra.ver-todas']);

    $this->actingAs($usuario)
        ->get(route('admin.costos.ordenes-compra.index'))
        ->assertInertia(fn ($page) => $page->where('ordenes.data.0.cancelaciones_pendientes_count', 0));

    $cancelacion = app(CanceladorDeUnidades::class)->solicitar($partida->fresh(), 40, 'El proveedor ya no surte el resto');

    $this->actingAs($usuario)
        ->get(route('admin.costos.ordenes-compra.index'))
        ->assertInertia(fn ($page) => $page
            ->where('ordenes.data.0.cancelaciones_pendientes_count', 1)
            ->where('ordenes.data.0.estatus', 'pendiente_aprobacion'));

    // Firmada deja de avisar.
    app(CanceladorDeUnidades::class)->autorizar($cancelacion);

    $this->actingAs($usuario)
        ->get(route('admin.costos.ordenes-compra.index'))
        ->assertInertia(fn ($page) => $page->where('ordenes.data.0.cancelaciones_pendientes_count', 0));
});

test('sin permiso de cancelar no se solicita', function () {
    [$oc, $partida] = ordenConPresupuesto(100, 10);

    $this->actingAs(User::factory()->create())
        ->post(route('admin.costos.ordenes-compra.cancelaciones.store', $partida), [
            'cantidad' => 10,
            'motivo' => 'Intento sin permiso ninguno',
        ])
        ->assertForbidden();
});
