<?php

use App\Models\Cotiz\CategoriaTarjeta;
use App\Models\Cotiz\Factor;
use App\Models\Cotiz\Insumo;
use App\Models\Cotiz\KilosRealesCategoria;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraInsumoOverride;
use App\Models\Cotiz\Tarjeta;
use App\Models\Cotiz\TarjetaCategoriaKilos;
use App\Models\Cotiz\TarjetaEstructura;
use App\Models\Cotiz\TarjetaFactor;
use App\Models\Cotiz\TarjetaInsumoPrecio;
use App\Models\Cotiz\TarjetaKilosReal;
use App\Models\Cotiz\TarjetaRegistro;
use App\Models\Cotiz\Unidad;
use App\Services\Cotiz\TarjetaCalculator;

beforeEach(function () {
    $this->calc = app(TarjetaCalculator::class);
    $this->kg = Unidad::factory()->create(['descripcion' => 'kg']);
    $this->pza = Unidad::factory()->create(['descripcion' => 'pza']);
    $this->cat = CategoriaTarjeta::factory()->create(['descripcion' => 'ESTRUCTURA', 'orden' => 1]);

    // Insumo en kg con categoría ESTRUCTURA y el P.U. dado.
    $this->insumoKg = fn (int $precio): Insumo => Insumo::factory()->create([
        'unidad_id' => $this->kg->id,
        'categoria_tarjeta_id' => $this->cat->id,
        'precio_unitario' => $precio,
        'peso_lineal' => 10,
    ]);

    // Insumo "de factor" con unidad fija (evita colisión con la 'kg' del catálogo).
    $this->insumoFactor = fn (int $precio): Insumo => Insumo::factory()->create([
        'unidad_id' => $this->pza->id,
        'precio_unitario' => $precio,
    ]);
});

test('importe de un registro manual = cantidad × P.U. global', function () {
    $tarjeta = Tarjeta::factory()->create();
    $insumo = ($this->insumoKg)(100);
    TarjetaRegistro::factory()->create([
        'tarjeta_id' => $tarjeta->id,
        'generadora_registro_id' => null,
        'insumo_id' => $insumo->id,
        'cantidad' => 5,
    ]);

    $r = $this->calc->calcular($tarjeta);

    expect($r['total_registros'])->toBe(500.0);
    expect($r['kg_fab'])->toBe(5.0);
    expect($r['total_importe'])->toBe(500.0);
});

test('P.U. efectivo respeta prioridad tarjeta > obra > global', function () {
    $obra = Obra::factory()->create();
    $tarjeta = Tarjeta::factory()->create(['obra_id' => $obra->id]);
    $insumo = ($this->insumoKg)(100);

    // Override de obra: 200.
    ObraInsumoOverride::factory()->create([
        'obra_id' => $obra->id,
        'insumo_id' => $insumo->id,
        'precio_unitario' => 200,
    ]);
    TarjetaRegistro::factory()->create([
        'tarjeta_id' => $tarjeta->id,
        'generadora_registro_id' => null,
        'insumo_id' => $insumo->id,
        'cantidad' => 5,
    ]);

    expect($this->calc->calcular($tarjeta)['total_registros'])->toBe(1000.0);

    // Override de tarjeta: 300 (gana sobre el de obra).
    TarjetaInsumoPrecio::factory()->create([
        'tarjeta_id' => $tarjeta->id,
        'insumo_id' => $insumo->id,
        'precio_unitario' => 300,
    ]);

    expect($this->calc->calcular($tarjeta->fresh())['total_registros'])->toBe(1500.0);
});

test('un factor con fórmula sobre kg_fab calcula su importe', function () {
    $tarjeta = Tarjeta::factory()->create();
    $insumo = ($this->insumoKg)(100);
    TarjetaRegistro::factory()->create([
        'tarjeta_id' => $tarjeta->id,
        'generadora_registro_id' => null,
        'insumo_id' => $insumo->id,
        'cantidad' => 5, // kg_fab = 5
    ]);

    $factorInsumo = ($this->insumoFactor)(10);
    $factor = Factor::factory()->create([
        'codigo' => 'TEST_OXI',
        'formula' => 'kg_fab * 2',
        'insumo_id' => $factorInsumo->id,
    ]);
    TarjetaFactor::factory()->create([
        'tarjeta_id' => $tarjeta->id,
        'factor_id' => $factor->id,
    ]);

    $r = $this->calc->calcular($tarjeta);

    // cantidad = 5×2 = 10 ; importe = 10 × 10 = 100.
    $factorRow = collect($r['factores'])->firstWhere('codigo', 'TEST_OXI');
    expect($factorRow['cantidad'])->toBe(10.0);
    expect($factorRow['importe'])->toBe(100.0);
    expect($r['total_factores'])->toBe(100.0);
    expect($r['total_importe'])->toBe(600.0); // 500 registros + 100 factor
});

test('un factor manual (sin fórmula) usa su cantidad_manual', function () {
    $tarjeta = Tarjeta::factory()->create();
    $factorInsumo = ($this->insumoFactor)(4);
    $factor = Factor::factory()->create([
        'codigo' => 'TEST_MAN',
        'formula' => null,
        'insumo_id' => $factorInsumo->id,
    ]);
    TarjetaFactor::factory()->create([
        'tarjeta_id' => $tarjeta->id,
        'factor_id' => $factor->id,
        'cantidad_manual' => 7,
    ]);

    $r = $this->calc->calcular($tarjeta);

    expect($r['total_factores'])->toBe(28.0); // 7 × 4
});

test('un factor puede referenciar el código de otro factor (DAG)', function () {
    $tarjeta = Tarjeta::factory()->create();
    $insumo = ($this->insumoKg)(100);
    TarjetaRegistro::factory()->create([
        'tarjeta_id' => $tarjeta->id,
        'generadora_registro_id' => null,
        'insumo_id' => $insumo->id,
        'cantidad' => 10, // kg_fab = 10
    ]);

    $i1 = ($this->insumoFactor)(1);
    $i2 = ($this->insumoFactor)(1);
    $base = Factor::factory()->create(['codigo' => 'BASE', 'formula' => 'kg_fab * 0.5', 'insumo_id' => $i1->id]);
    $derivado = Factor::factory()->create(['codigo' => 'DERIV', 'formula' => 'BASE * 2', 'insumo_id' => $i2->id]);
    TarjetaFactor::factory()->create(['tarjeta_id' => $tarjeta->id, 'factor_id' => $base->id]);
    TarjetaFactor::factory()->create(['tarjeta_id' => $tarjeta->id, 'factor_id' => $derivado->id]);

    $r = $this->calc->calcular($tarjeta);

    $baseRow = collect($r['factores'])->firstWhere('codigo', 'BASE');
    $derivRow = collect($r['factores'])->firstWhere('codigo', 'DERIV');
    expect($baseRow['cantidad'])->toBe(5.0);   // 10 × 0.5
    expect($derivRow['cantidad'])->toBe(10.0); // BASE(5) × 2
});

test('importe persistido del factor gana sobre el calculado', function () {
    $tarjeta = Tarjeta::factory()->create();
    $factorInsumo = ($this->insumoFactor)(10);
    $factor = Factor::factory()->create(['codigo' => 'TEST_PER', 'formula' => 'kg_fab * 2', 'insumo_id' => $factorInsumo->id]);
    TarjetaFactor::factory()->create([
        'tarjeta_id' => $tarjeta->id,
        'factor_id' => $factor->id,
        'importe' => 999,
    ]);

    expect($this->calc->calcular($tarjeta)['total_factores'])->toBe(999.0);
});

test('kg reales por tipo de corte alimenta el total', function () {
    $tarjeta = Tarjeta::factory()->create();
    $estructura = TarjetaEstructura::factory()->create(['tarjeta_id' => $tarjeta->id]);
    $categoria = KilosRealesCategoria::factory()->create(['tipo_corte' => 'TIRAS']);
    TarjetaCategoriaKilos::factory()->create([
        'tarjeta_id' => $tarjeta->id,
        'categoria_id' => $categoria->id,
        'porcentual' => null,
    ]);
    TarjetaKilosReal::factory()->create([
        'tarjeta_id' => $tarjeta->id,
        'categoria_id' => $categoria->id,
        'estructura_id' => $estructura->id,
        'kilos' => 250,
    ]);

    expect($this->calc->calcular($tarjeta)['kg_reales_total'])->toBe(250.0);
});

test('refrescarCache escribe importe_materiales y kilos_reales', function () {
    $tarjeta = Tarjeta::factory()->create(['importe_materiales' => null, 'kilos_reales' => null]);
    $insumo = ($this->insumoKg)(100);
    TarjetaRegistro::factory()->create([
        'tarjeta_id' => $tarjeta->id,
        'generadora_registro_id' => null,
        'insumo_id' => $insumo->id,
        'cantidad' => 5,
    ]);

    $this->calc->refrescarCache($tarjeta);

    expect((float) $tarjeta->fresh()->importe_materiales)->toBe(500.0);
});

test('slugCategoria normaliza acentos y espacios', function () {
    expect(TarjetaCalculator::slugCategoria('TORNILLERÍA'))->toBe('tornilleria');
    expect(TarjetaCalculator::slugCategoria('CONSUMIBLES PLANTA'))->toBe('consumibles_planta');
    expect(TarjetaCalculator::slugCategoria(null))->toBe('sin_clasificar');
});
