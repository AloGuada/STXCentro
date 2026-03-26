<?php

use App\Models\BadgeConfig;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'badge-configs.ver', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'badge-configs.crear', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'badge-configs.editar', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'badge-configs.eliminar', 'guard_name' => 'web']);
});

// CRUD Tests

test('lista badge configs', function () {
    BadgeConfig::factory()->count(3)->create();

    $this->actingAs($this->user)
        ->get('/admin/badge-configs')
        ->assertOk();
});

test('muestra formulario de crear badge config', function () {
    $this->actingAs($this->user)
        ->get('/admin/badge-configs/create')
        ->assertOk();
});

test('crea badge config', function () {
    $this->actingAs($this->user)
        ->post('/admin/badge-configs', [
            'nombre' => 'Test Badge',
            'tabla' => 'costos_facturas',
            'campo_estatus' => 'estatus',
            'operador' => '=',
            'valor_estatus' => 'pendiente_entrega',
            'rol' => 'almacen',
            'nav_href' => '/admin/costos/facturas',
            'activo' => true,
        ])
        ->assertRedirect('/admin/badge-configs');

    $this->assertDatabaseHas('badge_configs', [
        'nombre' => 'Test Badge',
        'tabla' => 'costos_facturas',
        'rol' => 'almacen',
    ]);
});

test('muestra formulario de editar badge config', function () {
    $config = BadgeConfig::factory()->create();

    $this->actingAs($this->user)
        ->get("/admin/badge-configs/{$config->id}/edit")
        ->assertOk();
});

test('actualiza badge config', function () {
    $config = BadgeConfig::factory()->create();

    $this->actingAs($this->user)
        ->put("/admin/badge-configs/{$config->id}", [
            'nombre' => 'Updated Badge',
            'tabla' => $config->tabla,
            'campo_estatus' => $config->campo_estatus,
            'operador' => $config->operador,
            'valor_estatus' => $config->valor_estatus,
            'rol' => $config->rol,
            'nav_href' => $config->nav_href,
            'activo' => false,
        ])
        ->assertRedirect('/admin/badge-configs');

    expect($config->fresh()->nombre)->toBe('Updated Badge');
    expect($config->fresh()->activo)->toBeFalse();
});

test('elimina badge config', function () {
    $config = BadgeConfig::factory()->create();

    $this->actingAs($this->user)
        ->delete("/admin/badge-configs/{$config->id}")
        ->assertRedirect('/admin/badge-configs');

    $this->assertDatabaseMissing('badge_configs', ['id' => $config->id]);
});

// Badge Computation Tests

test('middleware comparte badges para usuario con rol', function () {
    $role = Role::firstOrCreate(['name' => 'almacen', 'guard_name' => 'web']);
    $this->user->assignRole($role);

    BadgeConfig::factory()->create([
        'tabla' => 'costos_facturas',
        'campo_estatus' => 'estatus',
        'operador' => '=',
        'valor_estatus' => 'pendiente_entrega',
        'rol' => 'almacen',
        'nav_href' => '/admin/costos/facturas',
        'filter_href' => '/admin/costos/facturas?estatus=pendiente_entrega',
        'activo' => true,
    ]);

    $oc = OrdenCompra::factory()->create();
    Factura::factory()->count(3)->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $oc->proveedor_id,
        'estatus' => 'pendiente_entrega',
    ]);

    $response = $this->actingAs($this->user)->get('/admin/badge-configs');
    $badges = $response->original->getData()['page']['props']['auth']['badges'];

    expect($badges)->toHaveKey('/admin/costos/facturas');
    expect($badges['/admin/costos/facturas']['count'])->toBe(3);
});

test('badge config inactiva no genera badge', function () {
    $role = Role::firstOrCreate(['name' => 'almacen', 'guard_name' => 'web']);
    $this->user->assignRole($role);

    BadgeConfig::factory()->create([
        'tabla' => 'costos_facturas',
        'campo_estatus' => 'estatus',
        'operador' => '=',
        'valor_estatus' => 'pendiente_entrega',
        'rol' => 'almacen',
        'nav_href' => '/admin/costos/facturas',
        'activo' => false,
    ]);

    $oc = OrdenCompra::factory()->create();
    Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $oc->proveedor_id,
        'estatus' => 'pendiente_entrega',
    ]);

    $response = $this->actingAs($this->user)->get('/admin/badge-configs');
    $badges = $response->original->getData()['page']['props']['auth']['badges'];

    expect($badges)->toBeEmpty();
});

test('badge no aparece si conteo es cero', function () {
    $role = Role::firstOrCreate(['name' => 'almacen', 'guard_name' => 'web']);
    $this->user->assignRole($role);

    BadgeConfig::factory()->create([
        'tabla' => 'costos_facturas',
        'campo_estatus' => 'estatus',
        'operador' => '=',
        'valor_estatus' => 'pendiente_entrega',
        'rol' => 'almacen',
        'nav_href' => '/admin/costos/facturas',
        'activo' => true,
    ]);

    $response = $this->actingAs($this->user)->get('/admin/badge-configs');
    $badges = $response->original->getData()['page']['props']['auth']['badges'];

    expect($badges)->toBeEmpty();
});

test('usuario sin rol no recibe badges', function () {
    BadgeConfig::factory()->create(['activo' => true]);

    $response = $this->actingAs($this->user)->get('/admin/badge-configs');
    $badges = $response->original->getData()['page']['props']['auth']['badges'];

    expect($badges)->toBeEmpty();
});
