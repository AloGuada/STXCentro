<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * Las pantallas del módulo todavía no tienen backend: sólo se comprueba que la
 * ruta abre su maqueta y que sigue detrás de su propio permiso.
 *
 * Cada pantalla tiene el suyo a propósito. Mientras todas reciclaban
 * `alm.almacenes.ver`, quien podía consultar el catálogo podía capturar
 * cualquier movimiento, y aquí cada documento afecta existencias de forma
 * irreversible.
 */
$pantallas = [
    'existencias' => ['admin.alm.existencias.index', 'admin/almacen/existencias/index', 'alm.existencias.ver'],
    'kardex' => ['admin.alm.kardex.index', 'admin/almacen/kardex/index', 'alm.kardex.ver'],
    'entradas' => ['admin.alm.entradas.index', 'admin/almacen/entradas/index', 'alm.entradas.ver'],
    'alta de entrada' => ['admin.alm.entradas.create', 'admin/almacen/entradas/create', 'alm.entradas.crear'],
    'salidas' => ['admin.alm.salidas.index', 'admin/almacen/salidas/index', 'alm.salidas.ver'],
    'alta de salida' => ['admin.alm.salidas.create', 'admin/almacen/salidas/create', 'alm.salidas.crear'],
    'transferencias' => ['admin.alm.transferencias.index', 'admin/almacen/transferencias/index', 'alm.transferencias.ver'],
    'alta de transferencia' => ['admin.alm.transferencias.create', 'admin/almacen/transferencias/create', 'alm.transferencias.enviar'],
    'devoluciones' => ['admin.alm.devoluciones.index', 'admin/almacen/devoluciones/index', 'alm.devoluciones.ver'],
    'alta de devolucion' => ['admin.alm.devoluciones.create', 'admin/almacen/devoluciones/create', 'alm.devoluciones.crear'],
    'ajustes' => ['admin.alm.ajustes.index', 'admin/almacen/ajustes/index', 'alm.ajustes.ver'],
    'alta de ajuste' => ['admin.alm.ajustes.create', 'admin/almacen/ajustes/create', 'alm.ajustes.crear'],
    'pedidos' => ['admin.alm.pedidos.index', 'admin/almacen/pedidos/index', 'alm.pedidos.ver'],
    'alta de pedido' => ['admin.alm.pedidos.create', 'admin/almacen/pedidos/create', 'alm.pedidos.crear'],
    'prestamos' => ['admin.alm.prestamos.index', 'admin/almacen/prestamos/index', 'alm.prestamos.ver'],
    'alta de prestamo' => ['admin.alm.prestamos.create', 'admin/almacen/prestamos/create', 'alm.prestamos.crear'],
    'activos' => ['admin.alm.activos.index', 'admin/almacen/activos/index', 'alm.activos.ver'],
    'alta de activo' => ['admin.alm.activos.create', 'admin/almacen/activos/create', 'alm.activos.crear'],
    'articulos' => ['admin.alm.articulos.index', 'admin/almacen/articulos/index', 'alm.articulos.ver'],
    'alta de articulo' => ['admin.alm.articulos.create', 'admin/almacen/articulos/create', 'alm.articulos.crear'],
    'aprobaciones' => ['admin.alm.aprobaciones.index', 'admin/almacen/aprobaciones/index', 'alm.aprobaciones.ver'],
];

test('la pantalla abre con su permiso', function (string $ruta, string $componente, string $permiso) {
    Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);

    $usuario = User::factory()->create();
    $usuario->givePermissionTo($permiso);

    $this->actingAs($usuario)
        ->get(route($ruta))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($componente));
})->with($pantallas);

test('la pantalla queda cerrada sin el permiso', function (string $ruta) {
    $this->actingAs(User::factory()->create())
        ->get(route($ruta))
        ->assertForbidden();
})->with($pantallas);

test('el permiso de una pantalla no abre las demas', function () {
    Permission::firstOrCreate(['name' => 'alm.existencias.ver', 'guard_name' => 'web']);

    $consultor = User::factory()->create();
    $consultor->givePermissionTo('alm.existencias.ver');

    $this->actingAs($consultor)->get(route('admin.alm.existencias.index'))->assertOk();
    $this->actingAs($consultor)->get(route('admin.alm.salidas.create'))->assertForbidden();
    $this->actingAs($consultor)->get(route('admin.alm.ajustes.create'))->assertForbidden();
});
