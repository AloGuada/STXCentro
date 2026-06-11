<?php

use App\Models\Cotiz\Factor;
use App\Models\Cotiz\Insumo;
use App\Models\Cotiz\KilosRealesCategoria;
use App\Models\Cotiz\Tarjeta;
use App\Models\Cotiz\TarjetaCategoriaKilos;
use App\Models\Cotiz\TarjetaEstructura;
use App\Models\Cotiz\TarjetaFactor;
use App\Models\Cotiz\TarjetaInsumoPrecio;
use App\Models\Cotiz\TarjetaRegistro;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin-cotiz');
    $this->actingAs($this->user);
});

describe('edit con grilla densa', function () {
    test('expone registros/factores resueltos, estructura kr y catálogos', function () {
        $tarjeta = Tarjeta::factory()->create();
        TarjetaRegistro::factory()->create(['tarjeta_id' => $tarjeta->id, 'generadora_registro_id' => null]);

        $this->get(route('admin.cotiz.tarjetas.edit', $tarjeta))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cotiz/tarjetas/edit')
                ->has('registros', 1)
                ->has('factores')
                ->has('estructuras')
                ->has('categoriasKilos')
                ->has('celdas')
                ->has('catalogos.insumos')
                ->has('catalogos.tiposPintura')
            );
    });
});

describe('registros manuales', function () {
    test('store crea un registro manual', function () {
        $tarjeta = Tarjeta::factory()->create();
        $insumo = Insumo::factory()->create();

        $this->post(route('admin.cotiz.tarjetas.registros.store', $tarjeta), [
            'insumo_id' => $insumo->id,
            'cantidad' => 12,
        ])->assertRedirect();

        $this->assertDatabaseHas('cotiz_tarjeta_registros', [
            'tarjeta_id' => $tarjeta->id,
            'insumo_id' => $insumo->id,
            'generadora_registro_id' => null,
        ]);
    });

    test('update cambia cantidad y tipo de pintura de un registro manual', function () {
        $registro = TarjetaRegistro::factory()->create(['generadora_registro_id' => null, 'cantidad' => 1]);

        $this->put(route('admin.cotiz.tarjetas.registros.update', $registro), [
            'cantidad' => 99,
            'tipo_pintura' => 'placa',
            'validado' => true,
        ])->assertRedirect();

        $registro->refresh();
        expect((float) $registro->cantidad)->toBe(99.0);
        expect($registro->tipo_pintura)->toBe('placa');
        expect($registro->validado)->toBeTrue();
    });

    test('destroy elimina el registro', function () {
        $registro = TarjetaRegistro::factory()->create(['generadora_registro_id' => null]);

        $this->delete(route('admin.cotiz.tarjetas.registros.destroy', $registro))
            ->assertRedirect();

        $this->assertDatabaseMissing('cotiz_tarjeta_registros', ['id' => $registro->id]);
    });
});

describe('P.U. por tarjeta', function () {
    test('update crea el override de precio', function () {
        $tarjeta = Tarjeta::factory()->create();
        $insumo = Insumo::factory()->create();

        $this->put(route('admin.cotiz.tarjetas.precios.update', [$tarjeta, $insumo]), [
            'precio_unitario' => 555,
        ])->assertRedirect();

        $this->assertDatabaseHas('cotiz_tarjeta_insumo_precio', [
            'tarjeta_id' => $tarjeta->id,
            'insumo_id' => $insumo->id,
            'precio_unitario' => 555,
        ]);
    });

    test('update con precio nulo limpia el override', function () {
        $tarjeta = Tarjeta::factory()->create();
        $insumo = Insumo::factory()->create();
        TarjetaInsumoPrecio::factory()->create([
            'tarjeta_id' => $tarjeta->id,
            'insumo_id' => $insumo->id,
        ]);

        $this->put(route('admin.cotiz.tarjetas.precios.update', [$tarjeta, $insumo]), [
            'precio_unitario' => null,
        ])->assertRedirect();

        $this->assertDatabaseMissing('cotiz_tarjeta_insumo_precio', [
            'tarjeta_id' => $tarjeta->id,
            'insumo_id' => $insumo->id,
        ]);
    });
});

describe('factores', function () {
    test('store vincula un factor sin duplicar', function () {
        $tarjeta = Tarjeta::factory()->create();
        $factor = Factor::factory()->create();

        $this->post(route('admin.cotiz.tarjetas.factores.store', $tarjeta), ['factor_id' => $factor->id])
            ->assertRedirect();
        $this->post(route('admin.cotiz.tarjetas.factores.store', $tarjeta), ['factor_id' => $factor->id])
            ->assertRedirect();

        expect(TarjetaFactor::where('tarjeta_id', $tarjeta->id)->where('factor_id', $factor->id)->count())
            ->toBe(1);
    });

    test('update guarda formula_override', function () {
        $tf = TarjetaFactor::factory()->create();

        $this->put(route('admin.cotiz.tarjetas.factores.update', $tf), [
            'formula_override' => 'kg_fab * 3',
        ])->assertRedirect();

        expect($tf->fresh()->formula_override)->toBe('kg_fab * 3');
    });

    test('destroy quita el factor', function () {
        $tf = TarjetaFactor::factory()->create();

        $this->delete(route('admin.cotiz.tarjetas.factores.destroy', $tf))->assertRedirect();

        $this->assertDatabaseMissing('cotiz_tarjeta_factores', ['id' => $tf->id]);
    });
});

describe('estructuras y kilos reales', function () {
    test('agregar estructura y categoría y capturar una celda', function () {
        $tarjeta = Tarjeta::factory()->create();
        $categoria = KilosRealesCategoria::factory()->create(['tipo_corte' => 'TIRAS']);

        $this->post(route('admin.cotiz.tarjetas.estructuras.store', $tarjeta), ['nombre' => 'NAVE'])
            ->assertRedirect();
        $estructura = TarjetaEstructura::where('tarjeta_id', $tarjeta->id)->firstOrFail();

        $this->post(route('admin.cotiz.tarjetas.kr-categorias.store', $tarjeta), ['categoria_id' => $categoria->id])
            ->assertRedirect();

        $this->put(route('admin.cotiz.tarjetas.kr-celdas.upsert', $tarjeta), [
            'categoria_id' => $categoria->id,
            'estructura_id' => $estructura->id,
            'kilos' => 300,
        ])->assertRedirect();

        $this->assertDatabaseHas('cotiz_tarjeta_kilos_reales', [
            'tarjeta_id' => $tarjeta->id,
            'categoria_id' => $categoria->id,
            'estructura_id' => $estructura->id,
            'kilos' => 300,
        ]);
    });

    test('upsert de celda no duplica', function () {
        $tarjeta = Tarjeta::factory()->create();
        $estructura = TarjetaEstructura::factory()->create(['tarjeta_id' => $tarjeta->id]);
        $categoria = KilosRealesCategoria::factory()->create();
        TarjetaCategoriaKilos::factory()->create(['tarjeta_id' => $tarjeta->id, 'categoria_id' => $categoria->id]);

        foreach ([100, 200] as $kilos) {
            $this->put(route('admin.cotiz.tarjetas.kr-celdas.upsert', $tarjeta), [
                'categoria_id' => $categoria->id,
                'estructura_id' => $estructura->id,
                'kilos' => $kilos,
            ])->assertRedirect();
        }

        expect($tarjeta->kilosReales()->count())->toBe(1);
        expect((float) $tarjeta->kilosReales()->first()->kilos)->toBe(200.0);
    });

    test('destroy de categoría kr', function () {
        $catKilos = TarjetaCategoriaKilos::factory()->create();

        $this->delete(route('admin.cotiz.tarjetas.kr-categorias.destroy', $catKilos))->assertRedirect();

        $this->assertDatabaseMissing('cotiz_tarjeta_categorias_kilos', ['id' => $catKilos->id]);
    });
});

describe('validación de fórmula', function () {
    test('una fórmula válida devuelve error null', function () {
        $this->postJson(route('admin.cotiz.tarjetas.validar-formula'), ['formula' => 'total.tarjeta.kg * 0.5'])
            ->assertOk()
            ->assertJson(['error' => null]);
    });

    test('una fórmula inválida devuelve el mensaje de error', function () {
        $this->postJson(route('admin.cotiz.tarjetas.validar-formula'), ['formula' => 'total.tarjeta.foo * 2'])
            ->assertOk()
            ->assertJsonPath('error', fn ($e) => str_contains((string) $e, 'columna desconocida'));
    });
});
