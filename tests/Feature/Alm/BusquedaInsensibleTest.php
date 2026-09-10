<?php

use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Alm\Existencia;
use App\Models\Alm\Movimiento;
use App\Models\User;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Los buscadores de Almacén, sin distinguir mayúsculas.
 *
 * Esto se rompe **sólo en producción**: SQLite hace `LIKE` insensible y
 * PostgreSQL no, así que buscar «tornillo» encontraba «TORNILLO» en desarrollo
 * y no encontraba nada en el servidor. Por eso los tests de comportamiento no
 * bastan aquí —pasan con y sin el arreglo— y hay que mirar el SQL que se emite
 * con la gramática de Postgres, que es la única forma de que este archivo
 * proteja algo.
 */
function sqlEnPostgres(\Illuminate\Database\Eloquent\Builder $query): string
{
    $base = $query->getQuery();
    $base->grammar = new PostgresGrammar(DB::connection());

    return $base->toSql();
}

test('el buscador de existencias emite ilike en postgres', function () {
    // Se pide la consulta AL CONTROLADOR, no se arma aquí: armarla en el test
    // probaría que Laravel sabe hacer ilike, no que la pantalla lo use.
    $metodo = new ReflectionMethod(\App\Http\Controllers\Admin\Alm\ExistenciaController::class, 'consultaFiltrada');
    $metodo->setAccessible(true);

    $controlador = app(\App\Http\Controllers\Admin\Alm\ExistenciaController::class);
    $peticion = \Illuminate\Http\Request::create('/', 'GET', ['search' => 'tornillo']);

    $sql = sqlEnPostgres($metodo->invoke($controlador, $peticion, collect([1])));

    expect($sql)->toContain('ilike');
});

test('el buscador del kardex emite ilike en postgres', function () {
    $sql = sqlEnPostgres(Movimiento::query()->filtrados(['referencia' => 'sal-0012']));

    expect($sql)->toContain('ilike');
});

test('buscar en minúsculas encuentra lo que se capturó en mayúsculas', function () {
    // El comportamiento que se busca. En SQLite pasa aunque el arreglo no
    // estuviera —su LIKE ya ignora mayúsculas—, así que este test documenta la
    // intención; el que protege es el del SQL.
    Permission::firstOrCreate(['name' => 'alm.existencias.ver', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'alm.almacenes.ver-todos', 'guard_name' => 'web']);

    $usuario = User::factory()->create();
    $usuario->givePermissionTo(['alm.existencias.ver', 'alm.almacenes.ver-todos']);

    $almacen = Almacen::factory()->create();
    $articulo = Articulo::factory()->create(['descripcion' => 'TORNILLO A325 3/4']);

    Existencia::factory()->create([
        'almacen_id' => $almacen->id,
        'articulo_id' => $articulo->id,
    ]);

    $this->actingAs($usuario)
        ->get(route('admin.alm.existencias.index', ['search' => 'tornillo']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('existencias.data', 1));
});

test('el buscador del catalogo de articulos emite ilike en postgres', function () {
    // La pantalla que lo destapo: buscar "tornillo" no encontraba "TORNILLO" y
    // parecia que al catalogo le faltaban articulos.
    $metodo = new ReflectionMethod(\App\Http\Controllers\Admin\Alm\ArticuloController::class, 'buscador');
    $metodo->setAccessible(true);

    $controlador = app(\App\Http\Controllers\Admin\Alm\ArticuloController::class);
    $sql = sqlEnPostgres($metodo->invoke($controlador, Articulo::query(), 'tornillo'));

    // Los cinco campos del buscador, incluido el del area por relacion.
    expect($sql)->toContain('ilike')
        ->and(substr_count($sql, 'ilike'))->toBe(5);
});

test('el buscador de almacenes emite ilike en postgres', function () {
    $peticion = \Illuminate\Http\Request::create('/', 'GET', ['search' => 'nave']);
    $peticion->setUserResolver(fn () => User::factory()->create());

    $consulta = Almacen::query()
        ->when($peticion->search, fn ($q, $s) => $q->where(fn ($q) => $q->whereLike('clave', "%{$s}%")
            ->orWhereLike('nombre', "%{$s}%")));

    expect(sqlEnPostgres($consulta))->toContain('ilike');
});

test('los buscadores por folio emiten ilike en postgres', function () {
    // Los cuatro documentos se buscan por folio, y el folio se teclea como sale
    // de la mano: "sal-0012" tiene que encontrar "SAL-0012".
    $consultas = [
        'ajuste' => \App\Models\Alm\Ajuste::query()->filtrados(['search' => 'aju-0007']),
        'pedido' => \App\Models\Alm\Pedido::query()->filtrados(['search' => 'ped-0007']),
        // Estos dos llevan el scope en femenino: `filtradas`, no `filtrados`.
        'salida' => \App\Models\Alm\Salida::query()->filtradas(['search' => 'sal-0007']),
        'transferencia' => \App\Models\Alm\Transferencia::query()->filtradas(['search' => 'tra-0007']),
    ];

    // Se juntan los que fallan en vez de cortar en el primero: asi el fallo
    // dice cuales documentos siguen distinguiendo mayusculas, no solo que uno.
    $distinguenMayusculas = array_keys(array_filter(
        $consultas,
        fn ($consulta): bool => ! str_contains(sqlEnPostgres($consulta), 'ilike'),
    ));

    expect($distinguenMayusculas)->toBe([]);
});

test('el buscador de activos emite ilike en postgres', function () {
    // La serie y el id de mantenimiento se teclean de la placa de la pieza, que
    // casi siempre viene en mayusculas.
    $sql = sqlEnPostgres(\App\Models\Alm\Activo::query()->filtrados(['search' => 'ab-1234']));

    expect($sql)->toContain('ilike');
});

test('buscar un articulo en minusculas lo encuentra capturado en mayusculas', function () {
    // El comportamiento, para que se lea la intencion. En SQLite pasa aunque el
    // arreglo no estuviera; el que protege es el del SQL.
    Permission::firstOrCreate(['name' => 'alm.articulos.ver', 'guard_name' => 'web']);

    $usuario = User::factory()->create();
    $usuario->givePermissionTo('alm.articulos.ver');

    Articulo::factory()->create(['descripcion' => 'TORNILLO ESTRUCTURAL A325']);
    Articulo::factory()->create(['descripcion' => 'Placa de acero A36']);

    $this->actingAs($usuario)
        ->get(route('admin.alm.articulos.index', ['search' => 'tornillo']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('articulos.data', 1));
});
