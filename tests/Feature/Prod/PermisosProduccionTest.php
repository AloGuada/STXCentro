<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;

$permisosNuevos = [
    'prod.catalogos.ver',
    'prod.catalogos.crear',
    'prod.catalogos.editar',
    'prod.catalogos.eliminar',
    'prod.ubicaciones.ver',
    'prod.ubicaciones.crear',
    'prod.ubicaciones.editar',
    'prod.ubicaciones.eliminar',
    'prod.categorias-empleado.ver',
    'prod.categorias-empleado.crear',
    'prod.categorias-empleado.editar',
    'prod.categorias-empleado.eliminar',
    'prod.asistencia.ver',
    'prod.asistencia.registrar',
    'prod.pagos-extra.ver',
    'prod.pagos-extra.crear',
    'prod.pagos-extra.eliminar',
    'prod.liquidaciones.ver',
    'prod.configuracion.ver',
    'prod.configuracion.editar',
];

describe('permisos del modulo produccion', function () use ($permisosNuevos) {
    test('la migracion da de alta los permisos de las pantallas nuevas', function () use ($permisosNuevos) {
        $existentes = Permission::query()
            ->whereIn('name', $permisosNuevos)
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();

        expect($existentes)->toEqualCanonicalizing($permisosNuevos);
    });

    test('el seeder los incluye para que admin-produccion los reciba', function () use ($permisosNuevos) {
        $delSeeder = RolesAndPermissionsSeeder::groupedPermissions()['prod'];

        foreach ($permisosNuevos as $permiso) {
            expect($delSeeder)->toContain($permiso);
        }
    });

    test('el seeder es idempotente y no duplica permisos', function () {
        (new RolesAndPermissionsSeeder)->run();

        $duplicados = Permission::query()
            ->where('name', 'like', 'prod.%')
            ->get()
            ->groupBy('name')
            ->filter(fn ($grupo) => $grupo->count() > 1);

        expect($duplicados)->toBeEmpty();
    });
});
