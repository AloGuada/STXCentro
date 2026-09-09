<?php

use App\Models\Alm\Activo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Alm\Asignacion;
use App\Models\Alm\Existencia;
use App\Models\Costos\Producto;
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

/**
 * Una cara con su propio item aunque ya exista otro con la misma descripción:
 * es el estado que la fusión existe para retirar y que el alta normal ya no
 * permite.
 */
function itemSuelto(string $codigo, string $descripcion): Item
{
    return Item::create(['codigo' => $codigo, 'descripcion' => $descripcion, 'unidad' => 'PZA', 'activo' => true]);
}

function articuloDuplicado(string $codigo, string $descripcion): Articulo
{
    return Articulo::factory()->sinLigar()->create([
        'codigo' => $codigo,
        'descripcion' => $descripcion,
        'unidad' => 'PZA',
        'item_id' => itemSuelto($codigo, $descripcion)->id,
    ]);
}

function productoDuplicado(string $codigo, string $descripcion): Producto
{
    return Producto::factory()->create([
        'codigo' => $codigo,
        'descripcion' => $descripcion,
        'unidad' => 'PZA',
        'item_id' => itemSuelto($codigo, $descripcion)->id,
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
            ->and(Asignacion::query()->where('existencia_id', $existenciaX->id)->count())->toBe(0);

        // El kardex del sobrante se cuelga del renglón que queda con la nota de
        // qué código venía, y la corrida se recalcula para terminar en el saldo
        // de hoy: la existencia S tenía 10 sin movimiento, así que el +5 que
        // venía de X ahora se lee 10 → 15 y no 0 → 5.
        $mov = DB::table('alm_movimientos')->find($movimiento);

        expect($mov->existencia_id)->toBe($existenciaS->id)
            ->and($mov->articulo_id)->toBe($s->id)
            ->and((float) $mov->saldo_antes)->toBe(10.0)
            ->and((float) $mov->saldo_despues)->toBe(15.0)
            ->and($mov->observaciones)->toContain('Era ART-00002');
    });

    it('al juntar dos kardex la corrida se recalcula en orden de fecha', function () {
        $almacen = Almacen::factory()->create();
        $s = Articulo::factory()->create(['codigo' => 'ART-00001']);
        $x = Articulo::factory()->create(['codigo' => 'ART-00002']);
        $existenciaS = existenciaDe($s, $almacen, 30, 10);
        $existenciaX = existenciaDe($x, $almacen, 94, 10);

        // Dos cadenas que se intercalan en el tiempo: S entra 36 el día 5,
        // X entra 100 el día 12 y saca 6 el día 20; S saca 6 el día 25.
        $m1 = movimientoDe($existenciaS, 36);
        $m2 = movimientoDe($existenciaX, 100);
        $m3 = movimientoDe($existenciaX, -6);
        $m4 = movimientoDe($existenciaS, -6);
        DB::table('alm_movimientos')->where('id', $m1)->update(['created_at' => '2026-08-05 10:00:00', 'saldo_antes' => 0, 'saldo_despues' => 36]);
        DB::table('alm_movimientos')->where('id', $m2)->update(['created_at' => '2026-08-12 10:00:00', 'saldo_antes' => 0, 'saldo_despues' => 100]);
        DB::table('alm_movimientos')->where('id', $m3)->update(['created_at' => '2026-08-20 10:00:00', 'saldo_antes' => 100, 'saldo_despues' => 94]);
        DB::table('alm_movimientos')->where('id', $m4)->update(['created_at' => '2026-08-25 10:00:00', 'saldo_antes' => 36, 'saldo_despues' => 30]);

        $csv = csvFusion([['g', 'ART-00001', 'SI'], ['g', 'ART-00002', '']]);

        $this->artisan('alm:fusionar-articulos', ['csv' => $csv, '--force' => true])->assertSuccessful();

        $corrida = DB::table('alm_movimientos')
            ->where('existencia_id', $existenciaS->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m): array => [(float) $m->saldo_antes, (float) $m->saldo_despues])
            ->all();

        expect((float) $existenciaS->fresh()->cantidad)->toBe(124.0)
            ->and($corrida)->toBe([[0.0, 36.0], [36.0, 136.0], [136.0, 130.0], [130.0, 124.0]]);
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

    it('lo que ya era del sobreviviente aprende el producto adoptado, y el ledger lo encuentra por producto', function () {
        $almacenA = Almacen::factory()->create();
        $almacenB = Almacen::factory()->create();
        // El sobreviviente nació suelto y ya tenía saldo en A; el sobrante trae
        // el producto y saldo en B.
        $s = Articulo::factory()->sinLigar()->create(['codigo' => 'ART-00001']);
        $x = Articulo::factory()->create(['codigo' => 'ART-00002']);
        $productoX = $x->producto;
        $existenciaS = existenciaDe($s, $almacenA, 5, 10);
        $movimientoS = movimientoDe($existenciaS, 5);
        existenciaDe($x, $almacenB, 2, 10);

        $csv = csvFusion([['g', 'ART-00001', 'SI'], ['g', 'ART-00002', '']]);

        $this->artisan('alm:fusionar-articulos', ['csv' => $csv, '--force' => true])->assertSuccessful();

        // Sin esto, la siguiente recepción por orden (que abre existencia por
        // producto) no encontraría el renglón de A y trataría de crear otro.
        expect($existenciaS->fresh()->producto_id)->toBe($productoX->id)
            ->and(DB::table('alm_movimientos')->where('id', $movimientoS)->value('producto_id'))->toBe($productoX->id)
            ->and(app(App\Services\Alm\AlmacenLedger::class)->bloquear($almacenA->id, $productoX->id)->id)->toBe($existenciaS->id)
            ->and(Existencia::query()->where('almacen_id', $almacenA->id)->count())->toBe(1);
    });

    it('al sumar existencias conserva el acomodo del que se va si el que se queda no tenía', function () {
        $almacen = Almacen::factory()->create();
        $ubicacion = App\Models\Alm\Ubicacion::factory()->create(['almacen_id' => $almacen->id]);
        $s = Articulo::factory()->create(['codigo' => 'ART-00001']);
        $x = Articulo::factory()->create(['codigo' => 'ART-00002']);
        $existenciaS = existenciaDe($s, $almacen, 1, 10);
        $existenciaX = existenciaDe($x, $almacen, 1, 10);
        $existenciaX->forceFill(['ubicacion_id' => $ubicacion->id])->save();

        $csv = csvFusion([['g', 'ART-00001', 'SI'], ['g', 'ART-00002', '']]);

        $this->artisan('alm:fusionar-articulos', ['csv' => $csv, '--force' => true])->assertSuccessful();

        expect($existenciaS->fresh()->ubicacion_id)->toBe($ubicacion->id)
            ->and((float) $existenciaS->fresh()->cantidad)->toBe(2.0);
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

describe('los grupos automáticos', function () {
    it('exige el CSV o --auto, pero no ambos', function () {
        $this->artisan('alm:fusionar-articulos')->assertFailed();
        $this->artisan('alm:fusionar-articulos', ['csv' => 'x.csv', '--auto' => true])->assertFailed();
    });

    it('no hace nada cuando no hay descripciones repetidas', function () {
        Articulo::factory()->sinLigar()->create(['descripcion' => 'Taladro magnético']);

        $this->artisan('alm:fusionar-articulos', ['--auto' => true, '--force' => true])
            ->expectsOutputToContain('nada que fusionar')
            ->assertSuccessful();
    });

    it('agrupa por descripción repetida y se queda el código más bajo', function () {
        $almacenA = Almacen::factory()->create();
        $almacenB = Almacen::factory()->create();
        $alto = articuloDuplicado('ART-00020', 'Careta facial');
        $plural = articuloDuplicado('ART-00010', 'CARETAS FACIALES');
        $otro = articuloDuplicado('ART-00030', 'careta  facial');
        $exAlto = existenciaDe($alto, $almacenA, 3, 10);
        $exOtro = existenciaDe($otro, $almacenB, 2, 10);

        $this->artisan('alm:fusionar-articulos', ['--auto' => true, '--force' => true])
            ->expectsOutputToContain('se queda   ART-00020')
            ->assertSuccessful();

        // "CARETAS FACIALES" no normaliza igual que "careta facial": es otro
        // insumo para el maestro y no entra. Los dos "careta facial" sí, y se
        // queda el de código más bajo aunque no sea el más bajo de la tabla.
        expect($plural->fresh()->activo)->toBeTrue()
            ->and($alto->fresh()->activo)->toBeTrue()
            ->and($otro->fresh()->activo)->toBeFalse()
            ->and($otro->fresh()->fusionado_en_id)->toBe($alto->id)
            ->and($exOtro->fresh()->articulo_id)->toBe($alto->id)
            ->and($exAlto->fresh()->articulo_id)->toBe($alto->id)
            ->and(Item::query()->where('activo', true)->count())->toBe(2);
    });

    it('el artículo adopta el producto de Compras que se llama igual y no tiene artículo', function () {
        $articulo = articuloDuplicado('ART-00810', 'Ácido muriático 1 Lt');
        $producto = productoDuplicado('ART-00112', 'ACIDO MURIATICO 1 LT');
        $itemProducto = $producto->item_id;
        $precio = ProductoPrecio::factory()->create(['producto_id' => $producto->id]);

        $this->artisan('alm:fusionar-articulos', ['--auto' => true, '--force' => true])
            ->expectsOutputToContain('se adopta')
            ->assertSuccessful();

        expect($articulo->fresh()->producto_id)->toBe($producto->id)
            ->and($producto->fresh()->activo)->toBeTrue()
            ->and($producto->fresh()->item_id)->toBe($articulo->item_id)
            ->and(Item::findOrFail($itemProducto)->activo)->toBeFalse()
            ->and($precio->fresh()->producto_id)->toBe($producto->id)
            ->and(Item::query()->where('activo', true)->count())->toBe(1);
    });

    it('fusiona entre sí los productos de Compras que no tienen artículo', function () {
        $queda = productoDuplicado('ART-00405', 'ARGON INDUSTRIAL');
        $sobra1 = productoDuplicado('ART-00406', 'Argon industrial');
        $sobra2 = productoDuplicado('ART-00409', 'ARGON  INDUSTRIAL');
        $precio = ProductoPrecio::factory()->create(['producto_id' => $sobra2->id]);

        $this->artisan('alm:fusionar-articulos', ['--auto' => true, '--force' => true])
            ->expectsOutputToContain('sólo productos de Compras')
            ->expectsOutputToContain('2 productos desactivados')
            ->assertSuccessful();

        expect($queda->fresh()->activo)->toBeTrue()
            ->and($sobra1->fresh()->activo)->toBeFalse()
            ->and($sobra1->fresh()->fusionado_en_id)->toBe($queda->id)
            ->and($sobra2->fresh()->fusionado_en_id)->toBe($queda->id)
            ->and($precio->fresh()->producto_id)->toBe($queda->id)
            ->and(Item::findOrFail($sobra1->item_id)->activo)->toBeFalse()
            ->and(Item::findOrFail($queda->item_id)->activo)->toBeTrue()
            ->and(Item::query()->where('activo', true)->count())->toBe(1);
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
        $foto = fn (array $almacenes): array => ['almacenes' => $almacenes, 'asignaciones' => []];

        expect(fn () => $comprobar->invoke($fusionador, $planes[0], $foto([$almacen->id => ['cantidad' => 999.0, 'valor' => 100.0]])))
            ->toThrow(RuntimeException::class, 'no cuadra');

        $this->artisan('alm:fusionar-articulos', ['csv' => $csv, '--force' => true])->assertSuccessful();

        expect((float) DB::table('alm_existencias')->whereIn('articulo_id', [$s->id, $x->id])->sum('cantidad'))->toBe(5.0);
    });

    it('compara almacén por almacén: dos errores que se cancelan en el total no pasan', function () {
        $almacenA = Almacen::factory()->create();
        $almacenB = Almacen::factory()->create();
        $s = Articulo::factory()->create(['codigo' => 'ART-00001']);
        $x = Articulo::factory()->create(['codigo' => 'ART-00002']);
        existenciaDe($s, $almacenA, 10, 5);
        existenciaDe($x, $almacenB, 4, 5);

        $csv = csvFusion([['g', 'ART-00001', 'SI'], ['g', 'ART-00002', '']]);
        $fusionador = app(FusionadorArticulos::class);
        $planes = $fusionador->planear($fusionador->gruposDesdeCsv($csv));
        $comprobar = new ReflectionMethod($fusionador, 'comprobar');

        // Mismo total (14 piezas, $70) pero repartido al revés entre A y B: la
        // suma global lo daría por bueno y el almacén A tendría 6 de más.
        $alReves = ['almacenes' => [
            $almacenA->id => ['cantidad' => 4.0, 'valor' => 20.0],
            $almacenB->id => ['cantidad' => 10.0, 'valor' => 50.0],
        ], 'asignaciones' => []];

        expect(fn () => $comprobar->invoke($fusionador, $planes[0], $alReves))
            ->toThrow(RuntimeException::class, 'almacén');
    });

    it('compara las asignaciones por obra, no sólo el saldo', function () {
        $almacen = Almacen::factory()->create();
        $obra = Obra::factory()->create();
        $otraObra = Obra::factory()->create();
        $s = Articulo::factory()->create(['codigo' => 'ART-00001']);
        $x = Articulo::factory()->create(['codigo' => 'ART-00002']);
        $existenciaS = existenciaDe($s, $almacen, 10, 5);
        $existenciaX = existenciaDe($x, $almacen, 4, 5);
        Asignacion::factory()->de(3)->create(['existencia_id' => $existenciaS->id, 'obra_id' => $obra->id]);
        Asignacion::factory()->de(4)->create(['existencia_id' => $existenciaX->id, 'obra_id' => $otraObra->id]);

        $csv = csvFusion([['g', 'ART-00001', 'SI'], ['g', 'ART-00002', '']]);
        $fusionador = app(FusionadorArticulos::class);
        $planes = $fusionador->planear($fusionador->gruposDesdeCsv($csv));
        $comprobar = new ReflectionMethod($fusionador, 'comprobar');

        // Saldo del almacén intacto, pero las 7 piezas asignadas cargadas a la
        // obra equivocada: también revienta.
        $cambiadas = ['almacenes' => [$almacen->id => ['cantidad' => 14.0, 'valor' => 70.0]], 'asignaciones' => [
            "{$almacen->id}/{$obra->id}" => ['cantidad' => 7.0, 'valor' => 0.0],
        ]];

        expect(fn () => $comprobar->invoke($fusionador, $planes[0], $cambiadas))
            ->toThrow(RuntimeException::class, 'asignación');

        // Y la fusión real sí pasa, con cada obra en su lugar.
        $this->artisan('alm:fusionar-articulos', ['csv' => $csv, '--force' => true])->assertSuccessful();

        expect((float) Asignacion::query()->where('existencia_id', $existenciaS->id)->where('obra_id', $obra->id)->value('cantidad'))->toBe(3.0)
            ->and((float) Asignacion::query()->where('existencia_id', $existenciaS->id)->where('obra_id', $otraObra->id)->value('cantidad'))->toBe(4.0);
    });
});
