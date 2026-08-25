<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\RubroAfectado;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'costos.entregas.crear', 'guard_name' => 'web']);
});

/**
 * OC con una partida ligada a un centro de costos con presupuesto holgado.
 *
 * @return array{0: OrdenCompra, 1: OrdenCompraDetalle, 2: ObraRubro}
 */
function ocConRubro(float $cantidad = 10, float $precio = 100): array
{
    $rubro = ObraRubro::factory()->create(['presupuestado' => 1_000_000, 'acumulado' => 0]);
    $oc = OrdenCompra::factory()->pendienteFactura()->create(['total' => $cantidad * $precio]);
    $partida = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'obra_rubro_id' => $rubro->id,
        'cantidad' => $cantidad,
        'precio_unitario' => $precio,
        'subtotal' => $cantidad * $precio,
    ]);

    return [$oc, $partida, $rubro];
}

test('guarda el precio recibido en el detalle de la entrega', function () {
    [$oc, $partida] = ocConRubro(10, 100);

    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', [
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2026-07-20',
            'tipo' => 'completa',
            'detalles' => [
                ['orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 10, 'precio_unitario' => 120],
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('costos_entrega_detalle', [
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => 10,
        'precio_unitario' => 120,
    ]);
});

test('un precio recibido distinto ajusta el acumulado por la diferencia', function () {
    [$oc, $partida, $rubro] = ocConRubro(10, 100);

    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', [
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2026-07-20',
            'tipo' => 'completa',
            'detalles' => [
                ['orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 10, 'precio_unitario' => 120],
            ],
        ])
        ->assertRedirect();

    // delta = (120 - 100) × 10 = 200, ligado a la OC para poder revertirlo.
    expect((float) $rubro->fresh()->acumulado)->toBe(200.0);
    $ra = RubroAfectado::where('entrada_type', OrdenCompra::class)
        ->where('entrada_id', $oc->id)
        ->where('obra_rubro_id', $rubro->id)
        ->first();
    expect($ra)->not->toBeNull()
        ->and((float) $ra->monto)->toBe(200.0);
});

test('recibir al mismo precio de la OC no genera ajuste presupuestal', function () {
    [$oc, $partida, $rubro] = ocConRubro(10, 100);

    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', [
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2026-07-20',
            'tipo' => 'completa',
            'detalles' => [
                ['orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 10, 'precio_unitario' => 100],
            ],
        ])
        ->assertRedirect();

    expect((float) $rubro->fresh()->acumulado)->toBe(0.0)
        ->and(RubroAfectado::where('entrada_id', $oc->id)->count())->toBe(0);
});

test('un precio recibido menor reduce el acumulado', function () {
    [$oc, $partida, $rubro] = ocConRubro(10, 100);
    $rubro->update(['acumulado' => 1000]);

    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', [
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2026-07-20',
            'tipo' => 'completa',
            'detalles' => [
                ['orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 10, 'precio_unitario' => 90],
            ],
        ])
        ->assertRedirect();

    // delta = (90 - 100) × 10 = -100 → acumulado 1000 - 100 = 900.
    expect((float) $rubro->fresh()->acumulado)->toBe(900.0);
});

test('el monto recibido de la OC usa el precio recibido', function () {
    [$oc, $partida] = ocConRubro(10, 100);

    $this->actingAs($this->user)
        ->post('/admin/almacen/entradas', [
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($this->user)->id,
            'fecha_entrega' => '2026-07-20',
            'tipo' => 'completa',
            'detalles' => [
                ['orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 10, 'precio_unitario' => 120],
            ],
        ])
        ->assertRedirect();

    // 10 × 120 = 1200 (no 10 × 100 de la OC).
    expect($oc->fresh()->monto_recibido)->toBe(1200.0);
});
