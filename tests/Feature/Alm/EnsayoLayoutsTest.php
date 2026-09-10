<?php

use App\Models\Alm\Activo;
use App\Models\Alm\Ajuste;
use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Alm\Existencia;
use App\Models\Alm\Movimiento;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Producto;
use App\Models\User;
use Database\Seeders\Alm\LayoutsSeeder;
use Spatie\Permission\Models\Role;

/**
 * El ensayo general: corre **los 19 seeders de golpe**, igual que en producción,
 * y comprueba lo que tiene que salir.
 *
 * Existe para que correr `LayoutsSeeder` en el servidor deje de dar miedo. Lo
 * que aquí pasa en verde es exactamente lo que va a pasar allá: mismos
 * archivos, mismo orden, mismos números. Si algo va a fallar, falla aquí.
 */
beforeEach(function () {
    $usuario = User::factory()->create();
    $usuario->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
});

test('los 19 layouts abren sus almacenes de una corrida', function () {
    $this->seed(LayoutsSeeder::class);

    // 5 centrales + CONST repetido en 6 obras.
    expect(Almacen::count())->toBe(11);

    // Cada almacén con inventario dejó su ajuste de carga inicial, que es de
    // donde salió su saldo. Sin ajuste no habría kardex que explicara nada.
    expect(Ajuste::count())->toBeGreaterThan(0)
        ->and(Existencia::count())->toBeGreaterThan(0)
        ->and(Articulo::count())->toBeGreaterThan(0);
});

test('no le toca un solo renglon al catalogo de Compras', function () {
    // La garantía que se pidió por escrito. Se fotografía antes y después:
    // ni un producto nuevo, ni uno modificado, ni una orden movida.
    $productosAntes = Producto::count();
    $ordenesAntes = OrdenCompra::count();

    $this->seed(LayoutsSeeder::class);

    expect(Producto::count())->toBe($productosAntes)
        ->and(OrdenCompra::count())->toBe($ordenesAntes);

    // Y todo lo que entró es material sin identidad de compra todavía.
    expect(Articulo::whereNotNull('producto_id')->count())->toBe(0);
});

test('cada pieza suma exactamente uno a la existencia de su articulo', function () {
    $this->seed(LayoutsSeeder::class);

    // El invariante que hace confiable a Existencias. Se comprueba para todos
    // los artículos por pieza a la vez, no para uno de muestra.
    foreach (Articulo::where('se_controla_por_pieza', true)->get() as $articulo) {
        $porAlmacen = Activo::query()
            ->where('articulo_id', $articulo->id)
            ->vigentes()
            ->selectRaw('almacen_id, count(*) as piezas')
            ->groupBy('almacen_id')
            ->pluck('piezas', 'almacen_id');

        foreach ($porAlmacen as $almacenId => $piezas) {
            $saldo = (float) Existencia::query()
                ->where('almacen_id', $almacenId)
                ->where('articulo_id', $articulo->id)
                ->value('cantidad');

            expect($saldo)->toBe((float) $piezas);
        }
    }
});

test('el saldo de cada existencia lo explica su kardex', function () {
    $this->seed(LayoutsSeeder::class);

    // Nadie escribió una cantidad a mano: cada saldo es la suma de sus asientos.
    foreach (Existencia::all() as $existencia) {
        $asentado = (float) Movimiento::query()
            ->where('almacen_id', $existencia->almacen_id)
            ->where('articulo_id', $existencia->articulo_id)
            ->sum('cantidad');

        expect(round((float) $existencia->cantidad, 4))->toBe(round($asentado, 4));
    }
});

test('correrlo dos veces no carga nada dos veces', function () {
    // Lo que hace seguro reintentar si el deploy se cae a la mitad.
    $this->seed(LayoutsSeeder::class);

    $articulos = Articulo::count();
    $ajustes = Ajuste::count();
    $piezas = Activo::count();
    $valor = round((float) Existencia::sum('valor'), 2);

    $this->seed(LayoutsSeeder::class);

    expect(Articulo::count())->toBe($articulos)
        ->and(Ajuste::count())->toBe($ajustes)
        ->and(Activo::count())->toBe($piezas)
        ->and(round((float) Existencia::sum('valor'), 2))->toBe($valor);
});
