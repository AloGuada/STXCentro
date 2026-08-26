<?php

use App\Enums\Alm\AjusteMotivo;
use App\Enums\Alm\MovimientoTipo;
use App\Models\Alm\Ajuste;
use App\Models\Alm\Almacen;
use App\Models\Alm\Area;
use App\Models\Alm\Existencia;
use App\Models\Alm\Movimiento;
use App\Models\Alm\Ubicacion;
use App\Models\Costos\Producto;
use App\Models\User;
use Database\Seeders\Alm\SoldaduraSeeder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;

/**
 * El layout tal como se entrega a las áreas: hoja `Insumos` y los 13
 * encabezados en orden, que son el contrato de la carga.
 *
 * @param  list<array<string, mixed>>  $renglones
 */
function layoutDeInsumos(array $renglones, string $nombre = 'LAYOUT.xlsx'): string
{
    $columnas = [
        'DESCRIPCION', 'UNIDAD', 'AREA', 'IDSTEELEX', 'CODIGO_BARRAS',
        'CLASIFICACION_ABC', 'REQUIERE_VERIFICACION', 'STOCK_MINIMO',
        'ALMACEN', 'OBRA', 'UBICACION', 'EXISTENCIA_INICIAL', 'COSTO_UNITARIO',
    ];

    $libro = new Spreadsheet;
    $hoja = $libro->getActiveSheet();
    $hoja->setTitle('Insumos');
    $hoja->fromArray($columnas, null, 'A1');

    foreach (array_values($renglones) as $n => $renglon) {
        $fila = array_map(fn (string $columna) => $renglon[$columna] ?? null, $columnas);
        $hoja->fromArray($fila, null, 'A'.($n + 2));
    }

    $ruta = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('layout_').'_'.$nombre;
    (new Xlsx($libro))->save($ruta);

    return $ruta;
}

beforeEach(function () {
    $this->autoriza = User::factory()->create(['email' => 'almacen@test.mx']);
    $this->almacen = Almacen::factory()->create(['clave' => 'SOL', 'obra_id' => null]);
    $this->area = Area::create(['descripcion' => 'Soldadura', 'activo' => true]);
});

test('el ensayo reporta pero no escribe', function () {
    $ruta = layoutDeInsumos([[
        'DESCRIPCION' => 'ELECTRODO 7018 DE 1/8',
        'UNIDAD' => 'KG',
        'AREA' => 'Soldadura',
        'CLASIFICACION_ABC' => 'B',
        'ALMACEN' => 'SOL',
        'EXISTENCIA_INICIAL' => 100,
        'COSTO_UNITARIO' => 54.84,
    ]]);

    $this->artisan('alm:importar-insumos', ['archivo' => [$ruta]])
        ->assertSuccessful();

    expect(Producto::count())->toBe(0)
        ->and(Ajuste::count())->toBe(0);
});

test('la carga da de alta el artículo y le abre saldo por el kardex', function () {
    $ruta = layoutDeInsumos([[
        'DESCRIPCION' => 'ELECTRODO 7018 DE 1/8',
        'UNIDAD' => 'KG',
        'AREA' => 'Soldadura',
        'IDSTEELEX' => '10023445',
        'CLASIFICACION_ABC' => 'B',
        'REQUIERE_VERIFICACION' => 'NO',
        'STOCK_MINIMO' => 100,
        'ALMACEN' => 'SOL',
        'EXISTENCIA_INICIAL' => 100,
        'COSTO_UNITARIO' => 54.84,
    ]]);

    $this->artisan('alm:importar-insumos', [
        'archivo' => [$ruta],
        '--commit' => true,
        '--usuario' => 'almacen@test.mx',
    ])->assertSuccessful();

    $articulo = Producto::sole();

    expect($articulo->codigo)->toStartWith('ART-')
        // La etiqueta que se imprime es la nuestra cuando la caja no trae una.
        ->and($articulo->codigo_barras)->toBe($articulo->codigo)
        ->and($articulo->descripcion)->toBe('ELECTRODO 7018 DE 1/8')
        ->and($articulo->idsteelex)->toBe('10023445')
        ->and($articulo->area_id)->toBe($this->area->id)
        ->and($articulo->tipo->value)->toBe('insumo')
        ->and($articulo->controla_inventario)->toBeTrue()
        ->and($articulo->se_controla_por_pieza)->toBeFalse()
        ->and($articulo->clasificacion_abc->value)->toBe('B')
        ->and((float) $articulo->stock_minimo)->toBe(100.0);

    $ajuste = Ajuste::sole();

    expect($ajuste->motivo)->toBe(AjusteMotivo::CargaInicial)
        ->and($ajuste->folio)->toStartWith('AJU-')
        ->and($ajuste->autorizado_por)->toBe($this->autoriza->id);

    $existencia = Existencia::sole();

    expect((float) $existencia->cantidad)->toBe(100.0)
        ->and((float) $existencia->costo_promedio)->toBe(54.84)
        ->and((float) $existencia->valor)->toBe(5484.0);

    // El saldo entró por el ledger, no a mano: tiene su asiento en el kardex.
    $movimiento = Movimiento::sole();

    expect($movimiento->tipo)->toBe(MovimientoTipo::Ajuste)
        ->and((float) $movimiento->saldo_antes)->toBe(0.0)
        ->and((float) $movimiento->saldo_despues)->toBe(100.0)
        ->and($movimiento->referencia)->toBe($ajuste->folio);
});

test('sin --usuario no escribe: el ajuste necesita quién lo autoriza', function () {
    $ruta = layoutDeInsumos([[
        'DESCRIPCION' => 'FUNDENTE PARA SOLDADURA',
        'UNIDAD' => 'KG',
        'CLASIFICACION_ABC' => 'A',
        'ALMACEN' => 'SOL',
        'EXISTENCIA_INICIAL' => 900,
        'COSTO_UNITARIO' => 57.55,
    ]]);

    $this->artisan('alm:importar-insumos', ['archivo' => [$ruta], '--commit' => true])
        ->assertFailed();

    expect(Producto::count())->toBe(0);
});

/**
 * El almacén no se inventa: darlo de alta es un acto deliberado —clave, nombre,
 * tipo, responsable— y adivinarlo dejaría el saldo colgado del lugar equivocado.
 */
test('se detiene cuando el almacén del layout no existe', function () {
    $ruta = layoutDeInsumos([[
        'DESCRIPCION' => 'PINTURA GRIS',
        'UNIDAD' => 'PZA',
        'CLASIFICACION_ABC' => 'C',
        'ALMACEN' => 'PIN',
        'EXISTENCIA_INICIAL' => 20,
        'COSTO_UNITARIO' => 1425,
    ]]);

    $this->artisan('alm:importar-insumos', [
        'archivo' => [$ruta],
        '--commit' => true,
        '--usuario' => 'almacen@test.mx',
    ])->assertFailed();

    expect(Producto::count())->toBe(0)
        ->and(Ajuste::count())->toBe(0);
});

/**
 * La cantidad contada a granel con el precio anotado por envase: el promedio
 * nacería multiplicado por el envase y cada salida le cargaría eso de más a la
 * obra. El kardex sella el costo al registrar, así que corregirlo después no
 * repara las salidas ya hechas.
 */
describe('el costo que viene del envase y no de la unidad', function () {
    beforeEach(function () {
        $this->ruta = layoutDeInsumos([[
            'DESCRIPCION' => 'Primario anticorrosivo color gris Pazti ( 200 Lts.)',
            'UNIDAD' => 'LTS',
            'CLASIFICACION_ABC' => 'A',
            'ALMACEN' => 'SOL',
            'EXISTENCIA_INICIAL' => 1650,
            'COSTO_UNITARIO' => 11400,
        ]]);
    });

    test('detiene la carga', function () {
        $this->artisan('alm:importar-insumos', [
            'archivo' => [$this->ruta],
            '--commit' => true,
            '--usuario' => 'almacen@test.mx',
        ])->assertFailed();

        expect(Producto::count())->toBe(0);
    });

    test('con --prorratear-envase entra al costo por unidad', function () {
        $this->artisan('alm:importar-insumos', [
            'archivo' => [$this->ruta],
            '--commit' => true,
            '--usuario' => 'almacen@test.mx',
            '--prorratear-envase' => true,
        ])->assertSuccessful();

        $existencia = Existencia::sole();

        expect((float) $existencia->costo_promedio)->toBe(57.0)
            ->and((float) $existencia->valor)->toBe(94050.0);
    });
});

/**
 * La columna OBRA es a qué trabajo está asignado el insumo, no dónde vive: la
 * existencia es almacén+artículo y no tiene esa dimensión. No mueve saldo, pero
 * tampoco se tira.
 */
test('la obra asignada queda escrita en el renglón del ajuste', function () {
    $ruta = layoutDeInsumos([[
        'DESCRIPCION' => 'Sigmadur 550 Base White ( 17.6 Lts.)',
        'UNIDAD' => 'PZA',
        'CLASIFICACION_ABC' => 'A',
        'ALMACEN' => 'SOL',
        'OBRA' => 'T4 Aeropuerto',
        'EXISTENCIA_INICIAL' => 20,
        'COSTO_UNITARIO' => 4000,
    ]]);

    $this->artisan('alm:importar-insumos', [
        'archivo' => [$ruta],
        '--commit' => true,
        '--usuario' => 'almacen@test.mx',
    ])->assertSuccessful();

    expect(Ajuste::sole()->detalles()->sole()->observaciones)
        ->toContain('Asignado a: T4 Aeropuerto');
});

test('acomoda la existencia cuando el renglón trae ubicación', function () {
    $ubicacion = Ubicacion::create([
        'almacen_id' => $this->almacen->id,
        'codigo' => 'B-1',
        'nombre' => 'Rack B-1',
        'tipo' => 'rack',
        'activa' => true,
    ]);

    $ruta = layoutDeInsumos([[
        'DESCRIPCION' => 'GRANALLA DE ACERO',
        'UNIDAD' => 'KG',
        'CLASIFICACION_ABC' => 'A',
        'ALMACEN' => 'SOL',
        'UBICACION' => 'B-1',
        'EXISTENCIA_INICIAL' => 500,
        'COSTO_UNITARIO' => 25.76,
    ]]);

    $this->artisan('alm:importar-insumos', [
        'archivo' => [$ruta],
        '--commit' => true,
        '--usuario' => 'almacen@test.mx',
    ])->assertSuccessful();

    expect(Existencia::sole()->ubicacion_id)->toBe($ubicacion->id);
});

/**
 * Dos renglones con el mismo nombre serían dos artículos distintos, y el
 * catálogo queda con un duplicado que nadie va a depurar.
 */
test('se detiene cuando el layout repite una descripción', function () {
    $renglon = [
        'DESCRIPCION' => 'SOLDADURA DE ALAMBRE 0.45',
        'UNIDAD' => 'KG',
        'CLASIFICACION_ABC' => 'A',
        'ALMACEN' => 'SOL',
        'EXISTENCIA_INICIAL' => 100,
        'COSTO_UNITARIO' => 49.64,
    ];

    $ruta = layoutDeInsumos([$renglon, $renglon]);

    $this->artisan('alm:importar-insumos', [
        'archivo' => [$ruta],
        '--commit' => true,
        '--usuario' => 'almacen@test.mx',
    ])->assertFailed();

    expect(Producto::count())->toBe(0);
});

/**
 * Sin almacén el renglón es alta de catálogo y ya: el artículo queda en ceros y
 * no estrena existencia.
 */
test('el renglón sin almacén sólo da de alta el artículo', function () {
    $ruta = layoutDeInsumos([[
        'DESCRIPCION' => 'CARETA PARA SOLDAR',
        'UNIDAD' => 'PZA',
        'CLASIFICACION_ABC' => 'C',
    ]]);

    $this->artisan('alm:importar-insumos', [
        'archivo' => [$ruta],
        '--commit' => true,
        '--usuario' => 'almacen@test.mx',
    ])->assertSuccessful();

    expect(Producto::count())->toBe(1)
        ->and(Ajuste::count())->toBe(0)
        ->and(Existencia::count())->toBe(0);
});

test('rechaza el archivo cuyo encabezado no es el del layout', function () {
    $libro = new Spreadsheet;
    $libro->getActiveSheet()->setTitle('Insumos');
    $libro->getActiveSheet()->fromArray(['ARTICULO', 'CANTIDAD'], null, 'A1');

    $ruta = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('layout_').'_ROTO.xlsx';
    (new Xlsx($libro))->save($ruta);

    $this->artisan('alm:importar-insumos', ['archivo' => [$ruta]])
        ->assertFailed();
});

/**
 * El seeder por almacen es la envoltura: apunta a su layout —el que quedo en el
 * repo como registro de con que se arranco ese inventario— y delega en el
 * comando, que es donde vive la validacion.
 */
test('el seeder de soldadura carga el layout guardado en el repo', function () {
    $this->autoriza->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));

    $this->seed(SoldaduraSeeder::class);

    expect(Producto::count())->toBe(11)
        ->and(Ajuste::sole()->motivo)->toBe(AjusteMotivo::CargaInicial)
        ->and(round((float) Existencia::sum('valor'), 2))->toBe(287204.20);
});
