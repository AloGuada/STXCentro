<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

describe('roles de costos post-seed', function () {
    test('costos-compras puede gestionar OC y ver facturas (solo lectura)', function () {
        $compras = Role::findByName('costos-compras');

        expect($compras->hasPermissionTo('costos.ordenes-compra.crear'))->toBeTrue();
        expect($compras->hasPermissionTo('costos.ordenes-compra.editar'))->toBeTrue();
        expect($compras->hasPermissionTo('costos.ordenes-compra.cancelar'))->toBeTrue();
        expect($compras->hasPermissionTo('costos.facturas.ver'))->toBeTrue();
        expect($compras->hasPermissionTo('costos.proveedores.editar'))->toBeTrue();

        // No debe poder crear, cancelar ni aceptar facturas (admin-only)
        expect($compras->hasPermissionTo('costos.facturas.crear'))->toBeFalse();
        expect($compras->hasPermissionTo('costos.facturas.cancelar'))->toBeFalse();
        expect($compras->hasPermissionTo('costos.facturas.aceptar-contabilidad'))->toBeFalse();
        expect($compras->hasPermissionTo('costos.entregas.crear'))->toBeFalse();
    });

    test('costos-almacen puede registrar entregas y ver OC/facturas', function () {
        $almacen = Role::findByName('costos-almacen');

        expect($almacen->hasPermissionTo('costos.entregas.crear'))->toBeTrue();
        expect($almacen->hasPermissionTo('costos.ordenes-compra.ver'))->toBeTrue();
        expect($almacen->hasPermissionTo('costos.facturas.ver'))->toBeTrue();

        // No debe poder modificar OC, aprobar ni cancelar
        expect($almacen->hasPermissionTo('costos.ordenes-compra.crear'))->toBeFalse();
        expect($almacen->hasPermissionTo('costos.ordenes-compra.editar'))->toBeFalse();
        expect($almacen->hasPermissionTo('costos.facturas.crear'))->toBeFalse();
        expect($almacen->hasPermissionTo('costos.facturas.cancelar'))->toBeFalse();
    });

    test('admin-costos tiene todos los permisos de costos incluyendo facturas.crear', function () {
        $admin = Role::findByName('admin-costos');

        expect($admin->hasPermissionTo('costos.facturas.crear'))->toBeTrue();
        expect($admin->hasPermissionTo('costos.facturas.cancelar'))->toBeTrue();
        expect($admin->hasPermissionTo('costos.facturas.aceptar-contabilidad'))->toBeTrue();
        expect($admin->hasPermissionTo('costos.ordenes-compra.cancelar'))->toBeTrue();
        expect($admin->hasPermissionTo('costos.pagos.cancelar'))->toBeTrue();
    });

    test('renombrado de roles compras->costos-compras es idempotente al re-seedear', function () {
        // El primer seed ya corrió en beforeEach. Simula estado pre-rename creando rol viejo y
        // quitando el nuevo, luego re-corremos el seeder para verificar que migra.
        Role::where('name', 'costos-compras')->delete();
        Role::create(['name' => 'compras', 'guard_name' => 'web']);

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        expect(Role::where('name', 'compras')->exists())->toBeFalse();
        expect(Role::where('name', 'costos-compras')->exists())->toBeTrue();
    });
});
