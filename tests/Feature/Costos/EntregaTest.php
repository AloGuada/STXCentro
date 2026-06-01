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

    return [$oc, $partida];
}

test('registra entrega con detalle de partida contra la OC', function () {
    [$oc, $partida] = ocConPartida();

    $this->actingAs($this->user)
        ->post("/admin/costos/ordenes-compra/{$oc->id}/entregas", [
            'fecha_entrega' => '2026-02-17',
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
    [$oc, $partida] = ocConPartida(10);

    $this->actingAs($this->user)
        ->post("/admin/costos/ordenes-compra/{$oc->id}/entregas", [
            'fecha_entrega' => '2026-02-17',
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
    [$oc, $partida] = ocConPartida(10);

    // Primera entrega parcial de 7
    $this->actingAs($this->user)
        ->post("/admin/costos/ordenes-compra/{$oc->id}/entregas", [
            'fecha_entrega' => '2026-02-17',
            'tipo' => 'parcial',
            'detalles' => [
                ['orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 7],
            ],
        ])
        ->assertRedirect();

    // Segunda intenta 5 más → 7 + 5 = 12 > 10
    $this->actingAs($this->user)
        ->post("/admin/costos/ordenes-compra/{$oc->id}/entregas", [
            'fecha_entrega' => '2026-02-18',
            'tipo' => 'parcial',
            'detalles' => [
                ['orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 5],
            ],
        ])
        ->assertSessionHasErrors('detalles.0.cantidad_recibida');
});

test('rechaza entrega con partida de otra OC', function () {
    [$oc, $partida] = ocConPartida();
    $otraPartida = OrdenCompraDetalle::factory()->create();

    $this->actingAs($this->user)
        ->post("/admin/costos/ordenes-compra/{$oc->id}/entregas", [
            'fecha_entrega' => '2026-02-17',
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
        ->post("/admin/costos/ordenes-compra/{$oc->id}/entregas", [
            'fecha_entrega' => '2026-02-17',
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
        ->post("/admin/costos/ordenes-compra/{$oc->id}/entregas", [
            'fecha_entrega' => '2026-02-17',
        ])
        ->assertSessionHasErrors(['tipo', 'detalles']);
});
