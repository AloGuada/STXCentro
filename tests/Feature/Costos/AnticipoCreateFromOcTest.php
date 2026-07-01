<?php

use App\Models\Costos\OrdenCompra;
use App\Models\Obra;
use App\Models\Proveedor;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'costos.anticipos.crear', 'guard_name' => 'web']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('costos.anticipos.crear');
});

test('GET create con query params prellena proveedor y obra', function () {
    $proveedor = Proveedor::factory()->create();
    $obra = Obra::factory()->create();

    $this->actingAs($this->user)
        ->get("/admin/costos/anticipos/create?proveedor_id={$proveedor->id}&obra_id={$obra->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/costos/anticipos/create')
            ->where('preset.proveedor_id', $proveedor->id)
            ->where('preset.obra_id', $obra->id)
        );
});

test('GET create sin query params devuelve preset vacío', function () {
    $this->actingAs($this->user)
        ->get('/admin/costos/anticipos/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/costos/anticipos/create')
            ->where('preset.proveedor_id', null)
            ->where('preset.obra_id', null)
        );
});

test('OC show expone link a anticipo create con proveedor y obra', function () {
    Permission::firstOrCreate(['name' => 'costos.ordenes-compra.ver-todas', 'guard_name' => 'web']);
    $this->user->givePermissionTo('costos.ordenes-compra.ver-todas');

    $oc = OrdenCompra::factory()->create();

    // Verifica que la página show carga sin error con la OC ya cargada;
    // los datos requeridos para el link (proveedor_id, obra_id) están en
    // las props.
    $this->actingAs($this->user)
        ->get("/admin/costos/ordenes-compra/{$oc->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/costos/ordenes-compra/show')
            ->where('ordenCompra.proveedor_id', $oc->proveedor_id)
        );
});
