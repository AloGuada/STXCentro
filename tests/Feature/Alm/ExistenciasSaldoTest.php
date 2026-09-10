<?php

use App\Enums\Alm\MovimientoTipo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\User;
use App\Services\Alm\AlmacenLedger;
use Spatie\Permission\Models\Permission;

/**
 * El filtro de existencia en la pantalla de almacén.
 *
 * Un renglón en cero no se borra —la ubicación y el costo promedio siguen
 * valiendo para la próxima entrada—, así que «qué se me acabó» es una pregunta
 * que la pantalla tiene que poder contestar, y es la contraria de «qué tengo».
 */
function usuarioQuePreguntaPorSaldo(): User
{
    $usuario = User::factory()->create();

    foreach (['alm.existencias.ver', 'alm.almacenes.ver-todos'] as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        $usuario->givePermissionTo($permiso);
    }

    return $usuario;
}

/** Un artículo que entró y, si se le dice, volvió a salir hasta dejarlo en cero. */
function articuloConMovimiento(Almacen $almacen, string $descripcion, bool $seAcabo): Articulo
{
    $articulo = Articulo::factory()->create(['descripcion' => $descripcion]);
    $ledger = app(AlmacenLedger::class);

    $ledger->registrarPorArticulo(
        almacenId: $almacen->id,
        articuloId: $articulo->id,
        tipo: MovimientoTipo::Entrada,
        cantidad: 10,
        costoUnitario: 5,
    );

    if ($seAcabo) {
        $ledger->registrarPorArticulo(
            almacenId: $almacen->id,
            articuloId: $articulo->id,
            tipo: MovimientoTipo::Salida,
            cantidad: -10,
        );
    }

    return $articulo;
}

test('sin filtro de existencia salen tanto lo que hay como lo que se acabó', function () {
    $almacen = Almacen::factory()->create();
    articuloConMovimiento($almacen, 'TORNILLO A325 3/4', seAcabo: false);
    articuloConMovimiento($almacen, 'ESCOBA VENECIANA', seAcabo: true);

    $this->actingAs(usuarioQuePreguntaPorSaldo())
        ->get(route('admin.alm.existencias.index', ['almacen_id' => $almacen->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('existencias.data', 2));
});

test('«con existencia» deja fuera los renglones en cero', function () {
    $almacen = Almacen::factory()->create();
    articuloConMovimiento($almacen, 'TORNILLO A325 3/4', seAcabo: false);
    articuloConMovimiento($almacen, 'ESCOBA VENECIANA', seAcabo: true);

    $this->actingAs(usuarioQuePreguntaPorSaldo())
        ->get(route('admin.alm.existencias.index', ['almacen_id' => $almacen->id, 'saldo' => 'con_saldo']))
        ->assertInertia(fn ($page) => $page
            ->has('existencias.data', 1)
            ->where('existencias.data.0.descripcion', 'TORNILLO A325 3/4'));
});

test('«sin existencia» trae sólo lo que se acabó', function () {
    $almacen = Almacen::factory()->create();
    articuloConMovimiento($almacen, 'TORNILLO A325 3/4', seAcabo: false);
    articuloConMovimiento($almacen, 'ESCOBA VENECIANA', seAcabo: true);

    $this->actingAs(usuarioQuePreguntaPorSaldo())
        ->get(route('admin.alm.existencias.index', ['almacen_id' => $almacen->id, 'saldo' => 'cero']))
        ->assertInertia(fn ($page) => $page
            ->has('existencias.data', 1)
            ->where('existencias.data.0.descripcion', 'ESCOBA VENECIANA')
            ->where('existencias.data.0.cantidad', 0));
});

test('«sin existencia» basta como pregunta: no hace falta elegir almacén', function () {
    // Los renglones en cero son unos cuantos y son justo lo que se viene a ver,
    // así que la pantalla contesta aunque no se haya elegido bodega.
    $almacen = Almacen::factory()->create();
    articuloConMovimiento($almacen, 'ESCOBA VENECIANA', seAcabo: true);

    $this->actingAs(usuarioQuePreguntaPorSaldo())
        ->get(route('admin.alm.existencias.index', ['saldo' => 'cero']))
        ->assertInertia(fn ($page) => $page->has('existencias.data', 1)->has('totales'));
});

test('«con existencia» por sí solo no dispara la consulta', function () {
    // Acota tan poco que traería el inventario entero, que es justo lo que esta
    // pantalla no hace de entrada.
    $almacen = Almacen::factory()->create();
    articuloConMovimiento($almacen, 'TORNILLO A325 3/4', seAcabo: false);

    $this->actingAs(usuarioQuePreguntaPorSaldo())
        ->get(route('admin.alm.existencias.index', ['saldo' => 'con_saldo']))
        ->assertInertia(fn ($page) => $page->has('existencias.data', 0)->where('totales', null));
});
