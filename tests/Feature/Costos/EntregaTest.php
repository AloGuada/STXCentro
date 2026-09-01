<?php

use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Pago;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Storage::fake('public');

    $this->user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'costos.entregas.crear', 'guard_name' => 'web']);
});

function ocConPartida(float $cantidad = 10): array
{
    // El total de la orden va con impuestos: es contra él que se mide el saldo
    // facturable, y una orden que sólo valiera su subtotal rechazaría la primera
    // factura por excederse.
    $oc = OrdenCompra::factory()->pendienteFactura()->create([
        'total' => round($cantidad * 100 * (1 + (float) config('costos.iva_rate')), 2),
    ]);
    $partida = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'cantidad' => $cantidad,
        'precio_unitario' => 100,
        'subtotal' => $cantidad * 100,
    ]);

    // La factura no se siembra: la recepción la trae en el CFDI, que es como
    // llega en la vida real. Sembrar una de total aleatorio, además, se comería
    // el saldo facturable de la orden.
    return [$oc, $partida];
}

/**
 * La fecha del documento es la de la transacción y la elige quien captura: el
 * camión llegó el viernes y el almacén lo asienta el lunes. Cuándo se capturó
 * queda aparte, en `created_at`, y ese sí lo pone el servidor.
 */
test('la recepcion respeta la fecha capturada y sella aparte la de registro', function () {
    [$oc, $partida] = ocConPartida();

    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', capturaDeRecepcion([
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2020-01-01',
            'tipo' => 'parcial',
            'detalles' => [
                ['orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 1],
            ],
        ]))
        ->assertRedirect();

    $entrega = $oc->entregas()->sole();

    expect($entrega->fecha_entrega->toDateString())->toBe('2020-01-01')
        ->and($entrega->created_at->toDateString())->toBe(today()->toDateString());
});

test('registra entrega con detalle de partida contra la OC', function () {
    [$oc, $partida] = ocConPartida();

    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', capturaDeRecepcion([
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2026-02-17',
            'tipo' => 'parcial',
            'observaciones' => 'Primer lote',
            'detalles' => [
                [
                    'orden_compra_detalle_id' => $partida->id,
                    'cantidad_recibida' => 4,
                ],
            ],
        ]))
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
    [$oc, $partida] = ocConPartida(10);

    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', capturaDeRecepcion([
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2026-02-17',
            'tipo' => 'completa',
            'detalles' => [
                [
                    'orden_compra_detalle_id' => $partida->id,
                    'cantidad_recibida' => 15,
                ],
            ],
        ]))
        ->assertSessionHasErrors('detalles.0.cantidad_recibida');
});

test('rechaza entrega si acumulado excede cantidad ordenada', function () {
    [$oc, $partida] = ocConPartida(10);

    // Primera entrega parcial de 7
    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', capturaDeRecepcion([
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2026-02-17',
            'tipo' => 'parcial',
            'detalles' => [
                ['orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 7],
            ],
        ]))
        ->assertRedirect();

    // Segunda intenta 5 más → 7 + 5 = 12 > 10
    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', capturaDeRecepcion([
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2026-02-18',
            'tipo' => 'parcial',
            'detalles' => [
                ['orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 5],
            ],
        ]))
        ->assertSessionHasErrors('detalles.0.cantidad_recibida');
});

test('rechaza entrega con partida de otra OC', function () {
    [$oc, $partida] = ocConPartida();
    $otraPartida = OrdenCompraDetalle::factory()->create();

    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', capturaDeRecepcion([
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2026-02-17',
            'tipo' => 'parcial',
            'detalles' => [
                ['orden_compra_detalle_id' => $otraPartida->id, 'cantidad_recibida' => 1],
            ],
        ]))
        ->assertSessionHasErrors('detalles.0.orden_compra_detalle_id');
});

test('entrega no crea pago ni cambia estatus de factura', function () {
    // En Fase 4.2 el registro de entrega NO promueve la factura.
    // La transicion pendiente_entrega -> pendiente_aprobacion llega en Fase 4.4
    // via cobertura computada por partida.
    [$oc, $partida] = ocConPartida();
    $detalles = [['orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 10]];

    $factura = facturaQueAmpara(
        Factura::factory()->create([
            'orden_compra_id' => $oc->id,
            'estatus' => 'pendiente_aprobacion',
        ]),
        $oc,
        $detalles,
    );

    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', [
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2026-02-17',
            'factura_id' => $factura->id,
            'tipo' => 'completa',
            'detalles' => $detalles,
        ])
        ->assertRedirect();

    expect($factura->fresh()->estatus->value)->toBe('pendiente_aprobacion');
    expect(Pago::where('pagable_type', Factura::class)->where('pagable_id', $factura->id)->count())->toBe(0);
});

test('tipo y detalles son requeridos', function () {
    [$oc] = ocConPartida();

    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', capturaDeRecepcion([
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2026-02-17',
        ]))
        ->assertSessionHasErrors(['tipo', 'detalles'])
        ->assertSessionDoesntHaveErrors('factura_id');
});

/**
 * Contra orden ya no hay recepción sin factura: el CFDI viaja con el material y
 * la da de alta al recibir. Lo que sigue siendo opcional es declarar que esa
 * entrega la completó.
 */
test('la recepcion queda ligada a la factura que nacio de su CFDI', function () {
    [$oc, $partida] = ocConPartida();

    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', capturaDeRecepcion([
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2026-02-17',
            'tipo' => 'parcial',
            'detalles' => [
                ['orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 4],
            ],
        ]))
        ->assertSessionHasNoErrors();

    $entrega = $oc->entregas()->sole();
    $factura = $oc->facturas()->sole();

    expect($entrega->factura_id)->toBe($factura->id)
        ->and($factura->estatus->value)->toBe('pendiente_recepcion')
        ->and($entrega->completa_factura)->toBeFalse();
});

test('rechaza recepcion contra una factura de otra orden de compra', function () {
    [$oc, $partida] = ocConPartida();
    $ajena = Factura::factory()->create(['estatus' => 'pendiente_recepcion']);

    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', capturaDeRecepcion([
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2026-02-17',
            'factura_id' => $ajena->id,
            'tipo' => 'parcial',
            'detalles' => [
                ['orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 1],
            ],
        ]))
        ->assertSessionHasErrors('factura_id');

    expect($oc->entregas()->count())->toBe(0);
});
