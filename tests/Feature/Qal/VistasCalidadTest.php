<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * El módulo de Calidad llevaba meses funcionando sólo por API (Sanctum). Estas
 * son sus primeras pantallas web: todavía no leen nada, así que aquí sólo se
 * comprueba que la ruta abre y que respeta el permiso `qal.*` que ya existía.
 */
$pantallas = [
    'formularios' => ['admin.qal.formularios', 'admin/calidad/formularios/index', 'qal.inspecciones.crear'],
    'registros' => ['admin.qal.registros.index', 'admin/calidad/registros/index', 'qal.reportes.ver'],
    'accesorios' => ['admin.qal.accesorios.index', 'admin/calidad/accesorios/index', 'qal.accesorios.ver'],
    'avance' => ['admin.qal.avance', 'admin/calidad/avance/index', 'qal.reportes.ver'],
    'tablero' => ['admin.qal.dashboard', 'admin/calidad/dashboard/index', 'qal.dashboard.ver'],
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

/**
 * Consultar el padrón de soldadores no debe abrir los reportes de inspección:
 * son permisos distintos desde que el módulo existe.
 */
test('el permiso de una pantalla no abre las demas', function () {
    Permission::firstOrCreate(['name' => 'qal.soldadores.ver', 'guard_name' => 'web']);

    $usuario = User::factory()->create();
    $usuario->givePermissionTo('qal.soldadores.ver');

    $this->actingAs($usuario)->get(route('admin.qal.catalogos.index'))->assertOk();
    $this->actingAs($usuario)->get(route('admin.qal.formularios'))->assertForbidden();
    $this->actingAs($usuario)->get(route('admin.qal.dashboard'))->assertForbidden();
});
