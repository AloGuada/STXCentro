<?php

use App\Models\Cotiz\CategoriaTarjeta;
use App\Models\Cotiz\Factor;
use App\Models\Cotiz\Insumo;
use App\Models\Cotiz\KilosRealesCategoria;
use App\Models\Cotiz\PinturaFormula;
use App\Models\Cotiz\Tarjeta;
use App\Models\Cotiz\TarjetaCategoriaKilos;
use App\Models\Cotiz\TarjetaEstructura;
use App\Models\Cotiz\TarjetaFactor;
use App\Models\Cotiz\TarjetaKilosReal;
use App\Models\Cotiz\TarjetaRegistro;
use App\Models\Cotiz\Unidad;
use App\Services\Cotiz\TarjetaCalculator;

/**
 * Direccionamiento semántico (M046) end-to-end: un factor referencia medidas de SU propia
 * tarjeta vía total.tarjeta.* y tarjeta.factor[cod=X] (el camino que usan los seeds reales).
 */
beforeEach(function () {
    $this->calc = app(TarjetaCalculator::class);
    $kg = Unidad::factory()->create(['descripcion' => 'kg']);
    $pza = Unidad::factory()->create(['descripcion' => 'pza']);
    $cat = CategoriaTarjeta::factory()->create(['descripcion' => 'ESTRUCTURA', 'orden' => 1]);
    PinturaFormula::factory()->create(['clave' => 'placa', 'formula' => 'kg / peso_lineal']);

    $this->tarjeta = Tarjeta::factory()->create();
    $insumo = Insumo::factory()->create([
        'unidad_id' => $kg->id,
        'categoria_tarjeta_id' => $cat->id,
        'precio_unitario' => 100,
        'peso_lineal' => 10,
    ]);
    // Registro manual: 10 kg × $100 = $1000 ; categoría ESTRUCTURA ; pintura 'placa'.
    TarjetaRegistro::factory()->create([
        'tarjeta_id' => $this->tarjeta->id,
        'generadora_registro_id' => null,
        'insumo_id' => $insumo->id,
        'cantidad' => 10,
        'tipo_pintura' => 'placa',
    ]);

    // Análisis de kilos reales: 200 kg en TIRAS.
    $estructura = TarjetaEstructura::factory()->create(['tarjeta_id' => $this->tarjeta->id]);
    $catKr = KilosRealesCategoria::factory()->create(['tipo_corte' => 'TIRAS']);
    TarjetaCategoriaKilos::factory()->create([
        'tarjeta_id' => $this->tarjeta->id,
        'categoria_id' => $catKr->id,
        'porcentual' => null,
    ]);
    TarjetaKilosReal::factory()->create([
        'tarjeta_id' => $this->tarjeta->id,
        'categoria_id' => $catKr->id,
        'estructura_id' => $estructura->id,
        'kilos' => 200,
    ]);

    $this->pza = $pza;
    $this->factorInsumo = fn (): Insumo => Insumo::factory()->create(['unidad_id' => $pza->id, 'precio_unitario' => 1]);
});

function agregarFactor(int $tarjetaId, string $codigo, string $formula, $insumo): void
{
    $factor = Factor::factory()->create(['codigo' => $codigo, 'formula' => $formula, 'insumo_id' => $insumo->id]);
    TarjetaFactor::factory()->create(['tarjeta_id' => $tarjetaId, 'factor_id' => $factor->id]);
}

test('total.tarjeta.kg resuelve a kg_fab', function () {
    agregarFactor($this->tarjeta->id, 'F_KG', 'total.tarjeta.kg', ($this->factorInsumo)());

    $r = $this->calc->calcular($this->tarjeta);
    expect(collect($r['factores'])->firstWhere('codigo', 'F_KG')['cantidad'])->toBe(10.0);
});

test('total.tarjeta.kg_real[corte=tiras] resuelve a los kg de ese corte', function () {
    agregarFactor($this->tarjeta->id, 'F_TIRAS', 'total.tarjeta.kg_real[corte=tiras]', ($this->factorInsumo)());

    $r = $this->calc->calcular($this->tarjeta);
    expect(collect($r['factores'])->firstWhere('codigo', 'F_TIRAS')['cantidad'])->toBe(200.0);
});

test('total.tarjeta.importe[cc=ESTRUCTURA] resuelve al subtotal de la categoría', function () {
    agregarFactor($this->tarjeta->id, 'F_IMP', 'total.tarjeta.importe[cc=ESTRUCTURA]', ($this->factorInsumo)());

    $r = $this->calc->calcular($this->tarjeta);
    expect(collect($r['factores'])->firstWhere('codigo', 'F_IMP')['cantidad'])->toBe(1000.0);
});

test('total.tarjeta.area resuelve al área de pintura', function () {
    agregarFactor($this->tarjeta->id, 'F_AREA', 'total.tarjeta.area', ($this->factorInsumo)());

    $r = $this->calc->calcular($this->tarjeta);
    // area placa = kg / peso_lineal = 10 / 10 = 1.
    expect(collect($r['factores'])->firstWhere('codigo', 'F_AREA')['cantidad'])->toBe(1.0);
});

test('tarjeta.factor[cod=X] referencia otro factor de la misma tarjeta', function () {
    agregarFactor($this->tarjeta->id, 'F_KG', 'total.tarjeta.kg', ($this->factorInsumo)());
    agregarFactor($this->tarjeta->id, 'F_REF', 'tarjeta.factor[cod=F_KG] * 3', ($this->factorInsumo)());

    $r = $this->calc->calcular($this->tarjeta);
    expect(collect($r['factores'])->firstWhere('codigo', 'F_REF')['cantidad'])->toBe(30.0);
});

test('una fórmula con dirección a un factor inexistente cae a 0', function () {
    agregarFactor($this->tarjeta->id, 'F_BAD', 'tarjeta.factor[cod=NO_EXISTE] * 2', ($this->factorInsumo)());

    $r = $this->calc->calcular($this->tarjeta);
    expect(collect($r['factores'])->firstWhere('codigo', 'F_BAD')['cantidad'])->toBe(0.0);
});
