<?php

use App\Enums\ProveedorEstatus;
use App\Models\Proveedor;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['costos.proveedores.aprobar', 'costos.proveedores.editar'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $this->aprobador = User::factory()->create();
    $this->aprobador->givePermissionTo('costos.proveedores.aprobar');
});

test('aprobar activa el proveedor y registra al validador', function () {
    $proveedor = Proveedor::factory()->create([
        'estatus' => ProveedorEstatus::PendienteValidacion,
        'activo' => false,
    ]);

    $this->actingAs($this->aprobador)
        ->post("/admin/proveedores/{$proveedor->id}/aprobar")
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $proveedor->refresh();
    expect($proveedor->estatus)->toBe(ProveedorEstatus::Activo);
    expect($proveedor->activo)->toBeTrue();
    expect($proveedor->validado_por)->toBe($this->aprobador->id);
    expect($proveedor->validado_at)->not->toBeNull();
});

test('no se puede aprobar un proveedor que no está pendiente de validación', function () {
    $proveedor = Proveedor::factory()->create([
        'estatus' => ProveedorEstatus::Activo,
        'activo' => true,
    ]);

    $this->actingAs($this->aprobador)
        ->post("/admin/proveedores/{$proveedor->id}/aprobar")
        ->assertSessionHasErrors('estatus');
});

test('sin permiso no se puede aprobar un proveedor', function () {
    $sinPermiso = User::factory()->create();
    $proveedor = Proveedor::factory()->create([
        'estatus' => ProveedorEstatus::PendienteValidacion,
        'activo' => false,
    ]);

    $this->actingAs($sinPermiso)
        ->post("/admin/proveedores/{$proveedor->id}/aprobar")
        ->assertForbidden();

    expect($proveedor->refresh()->estatus)->toBe(ProveedorEstatus::PendienteValidacion);
});
