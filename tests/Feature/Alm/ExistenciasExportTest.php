<?php

use App\Enums\Alm\MovimientoTipo;
use App\Exports\Alm\ExistenciasExport;
use App\Models\Alm\Almacen;
use App\Models\Alm\Area;
use App\Models\Alm\Articulo;
use App\Models\User;
use App\Services\Alm\AlmacenLedger;
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

test('descarga un xlsx con lo filtrado y las seis columnas del reporte', function () {
    Excel::fake();
    $this->freezeTime();

    $almacen = Almacen::factory()->create();
    $otro = Almacen::factory()->create();
    $area = Area::factory()->create(['descripcion' => 'Tornillería']);
    $tornillo = Articulo::factory()->create(['codigo' => 'ART-001', 'descripcion' => 'Tornillo 1/2', 'unidad' => 'PZA', 'area_id' => $area->id]);
    $placa = Articulo::factory()->create(['codigo' => 'ART-002', 'descripcion' => 'Placa 1/4', 'unidad' => 'KG']);

    existenciaDe($almacen, $tornillo, 100, 2.5);
    existenciaDe($almacen, $placa, 40, 30);
    existenciaDe($otro, $placa, 7, 30);

    $this->actingAs(usuarioQueExporta())
        ->get(route('admin.alm.existencias.exportar', ['almacen_id' => $almacen->id]))
        ->assertOk();

    Excel::assertDownloaded(archivoEsperado(), function (ExistenciasExport $export): bool {
        $filas = $export->collection()->values();

        expect($export->headings())->toBe(['Código de item', 'Descripción', 'Stock', 'Nombre unidad', 'Precio', 'Nombre área'])
            ->and($filas)->toHaveCount(2)
            ->and($filas[0])->toBe([
                'codigo' => 'ART-002', 'descripcion' => 'Placa 1/4', 'stock' => 40.0, 'unidad' => 'KG', 'precio' => 30.0, 'area' => null,
            ])
            ->and($filas[1])->toBe([
                'codigo' => 'ART-001', 'descripcion' => 'Tornillo 1/2', 'stock' => 100.0, 'unidad' => 'PZA', 'precio' => 2.5, 'area' => 'Tornillería',
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
        fn (ExistenciasExport $export): bool => $export->collection()->count() === 60,
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
        fn (ExistenciasExport $export): bool => $export->collection()->isEmpty(),
    );
});

test('exportar exige el permiso de ver existencias', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.alm.existencias.exportar', ['search' => 'x']))
        ->assertForbidden();
});
