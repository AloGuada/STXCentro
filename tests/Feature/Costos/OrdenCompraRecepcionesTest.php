<?php

use App\Models\Costos\Devolucion;
use App\Models\Costos\Entrega;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'costos.ordenes-compra.ver',
        'costos.devoluciones.crear',
    ] as $permName) {
        Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'costos.ordenes-compra.ver',
        'costos.devoluciones.crear',
    ]);
});

test('OC show carga entregas con detalles y devoluciones para tab Recepciones', function () {
    $oc = OrdenCompra::factory()->create();
    $ocd = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'cantidad' => 20,
    ]);
    $entrega = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    $ed = EntregaDetalle::factory()->create([
        'entrega_id' => $entrega->id,
        'orden_compra_detalle_id' => $ocd->id,
        'cantidad_recibida' => 10,
    ]);
    Devolucion::factory()->create([
        'entrega_detalle_id' => $ed->id,
        'cantidad' => 2,
    ]);

    $this->actingAs($this->user)
        ->get("/admin/costos/ordenes-compra/{$oc->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/costos/ordenes-compra/show')
            ->has('ordenCompra.entregas', 1)
            ->has('ordenCompra.entregas.0.detalles', 1)
            ->has('ordenCompra.entregas.0.detalles.0.devoluciones', 1)
            ->where('ordenCompra.entregas.0.detalles.0.cantidad_recibida', '10.00')
        );
});

test('almacén devuelve item desde OC contra entrega_detalle de esa OC', function () {
    $oc = OrdenCompra::factory()->create();
    $ocd = OrdenCompraDetalle::factory()->create(['orden_compra_id' => $oc->id]);
    $entrega = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    $ed = EntregaDetalle::factory()->create([
        'entrega_id' => $entrega->id,
        'orden_compra_detalle_id' => $ocd->id,
        'cantidad_recibida' => 10,
    ]);

    $this->actingAs($this->user)
        ->post('/admin/costos/devoluciones', [
            'entrega_detalle_id' => $ed->id,
            'cantidad' => 3,
            'motivo' => 'Producto recibido con defecto visible',
            'fecha' => '2026-04-29',
        ])
        ->assertRedirect();

    $devolucion = Devolucion::first();
    expect($devolucion)->not->toBeNull();
    expect($devolucion->entrega_detalle_id)->toBe($ed->id);
    expect((float) $devolucion->cantidad)->toBe(3.0);
});
