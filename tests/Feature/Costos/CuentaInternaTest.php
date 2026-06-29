<?php

use App\Models\Usuario;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['costos.cuentas-internas.ver', 'costos.cuentas-internas.editar'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    foreach (['compras', 'almacen', 'contabilidad'] as $r) {
        Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
    }

    $this->admin = Usuario::factory()->create();
    $this->admin->givePermissionTo('costos.cuentas-internas.editar');
});

test('crea un usuario y le asigna un rol del módulo', function () {
    $this->actingAs($this->admin)
        ->post('/admin/costos/cuentas-internas/usuario', [
            'name' => 'Juan Compras',
            'email' => 'juan@steelex.mx',
            'password' => 'secret123',
            'rol' => 'compras',
        ])
        ->assertRedirect();

    $usuario = Usuario::where('email', 'juan@steelex.mx')->first();
    expect($usuario)->not->toBeNull()
        ->and($usuario->hasRole('compras'))->toBeTrue();
});

test('valida correo único y rol del módulo', function () {
    Usuario::factory()->create(['email' => 'dup@steelex.mx']);

    $this->actingAs($this->admin)
        ->post('/admin/costos/cuentas-internas/usuario', [
            'name' => 'X',
            'email' => 'dup@steelex.mx',
            'password' => 'secret123',
            'rol' => 'super-admin',
        ])
        ->assertSessionHasErrors(['email', 'rol']);
});

test('sin permiso no puede crear usuarios', function () {
    $this->actingAs(Usuario::factory()->create())
        ->post('/admin/costos/cuentas-internas/usuario', [
            'name' => 'Y',
            'email' => 'y@steelex.mx',
            'password' => 'secret123',
            'rol' => 'compras',
        ])
        ->assertForbidden();
});
