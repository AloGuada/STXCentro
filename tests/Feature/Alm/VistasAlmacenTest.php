<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * Las pantallas del módulo todavía no tienen backend: sólo se comprueba que la
 * ruta abre su maqueta y que sigue detrás del permiso.
 */
beforeEach(function () {
    Permission::firstOrCreate(['name' => 'alm.almacenes.ver', 'guard_name' => 'web']);

    $this->almacenista = User::factory()->create();
    $this->almacenista->givePermissionTo('alm.almacenes.ver');
});

$pantallas = [
    'existencias' => ['admin.alm.existencias.index', 'admin/almacen/existencias/index'],
    'kardex' => ['admin.alm.kardex.index', 'admin/almacen/kardex/index'],
    'entradas' => ['admin.alm.entradas.index', 'admin/almacen/entradas/index'],
    'alta de entrada' => ['admin.alm.entradas.create', 'admin/almacen/entradas/create'],
    'salidas' => ['admin.alm.salidas.index', 'admin/almacen/salidas/index'],
    'alta de salida' => ['admin.alm.salidas.create', 'admin/almacen/salidas/create'],
    'transferencias' => ['admin.alm.transferencias.index', 'admin/almacen/transferencias/index'],
    'alta de transferencia' => ['admin.alm.transferencias.create', 'admin/almacen/transferencias/create'],
    'devoluciones' => ['admin.alm.devoluciones.index', 'admin/almacen/devoluciones/index'],
    'alta de devolucion' => ['admin.alm.devoluciones.create', 'admin/almacen/devoluciones/create'],
    'ajustes' => ['admin.alm.ajustes.index', 'admin/almacen/ajustes/index'],
    'alta de ajuste' => ['admin.alm.ajustes.create', 'admin/almacen/ajustes/create'],
    'requisiciones' => ['admin.alm.requisiciones.index', 'admin/almacen/requisiciones/index'],
    'alta de requisicion' => ['admin.alm.requisiciones.create', 'admin/almacen/requisiciones/create'],
    'insumos' => ['admin.alm.insumos.index', 'admin/almacen/insumos/index'],
    'alta de insumo' => ['admin.alm.insumos.create', 'admin/almacen/insumos/create'],
    'aprobaciones' => ['admin.alm.aprobaciones.index', 'admin/almacen/aprobaciones/index'],
];

test('la pantalla abre con el permiso', function (string $ruta, string $componente) {
    $this->actingAs($this->almacenista)
        ->get(route($ruta))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($componente));
})->with($pantallas);

test('la pantalla queda cerrada sin el permiso', function (string $ruta) {
    $this->actingAs(User::factory()->create())
        ->get(route($ruta))
        ->assertForbidden();
})->with($pantallas);
