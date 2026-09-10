<?php

use App\Enums\Alm\MovimientoTipo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Area;
use App\Models\Alm\Articulo;
use App\Models\User;
use App\Services\Alm\AlmacenLedger;
use Spatie\Permission\Models\Permission;

/**
 * El área en la pantalla de existencias.
 *
 * El área es la familia del insumo y no gobierna ningún flujo —ni el kardex, ni
 * las salidas, ni los conteos, que van por ABC—: sirve para mirar el inventario
 * por familia sin tener que elegir bodega. «Qué tenemos de tornillería» no es
 * una pregunta sobre un almacén.
 */
function usuarioDeExistencias(): User
{
    $usuario = User::factory()->create();

    foreach (['alm.existencias.ver', 'alm.almacenes.ver-todos'] as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        $usuario->givePermissionTo($permiso);
    }

    return $usuario;
}

/** Un artículo con saldo, del área que se le diga. */
function conSaldoEnArea(Almacen $almacen, ?Area $area, string $descripcion): Articulo
{
    $articulo = Articulo::factory()->create([
        'descripcion' => $descripcion,
        'area_id' => $area?->id,
    ]);

    app(AlmacenLedger::class)->registrarPorArticulo(
        almacenId: $almacen->id,
        articuloId: $articulo->id,
        tipo: MovimientoTipo::Entrada,
        cantidad: 10,
        costoUnitario: 5,
    );

    return $articulo;
}

test('el filtro por área acota el inventario a esa familia', function () {
    $almacen = Almacen::factory()->create();
    $tornilleria = Area::factory()->create(['descripcion' => 'Tornillería']);
    $limpieza = Area::factory()->create(['descripcion' => 'Limpieza']);

    conSaldoEnArea($almacen, $tornilleria, 'TORNILLO A325 3/4');
    conSaldoEnArea($almacen, $tornilleria, 'TUERCA NEGRA 1 1/8');
    conSaldoEnArea($almacen, $limpieza, 'ESCOBA VENECIANA');

    $this->actingAs(usuarioDeExistencias())
        ->get(route('admin.alm.existencias.index', ['area_id' => $tornilleria->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('existencias.data', 2));
});

test('el área basta como pregunta: no hace falta elegir almacén', function () {
    // La pantalla no contesta sin filtro, para no traer el inventario entero.
    // El área acota lo suficiente y es la forma de mirarlo por familia.
    $almacen = Almacen::factory()->create();
    $area = Area::factory()->create(['descripcion' => 'Tornillería']);

    conSaldoEnArea($almacen, $area, 'TORNILLO A325 3/4');

    $this->actingAs(usuarioDeExistencias())
        ->get(route('admin.alm.existencias.index', ['area_id' => $area->id]))
        ->assertInertia(fn ($page) => $page->has('existencias.data', 1)->has('totales'));
});

test('el renglón dice de qué área es, y dice null cuando no tiene', function () {
    // Los dos primeros almacenes entraron sin área: clasificarlos es trabajo
    // pendiente, no un error, y la pantalla tiene que poder mostrarlo.
    $almacen = Almacen::factory()->create();
    $area = Area::factory()->create(['descripcion' => 'Pintura']);

    conSaldoEnArea($almacen, $area, 'PRIMARIO GRIS');
    conSaldoEnArea($almacen, null, 'SIN CLASIFICAR');

    $this->actingAs(usuarioDeExistencias())
        ->get(route('admin.alm.existencias.index', ['almacen_id' => $almacen->id]))
        ->assertInertia(function ($page) {
            $filas = collect($page->toArray()['props']['existencias']['data'])
                ->keyBy('descripcion');

            expect($filas['PRIMARIO GRIS']['area'])->toBe('Pintura')
                ->and($filas['SIN CLASIFICAR']['area'])->toBeNull();
        });
});

test('el renglón dice si el artículo es activo o insumo', function () {
    $almacen = Almacen::factory()->create();

    conSaldoEnArea($almacen, null, 'TORNILLO A325 3/4');

    $pulidora = Articulo::factory()->activoPorCantidad()->create(['descripcion' => 'PULIDORA 4 1/2']);
    app(AlmacenLedger::class)->registrarPorArticulo(
        almacenId: $almacen->id,
        articuloId: $pulidora->id,
        tipo: MovimientoTipo::Entrada,
        cantidad: 2,
        costoUnitario: 1500,
    );

    $this->actingAs(usuarioDeExistencias())
        ->get(route('admin.alm.existencias.index', ['almacen_id' => $almacen->id]))
        ->assertInertia(function ($page) {
            $filas = collect($page->toArray()['props']['existencias']['data'])
                ->keyBy('descripcion');

            expect($filas['TORNILLO A325 3/4']['tipo'])->toBe('insumo')
                ->and($filas['PULIDORA 4 1/2']['tipo'])->toBe('activo');
        });
});

test('la pantalla ofrece las áreas activas para filtrar', function () {
    Area::factory()->create(['descripcion' => 'Tornillería']);
    Area::factory()->inactiva()->create(['descripcion' => 'Obsoleta']);

    $this->actingAs(usuarioDeExistencias())
        ->get(route('admin.alm.existencias.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('areas', 1)
            ->where('areas.0.descripcion', 'Tornillería'));
});
