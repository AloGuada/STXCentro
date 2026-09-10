<?php

use App\Models\Alm\Articulo;
use App\Models\Costos\Producto;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * Las dos pantallas dicen con que renglon del otro lado es el mismo material.
 *
 * Es una etiqueta, no un campo: emparejar no se hace desde ninguna de las dos.
 * Se enseñan codigo Y descripcion porque el ligado se revisa comparando las dos
 * descripciones, y un id no dice nada.
 */
function usuarioConPermisos(array $nombres): User
{
    $user = User::factory()->create();

    foreach ($nombres as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($nombres);

    return $user;
}

describe('el articulo dice a que producto de Compras esta conectado', function () {
    it('lo entrega con codigo y descripcion', function () {
        $articulo = Articulo::factory()->create();
        $articulo->producto->update(['codigo' => 'ART-00042', 'descripcion' => 'Tornillo A325']);

        $this->actingAs(usuarioConPermisos(['alm.articulos.ver', 'alm.articulos.editar']))
            ->get(route('admin.alm.articulos.edit', $articulo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('articulo.producto.codigo', 'ART-00042')
                ->where('articulo.producto.descripcion', 'Tornillo A325'));
    });

    it('lo deja en null cuando todavia no se empareja', function () {
        // El que abrio un almacen por carga inicial y nadie ha clasificado.
        $articulo = Articulo::factory()->sinLigar()->create();

        $this->actingAs(usuarioConPermisos(['alm.articulos.ver', 'alm.articulos.editar']))
            ->get(route('admin.alm.articulos.edit', $articulo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('articulo.producto', null));
    });
});

describe('el producto dice a que articulo de Almacen esta conectado', function () {
    it('lo entrega con codigo y descripcion', function () {
        $producto = Producto::factory()->create();
        Articulo::factory()->create([
            'producto_id' => $producto->id,
            'codigo' => 'ART-00042',
            'descripcion' => 'Tornillo A325',
        ]);

        $this->actingAs(usuarioConPermisos(['costos.productos.ver']))
            ->get(route('admin.costos.productos.edit', $producto))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('producto.articulo.codigo', 'ART-00042')
                ->where('producto.articulo.descripcion', 'Tornillo A325'));
    });

    it('lo deja en null cuando el producto no lleva kardex', function () {
        // Un servicio o un flete: no tiene articulo y eso no es un hueco.
        $producto = Producto::factory()->create();

        $this->actingAs(usuarioConPermisos(['costos.productos.ver']))
            ->get(route('admin.costos.productos.edit', $producto))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('producto.articulo', null));
    });
});
