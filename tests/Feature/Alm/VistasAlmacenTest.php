<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * Las pantallas del módulo que todavía no tienen backend: sólo se comprueba que
 * la ruta abre su maqueta y que sigue detrás de su propio permiso. Conforme cada
 * una se construye sale de aquí y se prueba de verdad en su propio archivo
 * —de las 24 originales sólo quedan etiquetas y aprobaciones; conteos vive en
 * ConteoProgramadoTest y préstamos/devoluciones en PrestamoTest—, hasta que
 * este archivo desaparezca.
 *
 * Cada pantalla tiene el suyo a propósito. Mientras todas reciclaban
 * `alm.almacenes.ver`, quien podía consultar el catálogo podía capturar
 * cualquier movimiento, y aquí cada documento afecta existencias de forma
 * irreversible.
 */
$pantallas = [
    'etiquetas' => ['admin.alm.etiquetas.index', 'admin/almacen/etiquetas/index', 'alm.etiquetas.ver'],
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
