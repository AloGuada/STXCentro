<?php

use App\Exceptions\Catalogo\ItemDuplicadoException;
use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Costos\Producto;
use App\Models\Item;
use App\Models\User;
use App\Services\Alm\ResolvedorArticulo;
use App\Services\Catalogo\CatalogoMaestro;
use Illuminate\Database\QueryException;
use Spatie\Permission\Models\Permission;

/**
 * El catálogo maestro: una identidad por insumo, compartida por Compras y
 * Almacén. Aquí se fijan las reglas que cierran las cinco puertas por las que
 * nacían duplicados: la recepción, la carga inicial, el alta manual, la
 * requisición y la edición por separado de cada cara.
 */
describe('normalizar', function () {
    test('ignora mayúsculas, acentos y dobles espacios', function () {
        expect(CatalogoMaestro::normalizar('  Careta   FACIAL '))->toBe('careta facial')
            ->and(CatalogoMaestro::normalizar('Línea de vida'))->toBe('linea de vida')
            ->and(CatalogoMaestro::normalizar('TORNILLO  A-325  DE 1 1/2'))->toBe('tornillo a-325 de 1 1/2');
    });
});

describe('las caras nacen con item', function () {
    test('un producto nuevo estrena item con su código, descripción y unidad', function () {
        $producto = Producto::factory()->create(['codigo' => 'ART-00001', 'descripcion' => 'Disco de corte', 'unidad' => 'PZA']);

        expect($producto->item)->not->toBeNull()
            ->and($producto->item->codigo)->toBe('ART-00001')
            ->and($producto->item->descripcion)->toBe('Disco de corte')
            ->and($producto->item->descripcion_normalizada)->toBe('disco de corte')
            ->and($producto->item->unidad)->toBe('PZA');
    });

    test('un artículo con producto comparte el item del producto', function () {
        $producto = Producto::factory()->create();
        $articulo = Articulo::factory()->create(['producto_id' => $producto->id]);

        expect($articulo->item_id)->toBe($producto->item_id);
    });

    test('un artículo sin producto que se llama como un producto nace ligado a él', function () {
        // La carga inicial de un almacén con algo que Compras ya compra: antes
        // nacía suelto y alguien tenía que ligarlo a mano. Ahora el maestro
        // los junta y el artículo adopta el código del producto.
        $producto = Producto::factory()->create(['codigo' => 'ART-00321', 'descripcion' => 'Careta de soldador']);

        $articulo = Articulo::factory()->sinLigar()->create(['codigo' => null, 'descripcion' => 'CARETA DE SOLDADOR']);

        expect($articulo->item_id)->toBe($producto->item_id)
            ->and($articulo->producto_id)->toBe($producto->id)
            ->and($articulo->codigo)->toBe('ART-00321')
            ->and($articulo->descripcion)->toBe('Careta de soldador');
    });

    test('un producto que se llama como un artículo suelto se liga a él', function () {
        // El camino inverso: el almacén abrió con el material y Compras lo
        // compra por primera vez después.
        $articulo = Articulo::factory()->sinLigar()->create(['descripcion' => 'PRIMARIO GRIS']);

        $producto = Producto::factory()->create(['descripcion' => 'primario gris']);

        expect($producto->item_id)->toBe($articulo->item_id)
            ->and(app(ResolvedorArticulo::class)->paraProducto($producto->id))->toBe($articulo->id)
            ->and($articulo->fresh()->producto_id)->toBe($producto->id);
    });

    test('dos productos no pueden llamarse igual', function () {
        Producto::factory()->create(['descripcion' => 'Taladro magnético']);

        expect(fn () => Producto::factory()->create(['descripcion' => 'TALADRO MAGNETICO']))
            ->toThrow(ItemDuplicadoException::class);
    });

    test('dos artículos no pueden llamarse igual', function () {
        Articulo::factory()->sinLigar()->create(['descripcion' => 'Mini esmeril de 4 1/2']);

        expect(fn () => Articulo::factory()->sinLigar()->create(['descripcion' => 'mini esmeril de 4 1/2']))
            ->toThrow(ItemDuplicadoException::class);
    });

    test('el índice del maestro tampoco deja dos activos iguales', function () {
        Item::factory()->create(['descripcion' => 'Arnés']);

        expect(fn () => Item::factory()->create(['descripcion' => 'ARNES']))->toThrow(QueryException::class);
    });

    test('un inactivo no estorba: el nombre se puede volver a usar', function () {
        Item::factory()->create(['descripcion' => 'Arnés', 'activo' => false]);

        expect(Item::factory()->create(['descripcion' => 'ARNES'])->activo)->toBeTrue();
    });
});

describe('la recepción ya no fabrica duplicados', function () {
    test('el resolvedor encuentra el artículo por el item aunque no apunte al producto', function () {
        $articulo = Articulo::factory()->sinLigar()->create(['descripcion' => 'ROTO MARTILLO']);
        $producto = Producto::factory()->create(['descripcion' => 'Roto martillo']);

        $resolvedor = app(ResolvedorArticulo::class);

        expect($resolvedor->paraProducto($producto->id))->toBe($articulo->id)
            ->and(Articulo::count())->toBe(1);
    });

    test('crea el artículo bajo el mismo item la primera vez que el producto pisa una bodega', function () {
        $producto = Producto::factory()->create(['descripcion' => 'Electrodo 7018']);

        $articuloId = app(ResolvedorArticulo::class)->paraProducto($producto->id);

        expect(Articulo::findOrFail($articuloId)->item_id)->toBe($producto->item_id);
    });
});

describe('un solo nombre', function () {
    test('renombrar el producto renombra el artículo', function () {
        $producto = Producto::factory()->create(['descripcion' => 'DISCO CORTE', 'unidad' => 'PZA']);
        $articulo = Articulo::factory()->create(['producto_id' => $producto->id, 'descripcion' => 'DISCO CORTE', 'unidad' => 'PZA']);

        $producto->update(['descripcion' => 'Disco de corte 4 1/2', 'unidad' => 'CTO']);

        expect($articulo->fresh()->descripcion)->toBe('Disco de corte 4 1/2')
            ->and($articulo->fresh()->unidad)->toBe('CTO')
            ->and($producto->item->fresh()->descripcion_normalizada)->toBe('disco de corte 4 1/2');
    });

    test('renombrar el item baja a las dos caras', function () {
        $producto = Producto::factory()->create(['descripcion' => 'Guantes']);
        $articulo = Articulo::factory()->create(['producto_id' => $producto->id, 'descripcion' => 'Guantes']);

        $producto->item->update(['descripcion' => 'Guantes de carnaza']);

        expect($producto->fresh()->descripcion)->toBe('Guantes de carnaza')
            ->and($articulo->fresh()->descripcion)->toBe('Guantes de carnaza');
    });
});

describe('las altas manuales', function () {
    function usuarioDeCatalogo(): User
    {
        $user = User::factory()->create();

        foreach (['alm.articulos.crear', 'alm.articulos.editar', 'costos.productos.crear', 'costos.productos.editar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $user->givePermissionTo(['alm.articulos.crear', 'alm.articulos.editar', 'costos.productos.crear', 'costos.productos.editar']);

        return $user;
    }

    test('Compras no puede dar de alta un producto que ya existe', function () {
        Producto::factory()->create(['codigo' => 'ART-00010', 'descripcion' => 'Cinta teflón']);

        $this->actingAs(usuarioDeCatalogo())
            ->post(route('admin.costos.productos.store'), ['descripcion' => 'cinta teflon', 'unidad' => 'PZA'])
            ->assertSessionHasErrors(['descripcion']);

        expect(Producto::count())->toBe(1);
    });

    test('Almacén no puede dar de alta un artículo que ya existe', function () {
        Articulo::factory()->create(['descripcion' => 'Cinta teflón']);

        $this->actingAs(usuarioDeCatalogo())
            ->post(route('admin.alm.articulos.store'), [
                'descripcion' => 'CINTA TEFLON', 'unidad' => 'PZA', 'tipo' => 'insumo', 'clasificacion_abc' => 'C',
            ])
            ->assertSessionHasErrors(['descripcion']);

        expect(Articulo::count())->toBe(1);
    });

    test('Almacén sí puede dar de alta la cara de un producto que Compras ya tiene', function () {
        $producto = Producto::factory()->create(['codigo' => 'ART-00077', 'descripcion' => 'Cinta teflón', 'unidad' => 'PZA']);

        $this->actingAs(usuarioDeCatalogo())
            ->post(route('admin.alm.articulos.store'), [
                'descripcion' => 'cinta teflon', 'unidad' => 'PZA', 'tipo' => 'insumo', 'clasificacion_abc' => 'C',
            ])
            ->assertSessionDoesntHaveErrors();

        $articulo = Articulo::sole();

        expect($articulo->producto_id)->toBe($producto->id)
            ->and($articulo->item_id)->toBe($producto->item_id)
            ->and($articulo->codigo)->toBe('ART-00077')
            ->and(Producto::count())->toBe(1);
    });

    test('editar a un nombre ocupado se rechaza', function () {
        Producto::factory()->create(['descripcion' => 'Cinta teflón']);
        $otro = Producto::factory()->create(['descripcion' => 'Cinta aislante']);

        $this->actingAs(usuarioDeCatalogo())
            ->put(route('admin.costos.productos.update', $otro), ['descripcion' => 'CINTA TEFLON', 'unidad' => 'PZA'])
            ->assertSessionHasErrors(['descripcion']);

        expect($otro->fresh()->descripcion)->toBe('Cinta aislante');
    });

    test('editar conservando el nombre propio no se rechaza', function () {
        $producto = Producto::factory()->create(['descripcion' => 'Cinta teflón', 'unidad' => 'PZA']);

        $this->actingAs(usuarioDeCatalogo())
            ->put(route('admin.costos.productos.update', $producto), ['descripcion' => 'Cinta teflón', 'unidad' => 'CTO'])
            ->assertSessionDoesntHaveErrors();

        expect($producto->fresh()->unidad)->toBe('CTO');
    });
});

describe('la existencia se parte por almacén, no la identidad', function () {
    test('un segundo almacén no estrena artículo', function () {
        // Lo que hacía la carga inicial: un artículo nuevo por almacén con el
        // mismo nombre. Ahora el maestro devuelve el que ya existe.
        $maestro = app(CatalogoMaestro::class);
        Almacen::factory()->count(2)->create();

        $primero = $maestro->buscarOCrear('Taladro magnético', 'PZA');
        $segundo = $maestro->buscarOCrear('TALADRO MAGNETICO', 'PZA');

        expect($segundo->is($primero))->toBeTrue()
            ->and(Item::count())->toBe(1);
    });
});
