<?php

use App\Models\Alm\Activo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Alm\Asignacion;
use App\Models\Alm\Existencia;
use App\Models\Costos\ProductoPrecio;
use App\Models\Item;
use App\Models\Obra;
use App\Services\Alm\FusionadorArticulos;
use Illuminate\Support\Facades\DB;

/**
 * La fusión junta identidades, no material: todo lo que había sigue estando,
 * bajo un solo código, y el sobrante queda desactivado apuntando al que se
 * quedó.
 *
 * Corre en el estado en que corre en producción: con `items` creada y ANTES de
 * la migración que pone el unique de descripción, que es justamente la que se
 * niega mientras haya repetidos. Por eso aquí se quita ese índice: si no, no
 * habría forma de sembrar los duplicados que la fusión existe para retirar.
 */
beforeEach(function (): void {
    DB::statement('DROP INDEX IF EXISTS items_descripcion_activo_unique');
});

function csvFusion(array $renglones): string
{
    $ruta = tempnam(sys_get_temp_dir(), 'fusion').'.csv';
    $lineas = ["\xEF\xBB\xBFgrupo,codigo,descripcion,almacen,stock,conservar,nota"];

    foreach ($renglones as [$grupo, $codigo, $conservar]) {
        $lineas[] = "{$grupo},{$codigo},,,,{$conservar},";
    }

    file_put_contents($ruta, implode("\r\n", $lineas)."\r\n");

    return $ruta;
}

function existenciaDe(Articulo $articulo, Almacen $almacen, float $cantidad, float $costo): Existencia
{
    return Existencia::factory()
        ->conSaldo($cantidad, $costo)
        ->create([
            'almacen_id' => $almacen->id,
            'articulo_id' => $articulo->id,
            'producto_id' => $articulo->producto_id,
        ]);
}

function movimientoDe(Existencia $existencia, float $cantidad): int
{
    return DB::table('alm_movimientos')->insertGetId([
        'existencia_id' => $existencia->id,
        'almacen_id' => $existencia->almacen_id,
        'producto_id' => $existencia->producto_id,
        'articulo_id' => $existencia->articulo_id,
        'tipo' => 'entrada',
        'cantidad' => $cantidad,
        'saldo_antes' => 0,
        'saldo_despues' => $cantidad,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

describe('la lectura del CSV', function () {
    it('rechaza un grupo sin sobreviviente', function () {
        $csv = csvFusion([['mesa', 'ART-00001', ''], ['mesa', 'ART-00002', '']]);

        expect(fn () => app(FusionadorArticulos::class)->gruposDesdeCsv($csv))
            ->toThrow(InvalidArgumentException::class, 'no tiene ningún renglón marcado con SI');
    });

    it('rechaza un grupo con dos sobrevivientes', function () {
        $csv = csvFusion([['mesa', 'ART-00001', 'SI'], ['mesa', 'ART-00002', 'si']]);

        expect(fn () => app(FusionadorArticulos::class)->gruposDesdeCsv($csv))
            ->toThrow(InvalidArgumentException::class, 'más de un renglón marcado con SI');
    });

    it('rechaza códigos que no existen en alm_articulos', function () {
        $a = Articulo::factory()->create(['codigo' => 'ART-00001']);
        $csv = csvFusion([['mesa', $a->codigo, 'SI'], ['mesa', 'ART-99999', '']]);

        $this->artisan('alm:fusionar-articulos', ['csv' => $csv])
            ->expectsOutputToContain('ART-99999')
            ->assertFailed();
    });
});

describe('el simulacro', function () {
    it('enseña el plan y no escribe nada', function () {
        $almacen = Almacen::factory()->create();
        $s = Articulo::factory()->create(['codigo' => 'ART-00001', 'descripcion' => 'Mesa']);
        $x = Articulo::factory()->create(['codigo' => 'ART-00002', 'descripcion' => 'MESA']);
        existenciaDe($s, $almacen, 4, 100);
        existenciaDe($x, Almacen::factory()->create(), 1, 100);

        $csv = csvFusion([['mesa', 'ART-00001', 'SI'], ['mesa', 'ART-00002', '']]);

        $this->artisan('alm:fusionar-articulos', ['csv' => $csv])
            ->expectsOutputToContain('se queda   ART-00001')
            ->expectsOutputToContain('se retira  ART-00002')
            ->expectsOutputToContain('Simulacro')
            ->assertSuccessful();

        expect($x->fresh()->activo)->toBeTrue()
            ->and($x->fresh()->fusionado_en_id)->toBeNull()
            ->and(Existencia::query()->where('articulo_id', $x->id)->count())->toBe(1);
    });
});

describe('la fusión', function () {
    it('reapunta lo de Almacén y Compras al sobreviviente y desactiva el sobrante', function () {
        $almacenA = Almacen::factory()->create();
        $almacenB = Almacen::factory()->create();
        $s = Articulo::factory()->create(['codigo' => 'ART-00001']);
        $x = Articulo::factory()->create(['codigo' => 'ART-00002']);
        $productoX = $x->producto;

        $existenciaX = existenciaDe($x, $almacenB, 5, 20);
        existenciaDe($s, $almacenA, 3, 20);
        $movimiento = movimientoDe($existenciaX, 5);
        $pieza = Activo::factory()->de($productoX, $almacenB)->create(['articulo_id' => $x->id]);
        $precio = ProductoPrecio::factory()->create(['producto_id' => $productoX->id]);

        $csv = csvFusion([['g', 'ART-00001', 'SI'], ['g', 'ART-00002', '']]);

        $this->artisan('alm:fusionar-articulos', ['csv' => $csv, '--force' => true])
            ->expectsOutputToContain('Listo: 1 grupos')
            ->assertSuccessful();

        $x->refresh();
        $productoX->refresh();

        expect($x->activo)->toBeFalse()
            ->and($x->fusionado_en_id)->toBe($s->id)
            ->and($x->producto_id)->toBeNull()
            ->and($productoX->activo)->toBeFalse()
            ->and($productoX->fusionado_en_id)->toBe($s->producto_id)
            ->and($existenciaX->fresh()->articulo_id)->toBe($s->id)
            ->and($existenciaX->fresh()->producto_id)->toBe($s->producto_id)
            ->and(DB::table('alm_movimientos')->find($movimiento)->articulo_id)->toBe($s->id)
            ->and($pieza->fresh()->articulo_id)->toBe($s->id)
            ->and($pieza->fresh()->producto_id)->toBe($s->producto_id)
            ->and($precio->fresh()->producto_id)->toBe($s->producto_id)
            ->and(Existencia::query()->where('articulo_id', $s->id)->count())->toBe(2);
    });

    it('suma las existencias y las particiones cuando el sobrante está en el mismo almacén', function () {
        $almacen = Almacen::factory()->create();
        $obra = Obra::factory()->create();
        $otraObra = Obra::factory()->create();
        $s = Articulo::factory()->create(['codigo' => 'ART-00001']);
        $x = Articulo::factory()->create(['codigo' => 'ART-00002']);

        $existenciaS = existenciaDe($s, $almacen, 10, 100);
        $existenciaX = existenciaDe($x, $almacen, 5, 160);
        Asignacion::factory()->de(4)->create(['existencia_id' => $existenciaS->id, 'obra_id' => $obra->id]);
        Asignacion::factory()->de(3)->create(['existencia_id' => $existenciaX->id, 'obra_id' => $obra->id]);
        Asignacion::factory()->de(2)->create(['existencia_id' => $existenciaX->id, 'obra_id' => $otraObra->id]);
        $movimiento = movimientoDe($existenciaX, 5);

        $csv = csvFusion([['g', 'ART-00001', 'SI'], ['g', 'ART-00002', '']]);

        $this->artisan('alm:fusionar-articulos', ['csv' => $csv, '--force' => true])
            ->expectsOutputToContain('1 existencias sumadas')
            ->assertSuccessful();

        $existenciaS->refresh();

        expect((float) $existenciaS->cantidad)->toBe(15.0)
            ->and((float) $existenciaS->valor)->toBe(1800.0)
            ->and((float) $existenciaS->costo_promedio)->toBe(120.0)
            ->and(Existencia::query()->whereKey($existenciaX->id)->exists())->toBeFalse()
            ->and((float) Asignacion::query()->where('existencia_id', $existenciaS->id)->where('obra_id', $obra->id)->value('cantidad'))->toBe(7.0)
            ->and((float) Asignacion::query()->where('existencia_id', $existenciaS->id)->where('obra_id', $otraObra->id)->value('cantidad'))->toBe(2.0)
            ->and(Asignacion::query()->where('existencia_id', $existenciaX->id)->count())->toBe(0)
            ->and(DB::table('alm_movimientos')->find($movimiento)->existencia_id)->toBe($existenciaS->id);
    });

    it('el sobreviviente sin producto adopta el del sobrante', function () {
        $almacen = Almacen::factory()->create();
        $s = Articulo::factory()->sinLigar()->create(['codigo' => 'ART-00001']);
        $x = Articulo::factory()->create(['codigo' => 'ART-00002']);
        $productoX = $x->producto;
        $existenciaX = existenciaDe($x, $almacen, 2, 50);

        $csv = csvFusion([['g', 'ART-00001', 'SI'], ['g', 'ART-00002', '']]);

        $this->artisan('alm:fusionar-articulos', ['csv' => $csv, '--force' => true])->assertSuccessful();

        expect($s->fresh()->producto_id)->toBe($productoX->id)
            ->and($productoX->fresh()->activo)->toBeTrue()
            ->and($productoX->fresh()->fusionado_en_id)->toBeNull()
            ->and($x->fresh()->producto_id)->toBeNull()
            ->and($existenciaX->fresh()->articulo_id)->toBe($s->id)
            ->and($existenciaX->fresh()->producto_id)->toBe($productoX->id);

        // El maestro también se junta: el producto adoptado se muda al item
        // del sobreviviente y el item que soltó queda inactivo, que es lo que
        // el unique de descripción necesita para dejar de verlo repetido.
        $itemX = $x->fresh()->item_id;

        expect($productoX->fresh()->item_id)->toBe($s->item_id)
            ->and(Item::findOrFail($itemX)->activo)->toBeFalse()
            ->and(Item::findOrFail($s->item_id)->activo)->toBeTrue()
            ->and(Item::query()->where('activo', true)->count())->toBe(1);
    });

    it('aborta sin escribir cuando dos piezas del grupo comparten serie', function () {
        $almacen = Almacen::factory()->create();
        $s = Articulo::factory()->porPieza()->create(['codigo' => 'ART-00001']);
        $x = Articulo::factory()->porPieza()->create(['codigo' => 'ART-00002']);
        Activo::factory()->de($s->producto, $almacen)->create(['articulo_id' => $s->id, 'no_serie' => 'SER-1']);
        Activo::factory()->de($x->producto, $almacen)->create(['articulo_id' => $x->id, 'no_serie' => 'SER-1']);

        $csv = csvFusion([['g', 'ART-00001', 'SI'], ['g', 'ART-00002', '']]);

        $this->artisan('alm:fusionar-articulos', ['csv' => $csv, '--force' => true])
            ->expectsOutputToContain('SER-1')
            ->assertFailed();

        expect($x->fresh()->activo)->toBeTrue();
    });

    it('fusiona varios grupos en una sola corrida', function () {
        $almacen = Almacen::factory()->create();
        $a1 = Articulo::factory()->create(['codigo' => 'ART-00001']);
        $a2 = Articulo::factory()->create(['codigo' => 'ART-00002']);
        $b1 = Articulo::factory()->create(['codigo' => 'ART-00003']);
        $b2 = Articulo::factory()->create(['codigo' => 'ART-00004']);
        existenciaDe($a2, $almacen, 1, 10);
        existenciaDe($b2, $almacen, 2, 10);

        $csv = csvFusion([
            ['a', 'ART-00001', 'SI'], ['a', 'ART-00002', ''],
            ['b', 'ART-00003', 'SI'], ['b', 'ART-00004', ''],
        ]);

        $this->artisan('alm:fusionar-articulos', ['csv' => $csv, '--force' => true])
            ->expectsOutputToContain('Listo: 2 grupos fusionados, 2 artículos y 2 productos desactivados')
            ->assertSuccessful();

        expect($a2->fresh()->fusionado_en_id)->toBe($a1->id)
            ->and($b2->fresh()->fusionado_en_id)->toBe($b1->id)
            ->and((float) DB::table('alm_existencias')->sum('cantidad'))->toBe(3.0);
    });
});

describe('la comprobación aritmética', function () {
    it('revierte todo si el saldo del grupo no cuadra', function () {
        $almacen = Almacen::factory()->create();
        $s = Articulo::factory()->create(['codigo' => 'ART-00001']);
        $x = Articulo::factory()->create(['codigo' => 'ART-00002']);
        existenciaDe($x, $almacen, 5, 20);

        $csv = csvFusion([['g', 'ART-00001', 'SI'], ['g', 'ART-00002', '']]);
        $fusionador = app(FusionadorArticulos::class);
        $planes = $fusionador->planear($fusionador->gruposDesdeCsv($csv));

        // La puerta se prueba sola: un "antes" que no es el de la base tiene que
        // reventar, que es lo que pasaría si la fusión creara o perdiera saldo.
        $comprobar = new ReflectionMethod($fusionador, 'comprobar');

        expect(fn () => $comprobar->invoke($fusionador, $planes[0], ['cantidad' => 999.0, 'valor' => 100.0]))
            ->toThrow(RuntimeException::class, 'no cuadra');

        $this->artisan('alm:fusionar-articulos', ['csv' => $csv, '--force' => true])->assertSuccessful();

        expect((float) DB::table('alm_existencias')->whereIn('articulo_id', [$s->id, $x->id])->sum('cantidad'))->toBe(5.0);
    });
});
