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
            'valor_estatus' => 'pendiente_aprobacion',
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
        'valor_estatus' => 'pendiente_aprobacion',
        'rol' => 'almacen',
        'nav_href' => '/admin/costos/facturas',
        'filter_href' => '/admin/costos/facturas?estatus=pendiente_entrega',
        'activo' => true,
    ]);

    $oc = OrdenCompra::factory()->create();
    Factura::factory()->count(3)->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $oc->proveedor_id,
        'estatus' => 'pendiente_aprobacion',
    ]);

    $response = $this->actingAs($this->user)->get('/admin/badge-configs');
    $badges = $response->original->getData()['page']['props']['auth']['badges'];

    expect($badges)->toHaveKey('/admin/costos/facturas');
    expect($badges['/admin/costos/facturas']['count'])->toBe(3);
});

test('las facturas de OC de contado no cuentan en el badge', function () {
    $role = Role::firstOrCreate(['name' => 'costos', 'guard_name' => 'web']);
    $this->user->assignRole($role);

    BadgeConfig::factory()->create([
        'tabla' => 'costos_facturas',
        'campo_estatus' => 'estatus',
        'operador' => '=',
        'valor_estatus' => 'pendiente_aprobacion',
        'rol' => 'costos',
        'nav_href' => '/admin/costos/facturas',
        'activo' => true,
    ]);

    $ocContado = OrdenCompra::factory()->create(['tipo_pago' => 'contado']);
    Factura::factory()->create([
        'orden_compra_id' => $ocContado->id,
        'proveedor_id' => $ocContado->proveedor_id,
        'estatus' => 'pendiente_aprobacion',
    ]);

    $ocCredito = OrdenCompra::factory()->create(['tipo_pago' => 'credito']);
    Factura::factory()->create([
        'orden_compra_id' => $ocCredito->id,
        'proveedor_id' => $ocCredito->proveedor_id,
        'estatus' => 'pendiente_aprobacion',
    ]);

    $response = $this->actingAs($this->user)->get('/admin/badge-configs');
    $badges = $response->original->getData()['page']['props']['auth']['badges'];

    // Solo cuenta la de crédito; la de contado se ignora.
    expect($badges['/admin/costos/facturas']['count'])->toBe(1);
});

test('badge config inactiva no genera badge', function () {
    $role = Role::firstOrCreate(['name' => 'almacen', 'guard_name' => 'web']);
    $this->user->assignRole($role);

    BadgeConfig::factory()->create([
        'tabla' => 'costos_facturas',
        'campo_estatus' => 'estatus',
        'operador' => '=',
        'valor_estatus' => 'pendiente_aprobacion',
        'rol' => 'almacen',
        'nav_href' => '/admin/costos/facturas',
        'activo' => false,
    ]);

    $oc = OrdenCompra::factory()->create();
    Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $oc->proveedor_id,
        'estatus' => 'pendiente_aprobacion',
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
        'valor_estatus' => 'pendiente_aprobacion',
        'rol' => 'almacen',
        'nav_href' => '/admin/costos/facturas',
        'activo' => true,
    ]);

    $response = $this->actingAs($this->user)->get('/admin/badge-configs');
    $badges = $response->original->getData()['page']['props']['auth']['badges'];

    expect($badges)->toBeEmpty();
});

test('badge cuenta requisiciones pendientes de aprobación gerencial con OC configurada', function () {
    $role = Role::firstOrCreate(['name' => 'compras', 'guard_name' => 'web']);
    $this->user->assignRole($role);

    BadgeConfig::factory()->create([
        'tabla' => 'costos_requisiciones',
        'campo_estatus' => 'estatus',
        'operador' => '=',
        'valor_estatus' => 'pendiente_aprobacion_interno',
        'condiciones_extra' => [
            ['tipo' => 'existe', 'tabla' => 'costos_requisicion_ocs', 'fk' => 'requisicion_id'],
        ],
        'rol' => 'compras',
        'nav_href' => '/admin/costos/requisiciones',
        'activo' => true,
    ]);

    $depto = \App\Models\Departamento::factory()->create();
    $ocData = fn () => ['proveedor_id' => \App\Models\Proveedor::factory()->create()->id, 'numero_oc' => 1];

    // Cuenta: pendiente de aprobación interna, con OC configurada.
    $conOc = \App\Models\Costos\Requisicion::factory()->pendienteAprobacionInterna()->create([
        'departamento_id' => $depto->id,
    ]);
    $conOc->ocs()->create($ocData());

    // No cuenta: sin OC.
    \App\Models\Costos\Requisicion::factory()->pendienteAprobacionInterna()->create([
        'departamento_id' => $depto->id,
    ]);

    // No cuenta: ya con aprobación interna (aprobada_interna) aunque tenga OC.
    $verificada = \App\Models\Costos\Requisicion::factory()->aprobadaInterna()->create([
        'departamento_id' => $depto->id,
    ]);
    $verificada->ocs()->create($ocData());

    $response = $this->actingAs($this->user)->get('/admin/badge-configs');
    $badges = $response->original->getData()['page']['props']['auth']['badges'];

    expect($badges['/admin/costos/requisiciones']['count'])->toBe(1);
});

test('usuario sin rol no recibe badges', function () {
    BadgeConfig::factory()->create(['activo' => true]);

    $response = $this->actingAs($this->user)->get('/admin/badge-configs');
    $badges = $response->original->getData()['page']['props']['auth']['badges'];

    expect($badges)->toBeEmpty();
});
