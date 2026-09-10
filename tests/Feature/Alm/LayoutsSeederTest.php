<?php

use App\Enums\Alm\ActivoEstatus;
use App\Models\Alm\Activo;
use App\Models\Alm\Ajuste;
use App\Models\Alm\Almacen;
use App\Models\Alm\Area;
use App\Models\Alm\Articulo;
use App\Models\Alm\Existencia;
use App\Models\Alm\Ubicacion;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Producto;
use App\Models\Obra;
use App\Models\User;
use Database\Seeders\Alm\CatalogoLayoutSeeder;
use Database\Seeders\Alm\ConstAmpliacionT4Seeder;
use Database\Seeders\Alm\ConstMbpActivosSeeder;
use Database\Seeders\Alm\ConstMbpSeeder;
use Database\Seeders\Alm\MtoActivosSeeder;
use Database\Seeders\Alm\ProdActivosSeeder;
use Spatie\Permission\Models\Role;

/**
 * La segunda tanda de layouts: seis archivos, dos formas —insumos que se
 * cuentan y activos que se siguen pieza por pieza— y almacenes que por primera
 * vez son de obra.
 */
beforeEach(function () {
    $this->autoriza = User::factory()->create();
    $this->autoriza->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
});

test('el catálogo da de alta lo que los layouts dan por hecho', function () {
    Obra::factory()->create(['no' => 'MBP']);

    $this->seed(CatalogoLayoutSeeder::class);

    // El área usó "Tornillería" sin que estuviera en la lista que le dieron.
    expect(Area::where('descripcion', 'Tornillería')->exists())->toBeTrue()
        ->and(Area::where('descripcion', 'Papelería')->exists())->toBeTrue();

    // "En planta" es la casilla que significa central: estos no cuelgan de obra.
    foreach (['CONS', 'INS', 'MON', 'MTO', 'PROD'] as $clave) {
        expect(Almacen::where('clave', $clave)->whereNull('obra_id')->exists())->toBeTrue();
    }

    // CONST es la excepción: la misma clave repetida, una por obra.
    expect(Almacen::where('clave', 'CONST')->whereNotNull('obra_id')->count())->toBe(6);
});

test('el catálogo se puede correr dos veces sin duplicar nada', function () {
    Obra::factory()->create(['no' => 'MBP']);

    $this->seed(CatalogoLayoutSeeder::class);
    $areas = Area::count();
    $almacenes = Almacen::count();

    $this->seed(CatalogoLayoutSeeder::class);

    expect(Area::count())->toBe($areas)
        ->and(Almacen::count())->toBe($almacenes);
});

test('el almacén de obra recibe su saldo y su acomodo', function () {
    Obra::factory()->create(['no' => 'MBP']);
    $this->seed(CatalogoLayoutSeeder::class);

    $this->seed(ConstMbpSeeder::class);

    $almacen = Almacen::where('clave', 'CONST')
        ->whereHas('obra', fn ($q) => $q->where('no', 'MBP'))
        ->sole();

    expect(Articulo::count())->toBe(16)
        ->and(round((float) Existencia::sum('valor'), 2))->toBe(156710.18)
        ->and(Ajuste::where('almacen_id', $almacen->id)->count())->toBe(1);

    // El cable llegó sin unidad en el layout y se asumió metros; la nota del
    // renglón lo dice para que se pueda corregir de un vistazo.
    $cable = Articulo::where('descripcion', 'CABLE DE USO RUDO 2X12')->sole();

    expect($cable->unidad)->toBe('MTS')
        ->and($cable->area->descripcion)->toBe('Herramienta');

    // La ubicación no es saldo: se escribe sobre la existencia que abrió el
    // ledger, y sólo dentro de su propio almacén.
    $existencia = Existencia::where('articulo_id', $cable->id)->sole();
    $ubicacion = Ubicacion::whereKey($existencia->ubicacion_id)->sole();

    expect($ubicacion->codigo)->toBe('OBRA')
        ->and($ubicacion->almacen_id)->toBe($almacen->id);
});

test('cada pieza suma uno a la existencia de su artículo', function () {
    $this->seed(CatalogoLayoutSeeder::class);

    $this->seed(ProdActivosSeeder::class);

    $almacen = Almacen::where('clave', 'PROD')->whereNull('obra_id')->sole();

    expect(Activo::count())->toBe(118)
        ->and(Articulo::count())->toBe(7);

    // El invariante que sostiene Existencias: la cantidad de un artículo por
    // pieza es exactamente el número de sus piezas vigentes.
    foreach (Articulo::all() as $articulo) {
        // Vigentes, no todas: una pieza dada de baja ya no esta en el almacen,
        // y descargarla del kardex es justo lo que la carga inicial hace.
        $piezas = Activo::where('articulo_id', $articulo->id)
            ->where('almacen_id', $almacen->id)
            ->vigentes()
            ->count();
        $existencia = Existencia::where('articulo_id', $articulo->id)->sole();

        expect((float) $existencia->cantidad)->toBe((float) $piezas)
            ->and($articulo->se_controla_por_pieza)->toBeTrue()
            ->and($articulo->tipo->value)->toBe('activo');
    }
});

test('las piezas prestadas nacen prestadas y siguen contando', function () {
    $this->seed(CatalogoLayoutSeeder::class);
    $this->seed(ProdActivosSeeder::class);

    $prestadas = Activo::where('estatus', ActivoEstatus::Prestado)->count();

    expect($prestadas)->toBeGreaterThan(0);

    // Prestado cuenta en existencia igual que disponible: la pulidora sigue
    // siendo del almacén.
    $articulo = Activo::where('estatus', ActivoEstatus::Prestado)->first()->articulo;
    $vigentes = Activo::where('articulo_id', $articulo->id)->whereNot('estatus', ActivoEstatus::Baja)->count();

    expect((float) Existencia::where('articulo_id', $articulo->id)->sole()->cantidad)->toBe((float) $vigentes);
});

test('las piezas sin serie entran con folio provisional y lo dicen', function () {
    Obra::factory()->create(['no' => 'MBP']);
    $this->seed(CatalogoLayoutSeeder::class);

    $this->seed(ConstMbpActivosSeeder::class);

    $pieza = Activo::sole();

    expect($pieza->no_serie)->toStartWith('SIN-SERIE-')
        ->and($pieza->observaciones)->toContain('folio provisional');
});

/**
 * El padrón se pregunta por sus propios artículos y no por si el almacén tiene
 * piezas: que MTO ya guarde una pulidora de otro lado no quiere decir que su
 * padrón esté levantado.
 */
test('una pieza ajena en el almacén no bloquea el padrón', function () {
    $this->seed(CatalogoLayoutSeeder::class);

    $almacen = Almacen::where('clave', 'MTO')->whereNull('obra_id')->sole();
    Activo::factory()->create(['almacen_id' => $almacen->id]);

    $this->seed(MtoActivosSeeder::class);

    expect(Activo::where('almacen_id', $almacen->id)->count())->toBe(7);
});

test('el padrón no se levanta dos veces', function () {
    $this->seed(CatalogoLayoutSeeder::class);

    $this->seed(MtoActivosSeeder::class);
    $this->seed(MtoActivosSeeder::class);

    expect(Activo::count())->toBe(6);
});

/**
 * La carga anterior se llevó las órdenes de compra por el CASCADE de `truncate`
 * en PostgreSQL. Ésta no borra nada, y este test lo deja escrito.
 */
test('cargar no toca las órdenes de compra ni sus renglones', function () {
    Obra::factory()->create(['no' => 'MBP']);

    $orden = OrdenCompra::factory()->create();
    $partida = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $orden->id,
        'producto_id' => Producto::factory()->create()->id,
    ]);

    $this->seed(CatalogoLayoutSeeder::class);
    $this->seed(ConstMbpSeeder::class);
    $this->seed(ConstMbpActivosSeeder::class);

    expect(OrdenCompra::whereKey($orden->id)->exists())->toBeTrue()
        ->and($partida->fresh()->producto_id)->toBe($partida->producto_id);
});

/**
 * `costos_productos` es una sola tabla para Compras y Almacén. Los artículos que
 * Compras ya tecleó no se duplican: la carga les abre existencia y les completa
 * la clasificación, que es lo que hace a mano la pantalla de Artículos.
 */
/**
 * La garantía que reemplazó a la lista congelada de códigos.
 *
 * Antes, abrir un almacén obligaba a emparejar cada renglón contra Compras y a
 * pisarle campos a los que ya existían —entre ellos la unidad, que es de
 * cotización— para ponerles lo que Almacén sabía. Ahora la carga entra a su
 * propia tabla y a Compras no le mueve nada: si el insumo ya existía allá, el
 * artículo nace como su cara de Almacén, ligado y con su código; si no, nace
 * suelto y Compras lo encontrará el día que lo compre.
 */
test('cargar un almacén no toca el catálogo de Compras', function () {
    Obra::factory()->create(['no' => 'MBP']);
    $this->seed(CatalogoLayoutSeeder::class);

    // Así nace en Compras lo que alguien tecleó en una requisición: sin área,
    // sin ABC y con la unidad en minúscula que pone resolverProducto().
    $deCompras = Producto::factory()->create([
        'codigo' => 'ART-00321',
        'descripcion' => 'Careta de soldador',
        'unidad' => 'pza',
        'area_id' => null,
    ]);

    $antes = Producto::count();

    $this->seed(ConstAmpliacionT4Seeder::class);

    // Ni un producto nuevo, ni un campo movido en el que ya estaba.
    expect(Producto::count())->toBe($antes);

    expect($deCompras->fresh())
        ->descripcion->toBe('Careta de soldador')
        ->unidad->toBe('pza')
        ->area_id->toBeNull();

    // Y la careta del layout entró como la cara de Almacén del producto que
    // Compras ya tenía: mismo item, mismo código, ligada desde que nace.
    $careta = Articulo::whereRaw('upper(descripcion) = ?', ['CARETA DE SOLDADOR'])->sole();

    expect($careta->producto_id)->toBe($deCompras->id)
        ->and($careta->item_id)->toBe($deCompras->item_id)
        ->and($careta->codigo)->toBe('ART-00321')
        ->and(Existencia::where('articulo_id', $careta->id)->exists())->toBeTrue();
});
