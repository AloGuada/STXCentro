<?php

use App\Enums\Costos\RubroAfectadoEstatus;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\SolicitudPago;
use App\Models\Costos\SolicitudPagoDetalle;
use App\Models\User;
use App\Services\Costos\ReasignacionCentroCostos;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'costos.centros-costos.reasignar', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'costos.solicitudes-pago.editar', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->service = app(ReasignacionCentroCostos::class);
});

/**
 * Crea una solicitud de pago con impacto presupuestal ya aplicado (Aplicado)
 * sobre uno o más centros de costos.
 *
 * @param  array<int, array{rubro: ObraRubro, monto: float}>  $lineas
 */
function solicitudAplicada(array $lineas, string $estatus = 'aprobada'): SolicitudPago
{
    $total = array_sum(array_map(fn ($l) => $l['monto'], $lineas));

    $sp = SolicitudPago::factory()->create([
        'estatus' => $estatus,
        'monto_total' => $total,
        'orden_compra_id' => null,
    ]);

    foreach ($lineas as $l) {
        SolicitudPagoDetalle::factory()->create([
            'solicitud_id' => $sp->id,
            'obra_rubro_id' => $l['rubro']->id,
            'cantidad' => 1,
            'precio_unitario' => $l['monto'],
            'subtotal' => $l['monto'],
        ]);
    }

    $sp->load('detalles');
    $sp->aplicarImpactoPresupuestal();

    return $sp;
}

test('reasignar mueve el acumulado del rubro viejo al nuevo (neto cuadra)', function () {
    $origen = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $destino = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);

    $sp = solicitudAplicada([['rubro' => $origen, 'monto' => 5000]]);
    expect((float) $origen->fresh()->acumulado)->toBe(5000.00);

    $this->service->reasignar($sp, [
        ['obra_rubro_id' => $destino->id, 'monto' => 5000, 'concepto' => 'Corrección'],
    ], 'Se capturó el centro de costos equivocado');

    expect((float) $origen->fresh()->acumulado)->toBe(0.00);
    expect((float) $destino->fresh()->acumulado)->toBe(5000.00);
});

test('reasignar cancela los rubros afectados viejos y crea nuevos aplicados', function () {
    $origen = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $destino = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);

    $sp = solicitudAplicada([['rubro' => $origen, 'monto' => 4000]]);

    $this->service->reasignar($sp, [
        ['obra_rubro_id' => $destino->id, 'monto' => 4000, 'concepto' => 'Corrección'],
    ], 'Motivo de la reasignación de prueba');

    $afectados = $sp->rubrosAfectados()->get();

    $viejo = $afectados->firstWhere('obra_rubro_id', $origen->id);
    $nuevo = $afectados->firstWhere('obra_rubro_id', $destino->id);

    expect($viejo->estatus)->toBe(RubroAfectadoEstatus::Cancelado);
    expect($nuevo->estatus)->toBe(RubroAfectadoEstatus::Aplicado);
    expect((float) $nuevo->monto)->toBe(4000.00);
});

test('reasignar queda registrado en el historial con su motivo', function () {
    $origen = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $destino = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);

    $sp = solicitudAplicada([['rubro' => $origen, 'monto' => 5000]]);

    $this->service->reasignar($sp, [
        ['obra_rubro_id' => $destino->id, 'monto' => 5000, 'concepto' => 'Corrección'],
    ], 'Se capturó el centro de costos equivocado');

    $actividad = $sp->activities()
        ->where('description', 'Centros de costos reasignados')
        ->first();

    expect($actividad)->not->toBeNull();
    expect(data_get($actividad->properties, 'motivo'))->toBe('Se capturó el centro de costos equivocado');
});

test('reasignar permite dividir en varios centros de costos y conserva el total', function () {
    $origen = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $b = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $c = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);

    $sp = solicitudAplicada([['rubro' => $origen, 'monto' => 5000]]);

    $this->service->reasignar($sp, [
        ['obra_rubro_id' => $b->id, 'monto' => 3000, 'concepto' => 'Parte 1'],
        ['obra_rubro_id' => $c->id, 'monto' => 2000, 'concepto' => 'Parte 2'],
    ], 'Se reparte entre dos centros de costos');

    expect((float) $origen->fresh()->acumulado)->toBe(0.00);
    expect((float) $b->fresh()->acumulado)->toBe(3000.00);
    expect((float) $c->fresh()->acumulado)->toBe(2000.00);
    expect((float) $sp->fresh()->monto_total)->toBe(5000.00);
    expect($sp->detalles()->count())->toBe(2);
});

test('reasignar no cambia el monto de la solicitud aunque el reparto sume distinto', function () {
    $origen = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $destino = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);

    // El desglose de una solicitud no siempre suma lo que se pidió pagar: lo
    // que se corrige aquí es a qué centro de costos se carga, no el dinero.
    $sp = solicitudAplicada([['rubro' => $origen, 'monto' => 5000]]);

    $this->service->reasignar($sp, [
        ['obra_rubro_id' => $destino->id, 'monto' => 3000, 'concepto' => 'Sólo esta parte era de este centro'],
    ], 'Se corrige el centro de costos del comprobante');

    expect((float) $sp->fresh()->monto_total)->toBe(5000.00);
    expect((float) $origen->fresh()->acumulado)->toBe(0.00);
    expect((float) $destino->fresh()->acumulado)->toBe(3000.00);
});

test('reasignar una solicitud en divisa conserva el cargo convertido a mxn', function () {
    $origen = ObraRubro::factory()->create(['presupuestado' => 1000000, 'acumulado' => 0]);
    $destino = ObraRubro::factory()->create(['presupuestado' => 1000000, 'acumulado' => 0]);

    // 1,000 USD al tipo de cambio con que se firmó pesan 17,500 MXN en el
    // presupuesto. Mover el gasto de centro no lo re-cotiza: el destino tiene
    // que recibir los mismos pesos que soltó el origen.
    $sp = SolicitudPago::factory()->create([
        'estatus' => 'aprobada',
        'monto_total' => 1000,
        'tipo_moneda' => 'usd',
        'tipo_cambio' => 17.5,
        'orden_compra_id' => null,
    ]);

    SolicitudPagoDetalle::factory()->create([
        'solicitud_id' => $sp->id,
        'obra_rubro_id' => $origen->id,
        'cantidad' => 1,
        'precio_unitario' => 1000,
        'subtotal' => 1000,
    ]);

    $sp->load('detalles');
    $sp->aplicarImpactoPresupuestal();

    expect((float) $origen->fresh()->acumulado)->toBe(17500.00);

    app(ReasignacionCentroCostos::class)->reasignar($sp, [
        ['obra_rubro_id' => $destino->id, 'monto' => 1000, 'concepto' => 'Corrección'],
    ], 'Se corrige el centro de costos capturado');

    expect((float) $origen->fresh()->acumulado)->toBe(0.00);
    expect((float) $destino->fresh()->acumulado)->toBe(17500.00);

    $nuevo = $sp->rubrosAfectados()->where('obra_rubro_id', $destino->id)->first();
    expect($nuevo->moneda)->toBe('usd');
    expect((float) $nuevo->tipo_cambio)->toBe(17.5);
    expect((float) $nuevo->monto_origen)->toBe(1000.00);
    expect((float) $sp->fresh()->monto_total)->toBe(1000.00);
});

test('la bitácora guarda el monto de la solicitud junto a la suma repartida', function () {
    $origen = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $destino = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);

    $sp = solicitudAplicada([['rubro' => $origen, 'monto' => 5000]]);

    $this->service->reasignar($sp, [
        ['obra_rubro_id' => $destino->id, 'monto' => 3000],
    ], 'Motivo de prueba suficientemente largo');

    $actividad = $sp->activities()
        ->where('description', 'Centros de costos reasignados')
        ->first();

    expect((float) data_get($actividad->properties, 'monto_total'))->toBe(5000.00);
    expect((float) data_get($actividad->properties, 'suma_detalles'))->toBe(3000.00);
});

test('reasignar permite sobregiro en el destino y marca la bandera', function () {
    $origen = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $destino = ObraRubro::factory()->create(['presupuestado' => 1000, 'acumulado' => 0]);

    $sp = solicitudAplicada([['rubro' => $origen, 'monto' => 5000]]);

    $this->service->reasignar($sp, [
        ['obra_rubro_id' => $destino->id, 'monto' => 5000, 'concepto' => 'Excede'],
    ], 'Reasignación que sobregira el destino');

    $nuevo = $sp->rubrosAfectados()->where('obra_rubro_id', $destino->id)->first();
    expect($nuevo->sobre_giro)->toBeTrue();
    expect((float) $destino->fresh()->acumulado)->toBe(5000.00);
    expect($destino->fresh()->disponible)->toBeLessThan(0);
});

test('reasignar aborta si la solicitud no está aprobada ni pagada', function () {
    $origen = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $destino = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);

    $sp = SolicitudPago::factory()->create(['estatus' => 'borrador', 'orden_compra_id' => null]);
    SolicitudPagoDetalle::factory()->create([
        'solicitud_id' => $sp->id,
        'obra_rubro_id' => $origen->id,
        'subtotal' => 5000,
    ]);

    expect(fn () => $this->service->reasignar($sp, [
        ['obra_rubro_id' => $destino->id, 'monto' => 5000],
    ], 'Motivo de prueba suficiente'))->toThrow(HttpException::class);
});

test('reasignar aborta en solicitudes generadas desde una orden de compra', function () {
    $origen = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $destino = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);

    $sp = solicitudAplicada([['rubro' => $origen, 'monto' => 5000]]);
    // Simula una SP de OC: no es dueña del presupuesto.
    $sp->orden_compra_id = 999;

    expect(fn () => $this->service->reasignar($sp, [
        ['obra_rubro_id' => $destino->id, 'monto' => 5000],
    ], 'Motivo de prueba suficiente'))->toThrow(HttpException::class);

    // El acumulado del origen no se tocó.
    expect((float) $origen->fresh()->acumulado)->toBe(5000.00);
});

test('en una solicitud pagada solo se puede redistribuir (la suma debe igualar el total)', function () {
    $origen = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $destino = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);

    $sp = solicitudAplicada([['rubro' => $origen, 'monto' => 5000]], 'pagada');

    // Suma distinta al total pagado → aborta.
    expect(fn () => $this->service->reasignar($sp, [
        ['obra_rubro_id' => $destino->id, 'monto' => 3000],
    ], 'Intento de bajar el total pagado'))->toThrow(HttpException::class);

    // Suma igual al total → ok, redistribuye.
    $this->service->reasignar($sp, [
        ['obra_rubro_id' => $destino->id, 'monto' => 5000, 'concepto' => 'Redistribución'],
    ], 'Redistribución conservando el total');

    expect((float) $origen->fresh()->acumulado)->toBe(0.00);
    expect((float) $destino->fresh()->acumulado)->toBe(5000.00);
    expect((float) $sp->fresh()->monto_total)->toBe(5000.00);
});

test('el endpoint requiere el permiso costos.centros-costos.reasignar', function () {
    $origen = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $destino = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);

    $sp = solicitudAplicada([['rubro' => $origen, 'monto' => 5000]]);

    // Sin permiso → 403.
    $this->actingAs($this->user)
        ->post(route('admin.costos.solicitudes-pago.reasignar', $sp), [
            'motivo' => 'Motivo suficiente para pasar',
            'detalles' => [['obra_rubro_id' => $destino->id, 'monto' => 5000]],
        ])
        ->assertForbidden();

    expect((float) $origen->fresh()->acumulado)->toBe(5000.00);
});

test('el endpoint reasigna con permiso y motivo válido', function () {
    $this->user->givePermissionTo('costos.centros-costos.reasignar');

    $origen = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $destino = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);

    $sp = solicitudAplicada([['rubro' => $origen, 'monto' => 5000]]);

    $this->actingAs($this->user)
        ->post(route('admin.costos.solicitudes-pago.reasignar', $sp), [
            'motivo' => 'Se corrige el centro de costos mal capturado',
            'detalles' => [['obra_rubro_id' => $destino->id, 'monto' => 5000, 'concepto' => 'Corrección']],
        ])
        ->assertRedirect();

    expect((float) $origen->fresh()->acumulado)->toBe(0.00);
    expect((float) $destino->fresh()->acumulado)->toBe(5000.00);
});

test('el endpoint valida que el motivo tenga al menos 10 caracteres', function () {
    $this->user->givePermissionTo('costos.centros-costos.reasignar');

    $origen = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $destino = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);

    $sp = solicitudAplicada([['rubro' => $origen, 'monto' => 5000]]);

    $this->actingAs($this->user)
        ->post(route('admin.costos.solicitudes-pago.reasignar', $sp), [
            'motivo' => 'corto',
            'detalles' => [['obra_rubro_id' => $destino->id, 'monto' => 5000]],
        ])
        ->assertSessionHasErrors('motivo');

    expect((float) $origen->fresh()->acumulado)->toBe(5000.00);
});

test('cancelar una solicitud aprobada revierte el acumulado por cada centro de costos', function () {
    $this->user->givePermissionTo('costos.solicitudes-pago.editar');

    $a = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $b = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);

    $sp = solicitudAplicada([
        ['rubro' => $a, 'monto' => 3000],
        ['rubro' => $b, 'monto' => 2000],
    ]);

    expect((float) $a->fresh()->acumulado)->toBe(3000.00);
    expect((float) $b->fresh()->acumulado)->toBe(2000.00);

    $this->actingAs($this->user)
        ->post(route('admin.costos.solicitudes-pago.cancelar', $sp), [
            'motivo' => 'Cancelación de prueba con motivo suficiente',
        ])
        ->assertRedirect();

    expect((float) $a->fresh()->acumulado)->toBe(0.00);
    expect((float) $b->fresh()->acumulado)->toBe(0.00);
    expect($sp->rubrosAfectados()->where('estatus', RubroAfectadoEstatus::Aplicado->value)->count())->toBe(0);
});
