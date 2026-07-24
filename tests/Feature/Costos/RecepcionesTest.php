<?php

use App\Models\Costos\Entrega;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\SolicitudPago;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['costos.ordenes-compra.ver', 'costos.ordenes-compra.ver-todas'] as $permiso) {
        Permission::firstOrCreate(['name' => $permiso]);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['costos.ordenes-compra.ver', 'costos.ordenes-compra.ver-todas']);
});

test('lista las recepciones con su orden de compra', function () {
    $entrega = Entrega::factory()->create();

    $this->actingAs($this->user)
        ->get(route('admin.costos.recepciones.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/costos/recepciones/index')
            ->has('recepciones.data', 1)
            ->where('recepciones.data.0.id', $entrega->id)
            ->where('recepciones.data.0.oc.folio', $entrega->ordenCompra->folio)
        );
});

test('la recepción de una OC de contado muestra su solicitud de pago ligada', function () {
    $oc = OrdenCompra::factory()->create(['tipo_pago' => 'contado']);
    $sp = SolicitudPago::factory()->create(['orden_compra_id' => $oc->id]);
    Entrega::factory()->create(['orden_compra_id' => $oc->id]);

    $this->actingAs($this->user)
        ->get(route('admin.costos.recepciones.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recepciones.data', 1)
            ->has('recepciones.data.0.solicitudes_pago', 1)
            ->where('recepciones.data.0.solicitudes_pago.0.folio', $sp->folio)
        );
});

test('el filtro por tipo acota los resultados', function () {
    Entrega::factory()->create(['tipo' => 'completa']);
    Entrega::factory()->create(['tipo' => 'parcial']);

    $this->actingAs($this->user)
        ->get(route('admin.costos.recepciones.index', ['tipo' => 'completa']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recepciones.data', 1)
            ->where('recepciones.data.0.tipo', 'completa')
        );
});

test('sin permiso de ver órdenes de compra no puede entrar', function () {
    $sinPermiso = User::factory()->create();

    $this->actingAs($sinPermiso)
        ->get(route('admin.costos.recepciones.index'))
        ->assertForbidden();
});
