<?php

use App\Models\Cotiz\Factor;
use App\Models\Cotiz\Insumo;
use App\Models\Cotiz\ObraFactorOverride;
use App\Models\Cotiz\ObraInsumoOverride;
use App\Services\Cotiz\OverrideResolver;

beforeEach(function () {
    $this->resolver = new OverrideResolver;
});

describe('resolverInsumo', function () {
    test('sin override devuelve los valores globales', function () {
        $insumo = new Insumo([
            'descripcion' => 'Placa A36',
            'codigo_stumis' => 'STU-1',
            'precio_unitario' => 100.0,
            'peso_lineal' => 7.85,
            'peso_default' => 50.0,
        ]);

        $efectivo = $this->resolver->resolverInsumo($insumo, null);

        expect($efectivo['descripcion'])->toBe('Placa A36');
        expect($efectivo['precio_unitario'])->toBe(100.0);
        expect($efectivo['peso_lineal'])->toBe(7.85);
    });

    test('un campo override gana sobre el global, los demás caen al global', function () {
        $insumo = new Insumo([
            'descripcion' => 'Placa A36',
            'precio_unitario' => 100.0,
            'peso_lineal' => 7.85,
        ]);
        $override = new ObraInsumoOverride([
            'precio_unitario' => 250.0,
            // descripcion y peso_lineal NULL → caen al global
        ]);

        $efectivo = $this->resolver->resolverInsumo($insumo, $override);

        expect($efectivo['precio_unitario'])->toBe(250.0);
        expect($efectivo['descripcion'])->toBe('Placa A36');
        expect($efectivo['peso_lineal'])->toBe(7.85);
    });
});

describe('precioInsumo (prioridad tarjeta > obra > global)', function () {
    test('global cuando no hay override ni tarjeta', function () {
        $insumo = new Insumo(['precio_unitario' => 100.0]);

        expect($this->resolver->precioInsumo($insumo, null))->toBe(100.0);
    });

    test('obra gana sobre global', function () {
        $insumo = new Insumo(['precio_unitario' => 100.0]);
        $override = new ObraInsumoOverride(['precio_unitario' => 175.0]);

        expect($this->resolver->precioInsumo($insumo, $override))->toBe(175.0);
    });

    test('tarjeta gana sobre obra y global', function () {
        $insumo = new Insumo(['precio_unitario' => 100.0]);
        $override = new ObraInsumoOverride(['precio_unitario' => 175.0]);

        expect($this->resolver->precioInsumo($insumo, $override, 999.0))->toBe(999.0);
    });
});

describe('resolverFactor', function () {
    test('sin override devuelve los valores globales', function () {
        $factor = new Factor([
            'nombre' => 'Oxígeno',
            'insumo_id' => 5,
            'formula' => 'kg_fab * 0.001',
            'descripcion' => 'gas',
        ]);

        $efectivo = $this->resolver->resolverFactor($factor, null);

        expect($efectivo['nombre'])->toBe('Oxígeno');
        expect($efectivo['formula'])->toBe('kg_fab * 0.001');
        expect($efectivo['insumo_id'])->toBe(5);
    });

    test('override de fórmula gana; tarjeta gana sobre obra', function () {
        $factor = new Factor(['nombre' => 'Oxígeno', 'formula' => 'kg_fab * 0.001']);
        $override = new ObraFactorOverride(['formula' => 'kg_fab * 0.002']);

        expect($this->resolver->resolverFactor($factor, $override)['formula'])
            ->toBe('kg_fab * 0.002');

        expect($this->resolver->resolverFactor($factor, $override, 'kg_fab * 0.005')['formula'])
            ->toBe('kg_fab * 0.005');
    });
});

describe('estaVacio (limpieza de overrides nulos)', function () {
    test('un override con todos los campos NULL está vacío', function () {
        expect((new ObraInsumoOverride)->estaVacio())->toBeTrue();
        expect((new ObraFactorOverride)->estaVacio())->toBeTrue();
    });

    test('un override con cualquier campo poblado no está vacío', function () {
        expect((new ObraInsumoOverride(['precio_unitario' => 1]))->estaVacio())->toBeFalse();
        expect((new ObraInsumoOverride(['comentario' => 'nota']))->estaVacio())->toBeFalse();
        expect((new ObraFactorOverride(['formula' => 'x']))->estaVacio())->toBeFalse();
    });

    test('cadenas vacías cuentan como sin override', function () {
        expect((new ObraInsumoOverride(['descripcion' => '', 'comentario' => '']))->estaVacio())->toBeTrue();
    });
});
