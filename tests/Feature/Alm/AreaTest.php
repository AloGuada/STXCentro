<?php

use App\Models\Alm\Area;
use App\Models\Costos\Producto;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * Las rutas de almacén llevan middleware de permiso, así que aquí se dan
 * explícitos: un usuario sin ellos no pasa de la puerta.
 *
 * @param  list<string>  $acciones
 */
function usuarioDeAreas(array $acciones = ['ver', 'crear', 'editar']): User
{
    $user = User::factory()->create();

    $nombres = array_map(fn (string $accion): string => "alm.areas.{$accion}", $acciones);

    foreach ($nombres as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($nombres);

    return $user;
}

test('la pantalla abre con el permiso de ver', function () {
    Area::factory()->create(['descripcion' => 'Pintura']);

    $this->actingAs(usuarioDeAreas(['ver']))
        ->get(route('admin.alm.areas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/almacen/areas/index')
            ->has('areas', 1));
});

test('la pantalla queda cerrada sin permiso', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.alm.areas.index'))
        ->assertForbidden();
});

test('se da de alta un area y nace activa', function () {
    $this->actingAs(usuarioDeAreas())
        ->post(route('admin.alm.areas.store'), ['descripcion' => 'Pintura'])
        ->assertRedirect();

    $area = Area::firstWhere('descripcion', 'Pintura');

    expect($area)->not->toBeNull()
        ->and($area->activo)->toBeTrue();
});

test('no se admite la misma descripcion dos veces', function () {
    Area::factory()->create(['descripcion' => 'Pintura']);

    $this->actingAs(usuarioDeAreas())
        ->post(route('admin.alm.areas.store'), ['descripcion' => 'Pintura'])
        ->assertSessionHasErrors('descripcion');

    expect(Area::where('descripcion', 'Pintura')->count())->toBe(1);
});

test('crear exige su propio permiso, verlo no alcanza', function () {
    $this->actingAs(usuarioDeAreas(['ver']))
        ->post(route('admin.alm.areas.store'), ['descripcion' => 'Pintura'])
        ->assertForbidden();

    expect(Area::count())->toBe(0);
});

test('editar cambia la descripcion sin tocar el estado', function () {
    $area = Area::factory()->inactiva()->create(['descripcion' => 'Pintur']);

    $this->actingAs(usuarioDeAreas())
        ->put(route('admin.alm.areas.update', $area), ['descripcion' => 'Pintura'])
        ->assertRedirect();

    $area->refresh();

    expect($area->descripcion)->toBe('Pintura')
        ->and($area->activo)->toBeFalse();
});

/**
 * Renombrar un área no debe chocar consigo misma: la regla de unicidad tiene
 * que ignorar la fila que se está editando.
 */
test('un area puede guardarse con su misma descripcion', function () {
    $area = Area::factory()->create(['descripcion' => 'Pintura']);

    $this->actingAs(usuarioDeAreas())
        ->put(route('admin.alm.areas.update', $area), ['descripcion' => 'Pintura'])
        ->assertSessionHasNoErrors();
});

test('desactivar y reactivar es la unica baja que hay', function () {
    $area = Area::factory()->create();

    $this->actingAs(usuarioDeAreas())
        ->patch(route('admin.alm.areas.toggle', $area))
        ->assertRedirect();

    expect($area->refresh()->activo)->toBeFalse();

    $this->actingAs(usuarioDeAreas())->patch(route('admin.alm.areas.toggle', $area));

    expect($area->refresh()->activo)->toBeTrue();
});

/**
 * 405 y no 404 a propósito: la URI existe —responde a PUT y PATCH—, lo que no
 * existe es el método. Es la respuesta correcta y deja constancia de que aquí
 * no se borra.
 */
test('el catalogo no expone ninguna ruta de borrado', function () {
    $area = Area::factory()->create();

    $this->actingAs(usuarioDeAreas())
        ->delete("/admin/almacen/areas/{$area->id}")
        ->assertStatus(405);

    expect(Area::whereKey($area->id)->exists())->toBeTrue();
});

/**
 * El alta de artículo sigue siendo maqueta, pero su desplegable de áreas es
 * real: si ofreciera las desactivadas, se daría de alta contra un área que ya
 * se sacó de circulación.
 */
test('el alta de articulo solo ofrece las areas activas', function () {
    Area::factory()->create(['descripcion' => 'Pintura']);
    Area::factory()->inactiva()->create(['descripcion' => 'Obsoleta']);

    Permission::firstOrCreate(['name' => 'alm.articulos.crear', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->givePermissionTo('alm.articulos.crear');

    $this->actingAs($user)
        ->get(route('admin.alm.articulos.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('areas', 1)
            ->where('areas.0.descripcion', 'Pintura'));
});

/** Por lo mismo: corregir un artículo tampoco debe reasignarlo a un área muerta. */
test('la edicion de articulo solo ofrece las areas activas', function () {
    Area::factory()->create(['descripcion' => 'Pintura']);
    Area::factory()->inactiva()->create(['descripcion' => 'Obsoleta']);

    Permission::firstOrCreate(['name' => 'alm.articulos.editar', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->givePermissionTo('alm.articulos.editar');

    $articulo = Producto::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.alm.articulos.edit', $articulo))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('areas', 1)
            ->where('areas.0.descripcion', 'Pintura'));
});
