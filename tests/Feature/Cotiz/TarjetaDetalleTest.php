<?php

use App\Models\Cotiz\Factor;
use App\Models\Cotiz\Insumo;
use App\Models\Cotiz\KilosRealesCategoria;
use App\Models\Cotiz\Obra;
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

    test('crear categoría inline por descripción (crea global + la vincula)', function () {
        $tarjeta = Tarjeta::factory()->create();

        $this->post(route('admin.cotiz.tarjetas.kr-categorias.store', $tarjeta), [
            'descripcion' => 'COLUMNAS HSS', 'tipo_corte' => 'RAZ',
        ])->assertRedirect();

        $this->assertDatabaseHas('cotiz_kilos_reales_categorias', ['descripcion' => 'COLUMNAS HSS', 'tipo_corte' => 'RAZ']);
        $categoria = KilosRealesCategoria::where('descripcion', 'COLUMNAS HSS')->firstOrFail();
        $this->assertDatabaseHas('cotiz_tarjeta_categorias_kilos', ['tarjeta_id' => $tarjeta->id, 'categoria_id' => $categoria->id]);
    });

    test('cambiar tipo de corte de la categoría (catálogo global)', function () {
        $categoria = KilosRealesCategoria::factory()->create(['tipo_corte' => 'KG']);

        $this->put(route('admin.cotiz.tarjetas.kr-categorias.tipo', $categoria), ['tipo_corte' => 'CNX'])
            ->assertRedirect();

        $this->assertDatabaseHas('cotiz_kilos_reales_categorias', ['id' => $categoria->id, 'tipo_corte' => 'CNX']);
    });

    test('pasar fila a porcentual limpia sus celdas fijas', function () {
        $tarjeta = Tarjeta::factory()->create();
        $estructura = TarjetaEstructura::factory()->create(['tarjeta_id' => $tarjeta->id]);
        $categoria = KilosRealesCategoria::factory()->create();
        $catKilos = TarjetaCategoriaKilos::factory()->create(['tarjeta_id' => $tarjeta->id, 'categoria_id' => $categoria->id, 'porcentual' => null]);
        $tarjeta->kilosReales()->create(['categoria_id' => $categoria->id, 'estructura_id' => $estructura->id, 'kilos' => 500]);

        $this->put(route('admin.cotiz.tarjetas.kr-categorias.update', $catKilos), ['porcentual' => 0.22])
            ->assertRedirect();

        $this->assertDatabaseHas('cotiz_tarjeta_categorias_kilos', ['id' => $catKilos->id, 'porcentual' => 0.22]);
        expect($tarjeta->kilosReales()->where('categoria_id', $categoria->id)->count())->toBe(0);
    });
});

describe('acciones masivas y grupos', function () {
    test('validar-todas marca registros (manual + generadora) y factores', function () {
        $obra = Obra::factory()->create();
        $tarjeta = Tarjeta::factory()->create(['obra_id' => $obra->id]);
        $manual = TarjetaRegistro::factory()->create(['tarjeta_id' => $tarjeta->id, 'generadora_registro_id' => null, 'validado' => false]);
        $gen = \App\Models\Cotiz\Generadora::factory()->create(['obra_id' => $obra->id]);
        $genReg = \App\Models\Cotiz\GeneradoraRegistro::factory()->create(['generadora_id' => $gen->id, 'validado' => false]);
        TarjetaRegistro::factory()->create(['tarjeta_id' => $tarjeta->id, 'generadora_registro_id' => $genReg->id, 'insumo_id' => null]);
        $tf = TarjetaFactor::factory()->create(['tarjeta_id' => $tarjeta->id, 'validado' => false]);

        $this->post(route('admin.cotiz.tarjetas.validar-todas', $tarjeta))->assertRedirect();

        expect($manual->fresh()->validado)->toBeTrue();
        expect($genReg->fresh()->validado)->toBeTrue();
        expect($tf->fresh()->validado)->toBeTrue();
    });

    test('aplicar-sugerido limpia los importes override de factores', function () {
        $tarjeta = Tarjeta::factory()->create();
        $tf = TarjetaFactor::factory()->create(['tarjeta_id' => $tarjeta->id, 'importe' => 999]);

        $this->post(route('admin.cotiz.tarjetas.aplicar-sugerido', $tarjeta))->assertRedirect();

        expect($tf->fresh()->importe)->toBeNull();
    });

    test('registros-grupo distribuye cantidad entre manuales', function () {
        $tarjeta = Tarjeta::factory()->create();
        $insumo = Insumo::factory()->create();
        $a = TarjetaRegistro::factory()->create(['tarjeta_id' => $tarjeta->id, 'generadora_registro_id' => null, 'insumo_id' => $insumo->id, 'cantidad' => 10]);
        $b = TarjetaRegistro::factory()->create(['tarjeta_id' => $tarjeta->id, 'generadora_registro_id' => null, 'insumo_id' => $insumo->id, 'cantidad' => 30]);

        $this->put(route('admin.cotiz.tarjetas.registros.grupo', $tarjeta), [
            'ids' => [$a->id, $b->id],
            'cantidad' => 80,
        ])->assertRedirect();

        // 80 distribuido proporcional a 10:30 → 20 y 60.
        expect((float) $a->fresh()->cantidad)->toBe(20.0);
        expect((float) $b->fresh()->cantidad)->toBe(60.0);
    });

    test('registros-grupo destroy borra todos los ids', function () {
        $tarjeta = Tarjeta::factory()->create();
        $insumo = Insumo::factory()->create();
        $a = TarjetaRegistro::factory()->create(['tarjeta_id' => $tarjeta->id, 'generadora_registro_id' => null, 'insumo_id' => $insumo->id]);
        $b = TarjetaRegistro::factory()->create(['tarjeta_id' => $tarjeta->id, 'generadora_registro_id' => null, 'insumo_id' => $insumo->id]);

        $this->delete(route('admin.cotiz.tarjetas.registros.grupo-destroy', $tarjeta), ['ids' => [$a->id, $b->id]])
            ->assertRedirect();

        $this->assertDatabaseMissing('cotiz_tarjeta_registros', ['id' => $a->id]);
        $this->assertDatabaseMissing('cotiz_tarjeta_registros', ['id' => $b->id]);
    });

    test('resincronizar importa nuevos y quita los sin material', function () {
        $obra = Obra::factory()->create();
        $tarjeta = Tarjeta::factory()->create(['obra_id' => $obra->id]);
        $gen = \App\Models\Cotiz\Generadora::factory()->create(['obra_id' => $obra->id]);
        // Dos con material (se importan), uno sin material (no).
        \App\Models\Cotiz\GeneradoraRegistro::factory()->count(2)->create(['generadora_id' => $gen->id]);
        \App\Models\Cotiz\GeneradoraRegistro::factory()->create(['generadora_id' => $gen->id, 'material_origen_id' => null]);
        $tarjeta->generadoras()->attach($gen->id);

        $this->post(route('admin.cotiz.tarjetas.generadoras.resincronizar', [$tarjeta, $gen]))->assertRedirect();

        expect($tarjeta->registros()->count())->toBe(2);
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
