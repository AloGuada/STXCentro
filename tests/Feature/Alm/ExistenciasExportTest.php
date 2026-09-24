<?php

use App\Enums\Alm\MovimientoTipo;
use App\Exports\Alm\ExistenciasAlmacenSheet;
use App\Exports\Alm\ExistenciasExport;
use App\Models\Alm\Almacen;
use App\Models\Alm\Area;
use App\Models\Alm\Articulo;
use App\Models\Obra;
use App\Models\User;
use App\Services\Alm\AlmacenLedger;
use App\Services\Alm\RegistradorPiezas;
use App\Services\Alm\RegistradorPrestamos;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;

/**
 * El Excel de existencias es la pantalla, entera: mismos filtros, misma
 * visibilidad, sin paginar. Y sin pregunta no hay archivo, igual que no hay
 * tabla.
 */
function usuarioQueExporta(array $extra = ['alm.almacenes.ver-todos']): User
{
    $usuario = User::factory()->create();

    foreach (['alm.existencias.ver', ...$extra] as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        $usuario->givePermissionTo($permiso);
    }

    return $usuario;
}

/** El nombre que el controlador le pone al archivo, con el reloj congelado. */
function archivoEsperado(): string
{
    return 'existencias-'.now()->format('Ymd-Hi').'.xlsx';
}

/**
 * Las hojas del libro por nombre, cada una con sus renglones.
 *
 * @return array<string, list<array<string, mixed>>>
 */
function hojasDe(ExistenciasExport $export): array
{
    return collect($export->sheets())
        ->mapWithKeys(fn (ExistenciasAlmacenSheet $hoja): array => [$hoja->title() => $hoja->collection()->values()->all()])
        ->all();
}

function existenciaDe(Almacen $almacen, Articulo $articulo, float $cantidad, float $costo): void
{
    app(AlmacenLedger::class)->registrarPorArticulo(
        almacenId: $almacen->id,
        articuloId: $articulo->id,
        tipo: MovimientoTipo::Entrada,
        cantidad: $cantidad,
        costoUnitario: $costo,
    );
}

test('descarga un xlsx con lo filtrado y las once columnas del reporte', function () {
    Excel::fake();
    $this->freezeTime();

    $almacen = Almacen::factory()->create(['nombre' => 'Almacén de Construcción MBP']);
    $otro = Almacen::factory()->create();
    $area = Area::factory()->create(['descripcion' => 'Tornillería']);
    $tornillo = Articulo::factory()->create(['codigo' => 'ART-001', 'descripcion' => 'Tornillo 1/2', 'unidad' => 'PZA', 'area_id' => $area->id]);
    $pulidora = Articulo::factory()->activoPorCantidad()->create(['codigo' => 'ART-002', 'descripcion' => 'Pulidora 4 1/2', 'unidad' => 'PZA']);

    existenciaDe($almacen, $tornillo, 100, 2.5);
    existenciaDe($almacen, $pulidora, 4, 1500);
    existenciaDe($otro, $pulidora, 7, 1500);

    $this->actingAs(usuarioQueExporta())
        ->get(route('admin.alm.existencias.exportar', ['almacen_id' => $almacen->id]))
        ->assertOk();

    Excel::assertDownloaded(archivoEsperado(), function (ExistenciasExport $export): bool {
        $hojas = hojasDe($export);
        $filas = $hojas['Construcción MBP'];

        expect(array_keys($hojas))->toBe(['Construcción MBP'])
            ->and($export->sheets()[0]->headings())->toBe(['Almacén', 'Código de item', 'Descripción', 'Tipo', 'Stock', 'Prestado', 'Nombre unidad', 'Precio', 'Nombre área', 'Prestado a', 'Ubicación del préstamo'])
            ->and($filas)->toHaveCount(2)
            ->and($filas[0])->toBe([
                'almacen' => 'Almacén de Construcción MBP', 'codigo' => 'ART-002', 'descripcion' => 'Pulidora 4 1/2', 'tipo' => 'Activo', 'stock' => 4.0, 'prestado' => 0.0, 'unidad' => 'PZA', 'precio' => 1500.0, 'area' => null, 'prestado_a' => null, 'ubicacion_prestamo' => null,
            ])
            ->and($filas[1])->toBe([
                'almacen' => 'Almacén de Construcción MBP', 'codigo' => 'ART-001', 'descripcion' => 'Tornillo 1/2', 'tipo' => 'Insumo', 'stock' => 100.0, 'prestado' => 0.0, 'unidad' => 'PZA', 'precio' => 2.5, 'area' => 'Tornillería', 'prestado_a' => null, 'ubicacion_prestamo' => null,
            ]);

        return true;
    });
});

test('una hoja por almacén activo, con su lista y nada de los demás', function () {
    Excel::fake();
    $this->freezeTime();

    $mbp = Almacen::factory()->create(['nombre' => 'Almacén de Construcción MBP']);
    $t4 = Almacen::factory()->create(['nombre' => 'Almacén de Construcción AMPLIACION T4']);
    $dadoDeBaja = Almacen::factory()->create(['nombre' => 'Almacén de Construcción TRES GUERRAS']);
    $arnes = Articulo::factory()->create(['descripcion' => 'Arnés de cuerpo completo']);
    $casco = Articulo::factory()->create(['descripcion' => 'Casco de seguridad']);

    existenciaDe($mbp, $arnes, 3, 390);
    existenciaDe($t4, $casco, 10, 95);
    existenciaDe($mbp, $casco, 6, 95);
    existenciaDe($dadoDeBaja, $casco, 4, 95);
    $dadoDeBaja->update(['activo' => false]);

    $this->actingAs(usuarioQueExporta())
        ->get(route('admin.alm.existencias.exportar', ['search' => 'c']))
        ->assertOk();

    Excel::assertDownloaded(archivoEsperado(), function (ExistenciasExport $export): bool {
        $hojas = collect(hojasDe($export))
            ->map(fn (array $filas): array => array_map(fn (array $f): array => [$f['descripcion'], $f['stock']], $filas))
            ->all();

        expect($hojas)->toBe([
            'Construcción AMPLIACION T4' => [['Casco de seguridad', 10.0]],
            'Construcción MBP' => [['Arnés de cuerpo completo', 3.0], ['Casco de seguridad', 6.0]],
        ]);

        return true;
    });
});

test('el nombre de la hoja cabe en los 31 caracteres de Excel y no se repite', function () {
    Excel::fake();
    $this->freezeTime();

    $casco = Articulo::factory()->create(['descripcion' => 'Casco de seguridad']);

    foreach (['Almacén de Construcción TERRAZA ERNESTO ROSADO', 'Herramienta MBP', 'Herramienta MBP', 'Pintura: nave D/K'] as $nombre) {
        existenciaDe(Almacen::factory()->create(['nombre' => $nombre]), $casco, 1, 1);
    }

    $this->actingAs(usuarioQueExporta())
        ->get(route('admin.alm.existencias.exportar', ['search' => 'casco']))
        ->assertOk();

    Excel::assertDownloaded(archivoEsperado(), function (ExistenciasExport $export): bool {
        expect(array_keys(hojasDe($export)))->toBe([
            'Construcción TERRAZA ERNESTO RO',
            'Herramienta MBP',
            'Herramienta MBP (2)',
            'Pintura  nave D K',
        ]);

        return true;
    });
});

test('lo prestado sigue en el stock y dice cuánto, con quién y en dónde', function () {
    Excel::fake();
    $this->freezeTime();

    $almacen = Almacen::factory()->create(['nombre' => 'Pañol de Herramienta']);
    $piezas = app(RegistradorPiezas::class);
    $prestamos = app(RegistradorPrestamos::class);

    $pulidora = Articulo::factory()->porPieza()->create(['descripcion' => 'Pulidora 4 1/2']);
    [$pul1, $pul2] = $piezas->alta($pulidora, $almacen, [['no_serie' => 'PUL-1'], ['no_serie' => 'PUL-2'], ['no_serie' => 'PUL-3']]);
    $arnes = Articulo::factory()->activoPorCantidad()->create(['descripcion' => 'Arnés de cuerpo completo']);
    $piezas->altaPorCantidad($arnes, $almacen, 20, 390);

    $juan = supervisorDeAlmacen('Juan Pérez');
    $ana = supervisorDeAlmacen('Ana López');
    $obra = Obra::factory()->create(['no' => 'MBP']);
    $cabecera = fn (string $responsable, ?int $obraId): array => [
        'responsable_id' => $responsable, 'obra_id' => $obraId, 'fecha_salida' => today()->toDateString(),
    ];

    $prestamos->prestar($almacen, $cabecera($juan->id, $obra->id), [
        ['articulo_id' => $pulidora->id, 'activo_id' => $pul1->id],
        ['articulo_id' => $arnes->id, 'cantidad' => 3],
    ]);
    $prestamos->prestar($almacen, $cabecera($juan->id, $obra->id), [['articulo_id' => $pulidora->id, 'activo_id' => $pul2->id]]);
    $prestamos->prestar($almacen, $cabecera($ana->id, null), [['articulo_id' => $arnes->id, 'cantidad' => 2]]);

    $this->actingAs(usuarioQueExporta())
        ->get(route('admin.alm.existencias.exportar', ['almacen_id' => $almacen->id]))
        ->assertOk();

    Excel::assertDownloaded(archivoEsperado(), function (ExistenciasExport $export): bool {
        $filas = array_map(
            fn (array $f): array => [$f['descripcion'], $f['stock'], $f['prestado'], $f['prestado_a'], $f['ubicacion_prestamo']],
            hojasDe($export)['Pañol de Herramienta'],
        );

        expect($filas)->toBe([
            ['Arnés de cuerpo completo', 20.0, 5.0, "Juan Pérez (3)\nAna López (2)", "MBP\nPlanta"],
            ['Pulidora 4 1/2', 3.0, 2.0, 'Juan Pérez (2)', 'MBP'],
        ]);

        return true;
    });
});

test('respeta el buscador y trae todo lo filtrado, no una pagina', function () {
    Excel::fake();
    $this->freezeTime();

    $almacen = Almacen::factory()->create();

    foreach (range(1, 60) as $i) {
        existenciaDe($almacen, Articulo::factory()->create(['descripcion' => "Tuerca {$i}"]), 1, 1);
    }
    existenciaDe($almacen, Articulo::factory()->create(['descripcion' => 'Rondana']), 1, 1);

    $this->actingAs(usuarioQueExporta())
        ->get(route('admin.alm.existencias.exportar', ['search' => 'tuerca']))
        ->assertOk();

    Excel::assertDownloaded(
        archivoEsperado(),
        fn (ExistenciasExport $export): bool => count(array_merge(...array_values(hojasDe($export)))) === 60,
    );
});

test('sin filtro no hay reporte, igual que no hay tabla', function () {
    Excel::fake();
    $this->freezeTime();

    $this->actingAs(usuarioQueExporta())
        ->from(route('admin.alm.existencias.index'))
        ->get(route('admin.alm.existencias.exportar'))
        ->assertRedirect(route('admin.alm.existencias.index'))
        ->assertSessionHasErrors('filtros');
});

test('solo exporta los almacenes que el usuario puede ver', function () {
    Excel::fake();
    $this->freezeTime();

    $ajeno = Almacen::factory()->create();
    existenciaDe($ajeno, Articulo::factory()->create(['descripcion' => 'Cable']), 5, 1);

    $this->actingAs(usuarioQueExporta(extra: []))
        ->get(route('admin.alm.existencias.exportar', ['search' => 'cable']))
        ->assertOk();

    Excel::assertDownloaded(
        archivoEsperado(),
        fn (ExistenciasExport $export): bool => hojasDe($export) === ['Existencias' => []],
    );
});

test('exportar exige el permiso de ver existencias', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.alm.existencias.exportar', ['search' => 'x']))
        ->assertForbidden();
});
