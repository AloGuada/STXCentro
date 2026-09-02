<?php

use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Pago;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'costos.entregas.crear', 'guard_name' => 'web']);
});

function ocConPartida(float $cantidad = 10): array
{
    $oc = OrdenCompra::factory()->pendienteFactura()->create(['total' => $cantidad * 100]);
    $partida = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'cantidad' => $cantidad,
        'precio_unitario' => 100,
        'subtotal' => $cantidad * 100,
    ]);

    // Toda recepción va contra una factura de la OC.
    $factura = Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'estatus' => 'pendiente_recepcion',
    ]);

    return [$oc, $partida, $factura];
}

/**
 * La fecha del documento es la de la transacción y la elige quien captura: el
 * camión llegó el viernes y el almacén lo asienta el lunes. Cuándo se capturó
 * queda aparte, en `created_at`, y ese sí lo pone el servidor.
 */
test('la recepcion respeta la fecha capturada y sella aparte la de registro', function () {
    [$oc, $partida, $factura] = ocConPartida();

    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', [
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2020-01-01',
            'factura_id' => $factura->id,
            'tipo' => 'parcial',
            'detalles' => [
                ['orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 1],
            ],
        ])
        ->assertRedirect();

    $entrega = $oc->entregas()->sole();

    expect($entrega->fecha_entrega->toDateString())->toBe('2020-01-01')
        ->and($entrega->created_at->toDateString())->toBe(today()->toDateString());
});

test('registra entrega con detalle de partida contra la OC', function () {
    [$oc, $partida, $factura] = ocConPartida();

    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', [
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2026-02-17',
            'factura_id' => $factura->id,
            'tipo' => 'parcial',
            'observaciones' => 'Primer lote',
            'detalles' => [
                [
                    'orden_compra_detalle_id' => $partida->id,
                    'cantidad_recibida' => 4,
                ],
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('costos_entregas', [
        'orden_compra_id' => $oc->id,
        'tipo' => 'parcial',
        'observaciones' => 'Primer lote',
    ]);

    $this->assertDatabaseHas('costos_entrega_detalle', [
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => 4,
    ]);
});

test('rechaza entrega que supera la cantidad ordenada en la partida', function () {
    [$oc, $partida, $factura] = ocConPartida(10);

    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', [
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2026-02-17',
            'factura_id' => $factura->id,
            'tipo' => 'completa',
            'detalles' => [
                [
                    'orden_compra_detalle_id' => $partida->id,
                    'cantidad_recibida' => 15,
                ],
            ],
        ])
        ->assertSessionHasErrors('detalles.0.cantidad_recibida');
});

test('rechaza entrega si acumulado excede cantidad ordenada', function () {
    [$oc, $partida, $factura] = ocConPartida(10);

    // Primera entrega parcial de 7
    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', [
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2026-02-17',
            'factura_id' => $factura->id,
            'tipo' => 'parcial',
            'detalles' => [
                ['orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 7],
            ],
        ])
        ->assertRedirect();

    // Segunda intenta 5 más → 7 + 5 = 12 > 10
    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', [
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2026-02-18',
            'factura_id' => $factura->id,
            'tipo' => 'parcial',
            'detalles' => [
                ['orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 5],
            ],
        ])
        ->assertSessionHasErrors('detalles.0.cantidad_recibida');
});

test('rechaza entrega con partida de otra OC', function () {
    [$oc, $partida, $factura] = ocConPartida();
    $otraPartida = OrdenCompraDetalle::factory()->create();

    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', [
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2026-02-17',
            'factura_id' => $factura->id,
            'tipo' => 'parcial',
            'detalles' => [
                ['orden_compra_detalle_id' => $otraPartida->id, 'cantidad_recibida' => 1],
            ],
        ])
        ->assertSessionHasErrors('detalles.0.orden_compra_detalle_id');
});

test('entrega no crea pago ni cambia estatus de factura', function () {
    // En Fase 4.2 el registro de entrega NO promueve la factura.
    // La transicion pendiente_entrega -> pendiente_aprobacion llega en Fase 4.4
    // via cobertura computada por partida.
    [$oc, $partida] = ocConPartida();
    $factura = Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'estatus' => 'pendiente_aprobacion',
    ]);

    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', [
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2026-02-17',
            'factura_id' => $factura->id,
            'tipo' => 'completa',
            'detalles' => [
                ['orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 10],
            ],
        ])
        ->assertRedirect();

    expect($factura->fresh()->estatus->value)->toBe('pendiente_aprobacion');
    expect(Pago::where('pagable_type', Factura::class)->where('pagable_id', $factura->id)->count())->toBe(0);
});

test('tipo y detalles son requeridos', function () {
    [$oc] = ocConPartida();

    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', [
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2026-02-17',
        ])
        ->assertSessionHasErrors(['tipo', 'detalles'])
        ->assertSessionDoesntHaveErrors('factura_id');
});

test('registra la entrega sin factura ligada', function () {
    [$oc, $partida] = ocConPartida();

    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', [
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2026-02-17',
            'tipo' => 'parcial',
            'detalles' => [
                ['orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 4],
            ],
        ])
        ->assertSessionHasNoErrors();

    $entrega = $oc->entregas()->sole();

    expect($entrega->factura_id)->toBeNull()
        ->and($entrega->completa_factura)->toBeFalse();
});

test('rechaza recepcion contra una factura de otra orden de compra', function () {
    [$oc, $partida] = ocConPartida();
    $ajena = Factura::factory()->create(['estatus' => 'pendiente_recepcion']);

    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', [
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2026-02-17',
            'factura_id' => $ajena->id,
            'tipo' => 'parcial',
            'detalles' => [
                ['orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 1],
            ],
        ])
        ->assertSessionHasErrors('factura_id');

    expect($oc->entregas()->count())->toBe(0);
});
