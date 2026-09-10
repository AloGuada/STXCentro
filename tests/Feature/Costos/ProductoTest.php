<?php

use App\Models\Costos\Producto;
use App\Models\Costos\ProductoPrecio;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['ver', 'crear', 'editar', 'eliminar'] as $accion) {
        Permission::firstOrCreate(['name' => "costos.productos.{$accion}", 'guard_name' => 'web']);
    }
    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'costos.productos.ver',
        'costos.productos.crear',
        'costos.productos.editar',
        'costos.productos.eliminar',
    ]);
});

test('lista el catálogo de productos', function () {
    Producto::factory()->count(3)->create();

    $this->actingAs($this->user)
        ->get('/admin/costos/productos')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/costos/productos/index')->has('productos.data', 3));
});

test('requiere permiso para ver el catálogo', function () {
    $sinPermiso = User::factory()->create();

    $this->actingAs($sinPermiso)
        ->get('/admin/costos/productos')
        ->assertForbidden();
});

test('Compras ya no da de alta productos: la puerta es Almacén > Artículos', function () {
    // El producto nace en Almacén con las dos caras del maestro, donde se
    // decide si es insumo o activo. Aquí sólo se consulta y se edita.
    $this->actingAs($this->user)
        ->post('/admin/costos/productos', ['codigo' => 'TORN-14', 'descripcion' => 'Tornillo 1/4"', 'unidad' => 'pza'])
        ->assertStatus(405);

    // Sin ruta de alta, /create cae en el parametro {producto}, que solo
    // acepta DELETE: por eso es 405 y no 404.
    $this->actingAs($this->user)
        ->get('/admin/costos/productos/create')
        ->assertStatus(405);

    expect(Producto::count())->toBe(0);
});

test('actualiza un producto', function () {
    $producto = Producto::factory()->create(['descripcion' => 'Viejo']);

    $this->actingAs($this->user)
        ->put("/admin/costos/productos/{$producto->id}", [
            'codigo' => $producto->codigo,
            'descripcion' => 'Nuevo',
            'unidad' => 'kg',
            'activo' => false,
        ])
        ->assertRedirect();

    $producto->refresh();
    expect($producto->descripcion)->toBe('Nuevo')
        ->and($producto->unidad)->toBe('kg')
        ->and($producto->activo)->toBeFalse();
});

test('elimina un producto y su histórico', function () {
    $producto = Producto::factory()->create();
    ProductoPrecio::factory()->create(['producto_id' => $producto->id]);

    $this->actingAs($this->user)
        ->delete("/admin/costos/productos/{$producto->id}")
        ->assertRedirect();

    expect(Producto::find($producto->id))->toBeNull()
        ->and(ProductoPrecio::where('producto_id', $producto->id)->count())->toBe(0);
});

test('buscar devuelve productos activos por código o descripción', function () {
    Producto::factory()->create(['codigo' => 'ABC-1', 'descripcion' => 'Cemento gris', 'activo' => true]);
    Producto::factory()->create(['descripcion' => 'Arena', 'activo' => true]);
    Producto::factory()->create(['descripcion' => 'Cemento blanco', 'activo' => false]);

    $res = $this->actingAs($this->user)->getJson('/admin/costos/productos/buscar?q=cemento');

    $res->assertOk();
    // Solo el activo que coincide.
    expect(collect($res->json())->pluck('descripcion')->all())->toBe(['Cemento gris']);
});
