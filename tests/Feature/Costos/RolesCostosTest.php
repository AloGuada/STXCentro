<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

describe('roles de costos post-seed', function () {
    test('compras gestiona OC y ve facturas (solo lectura)', function () {
        $compras = Role::findByName('compras');

        expect($compras->hasPermissionTo('costos.ordenes-compra.crear'))->toBeTrue();
        expect($compras->hasPermissionTo('costos.ordenes-compra.editar'))->toBeTrue();
        expect($compras->hasPermissionTo('costos.ordenes-compra.cancelar'))->toBeTrue();
        expect($compras->hasPermissionTo('costos.ordenes-compra.ver-todas'))->toBeTrue();
        expect($compras->hasPermissionTo('costos.facturas.ver'))->toBeTrue();
        expect($compras->hasPermissionTo('costos.proveedores.editar'))->toBeTrue();
        expect($compras->hasPermissionTo('costos.devoluciones.crear'))->toBeTrue();

        // No debe poder crear, cancelar ni aceptar facturas (responsabilidad de costos)
        expect($compras->hasPermissionTo('costos.facturas.crear'))->toBeFalse();
        expect($compras->hasPermissionTo('costos.facturas.cancelar'))->toBeFalse();
        expect($compras->hasPermissionTo('costos.facturas.aceptar-contabilidad'))->toBeFalse();
        expect($compras->hasPermissionTo('costos.entregas.crear'))->toBeFalse();
    });

    test('almacen registra entregas y ve OC/facturas', function () {
        $almacen = Role::findByName('almacen');

        expect($almacen->hasPermissionTo('costos.entregas.crear'))->toBeTrue();
        expect($almacen->hasPermissionTo('costos.ordenes-compra.ver'))->toBeTrue();
        expect($almacen->hasPermissionTo('costos.ordenes-compra.ver-todas'))->toBeTrue();
        expect($almacen->hasPermissionTo('costos.facturas.ver'))->toBeTrue();

        // No debe poder modificar OC, aprobar ni cancelar
        expect($almacen->hasPermissionTo('costos.ordenes-compra.crear'))->toBeFalse();
        expect($almacen->hasPermissionTo('costos.ordenes-compra.editar'))->toBeFalse();
        expect($almacen->hasPermissionTo('costos.facturas.crear'))->toBeFalse();
        expect($almacen->hasPermissionTo('costos.facturas.cancelar'))->toBeFalse();
    });

    test('contabilidad gestiona pagos, solicitudes y complementos', function () {
        $contabilidad = Role::findByName('contabilidad');

        expect($contabilidad->hasPermissionTo('costos.pagos.programar'))->toBeTrue();
        expect($contabilidad->hasPermissionTo('costos.solicitudes-pago.aprobar'))->toBeTrue();
        expect($contabilidad->hasPermissionTo('costos.complementos.desbloquear'))->toBeTrue();
        expect($contabilidad->hasPermissionTo('costos.facturas.aceptar-contabilidad'))->toBeTrue();
        expect($contabilidad->hasPermissionTo('costos.ordenes-compra.ver-todas'))->toBeTrue();
        expect($contabilidad->hasPermissionTo('costos.requisiciones.ver-todas'))->toBeTrue();
    });

    test('costos tiene todos los permisos de costos incluyendo facturas.crear', function () {
        $costos = Role::findByName('costos');

        expect($costos->hasPermissionTo('costos.facturas.crear'))->toBeTrue();
        expect($costos->hasPermissionTo('costos.facturas.cancelar'))->toBeTrue();
        expect($costos->hasPermissionTo('costos.facturas.aceptar-contabilidad'))->toBeTrue();
        expect($costos->hasPermissionTo('costos.ordenes-compra.cancelar'))->toBeTrue();
        expect($costos->hasPermissionTo('costos.pagos.cancelar'))->toBeTrue();
        expect($costos->hasPermissionTo('costos.afectaciones.crear'))->toBeTrue();
        expect($costos->hasPermissionTo('costos.obra-rubros.editar'))->toBeTrue();
    });

    test('los 4 roles operativos ven todas las requisiciones, OC y solicitudes', function () {
        foreach (['compras', 'costos', 'almacen', 'contabilidad'] as $nombre) {
            $rol = Role::findByName($nombre);
            expect($rol->hasPermissionTo('costos.requisiciones.ver-todas'))->toBeTrue("{$nombre} req");
            expect($rol->hasPermissionTo('costos.ordenes-compra.ver-todas'))->toBeTrue("{$nombre} oc");
            expect($rol->hasPermissionTo('costos.solicitudes-pago.ver-todas'))->toBeTrue("{$nombre} sol");
        }
    });

    test('consolidación de roles antiguos al re-seedear es idempotente', function () {
        // Simula estado previo (admin-costos) y verifica que se renombra a costos.
        Role::where('name', 'costos')->delete();
        Role::create(['name' => 'admin-costos', 'guard_name' => 'web']);

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        expect(Role::where('name', 'admin-costos')->exists())->toBeFalse();
        expect(Role::where('name', 'costos')->exists())->toBeTrue();
    });
});
