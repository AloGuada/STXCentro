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
