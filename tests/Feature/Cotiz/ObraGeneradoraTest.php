<?php

use App\Models\Cotiz\Generadora;
use App\Models\Cotiz\GeneradoraRegistro;
use App\Models\Cotiz\Insumo;
use App\Models\Cotiz\Merma;
use App\Models\Cotiz\Obra;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-cotiz');
});

describe('cotiz obras', function () {
    test('index renderiza la página de obras', function () {
        Obra::factory()->count(3)->create();

        $this->actingAs($this->user)
            ->get(route('admin.cotiz.obras.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cotiz/obras/index')
                ->has('obras.data', 3)
            );
    });

    test('store crea una obra y redirige', function () {
        $this->actingAs($this->user)
            ->post(route('admin.cotiz.obras.store'), [
                'nombre' => 'Nave Industrial Norte',
                'op' => 'OP-2024',
                'factor_contratista' => 1.2,
                'num_grupos' => 3,
            ])
            ->assertRedirect(route('admin.cotiz.obras.index'));

        $this->assertDatabaseHas('cotiz_obras', [
            'nombre' => 'Nave Industrial Norte',
            'op' => 'OP-2024',
            'num_grupos' => 3,
        ]);
    });

    test('store valida campos requeridos', function () {
        $this->actingAs($this->user)
            ->post(route('admin.cotiz.obras.store'), [])
            ->assertSessionHasErrors(['nombre', 'factor_contratista', 'num_grupos']);
    });

    test('destroy elimina una obra', function () {
        $obra = Obra::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('admin.cotiz.obras.destroy', $obra))
            ->assertRedirect(route('admin.cotiz.obras.index'));

        $this->assertDatabaseMissing('cotiz_obras', ['id' => $obra->id]);
    });
});

describe('cotiz generadoras', function () {
    test('store crea una generadora en una obra', function () {
        $obra = Obra::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.cotiz.generadoras.store'), [
                'obra_id' => $obra->id,
                'titulo' => 'Columnas eje A',
                'orden' => 1,
            ])
            ->assertRedirect(route('admin.cotiz.generadoras.index', $obra->id));

        $this->assertDatabaseHas('cotiz_generadoras', [
            'obra_id' => $obra->id,
            'titulo' => 'Columnas eje A',
        ]);
    });

    test('index lista las generadoras de la obra con info de lock', function () {
        $obra = Obra::factory()->create();
        Generadora::factory()->count(2)->create(['obra_id' => $obra->id]);

        $this->actingAs($this->user)
            ->get(route('admin.cotiz.generadoras.index', $obra))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cotiz/generadoras/index')
                ->has('obra')
                ->has('generadoras', 2)
                ->has('generadoras.0.is_locked')
                ->has('generadoras.0.registros_count')
            );
    });

    test('edit devuelve props con registros (kilos_con_merma), lock, insumos y mermas', function () {
        $generadora = Generadora::factory()->create();
        GeneradoraRegistro::factory()->count(2)->create(['generadora_id' => $generadora->id]);

        $this->actingAs($this->user)
            ->get(route('admin.cotiz.generadoras.edit', $generadora))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cotiz/generadoras/edit')
                ->has('generadora')
                ->has('registros', 2)
                ->has('registros.0.kilos_con_merma')
                ->has('registros.0.t_ml_m2')
                ->has('registros.0.kilos_reales')
                ->has('insumos')
                ->has('mermas')
                ->has('lock', fn ($lock) => $lock
                    ->where('is_locked', false)
                    ->where('locked_by', null)
                    ->where('locked_at', null)
                )
            );
    });

    test('update modifica la cabecera de la generadora', function () {
        $generadora = Generadora::factory()->create();

        $this->actingAs($this->user)
            ->put(route('admin.cotiz.generadoras.update', $generadora), [
                'titulo' => 'Título actualizado',
                'orden' => 5,
            ])
            ->assertRedirect(route('admin.cotiz.generadoras.edit', $generadora));

        $this->assertDatabaseHas('cotiz_generadoras', [
            'id' => $generadora->id,
            'titulo' => 'Título actualizado',
            'orden' => 5,
        ]);
    });

    test('destroy elimina la generadora', function () {
        $generadora = Generadora::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('admin.cotiz.generadoras.destroy', $generadora))
            ->assertRedirect(route('admin.cotiz.generadoras.index', $generadora->obra_id));

        $this->assertDatabaseMissing('cotiz_generadoras', ['id' => $generadora->id]);
    });

    test('reorder actualiza el orden de varias generadoras', function () {
        $obra = Obra::factory()->create();
        $a = Generadora::factory()->create(['obra_id' => $obra->id, 'orden' => 0]);
        $b = Generadora::factory()->create(['obra_id' => $obra->id, 'orden' => 1]);

        $this->actingAs($this->user)
            ->post(route('admin.cotiz.generadoras.reorder'), [
                'orden' => [$b->id, $a->id],
            ])
            ->assertRedirect();

        expect($a->fresh()->orden)->toBe(1);
        expect($b->fresh()->orden)->toBe(0);
    });
});

describe('cotiz generadora registros', function () {
    test('store crea un registro en la generadora', function () {
        $generadora = Generadora::factory()->create();
        $insumo = Insumo::factory()->create();
        $merma = Merma::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.cotiz.generadoras.registros.store', $generadora), [
                'material_origen_id' => $insumo->id,
                'material' => 'Placa A36',
                'ancho' => 1.5,
                'largo' => 6,
                'cantidad' => 10,
                'cant_pzas' => 1,
                'merma_id' => $merma->id,
                'validado' => false,
            ])
            ->assertRedirect(route('admin.cotiz.generadoras.edit', $generadora));

        $this->assertDatabaseHas('cotiz_generadora_registros', [
            'generadora_id' => $generadora->id,
            'material' => 'Placa A36',
        ]);
    });

    test('store valida que la merma sea requerida', function () {
        $generadora = Generadora::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.cotiz.generadoras.registros.store', $generadora), [])
            ->assertSessionHasErrors(['merma_id']);
    });

    test('update modifica un registro', function () {
        $registro = GeneradoraRegistro::factory()->create();

        $this->actingAs($this->user)
            ->put(route('admin.cotiz.registros.update', $registro), [
                'material' => 'Material editado',
                'merma_id' => $registro->merma_id,
                'ancho' => 2,
                'largo' => 3,
                'cantidad' => 4,
                'cant_pzas' => 5,
            ])
            ->assertRedirect(route('admin.cotiz.generadoras.edit', $registro->generadora_id));

        $this->assertDatabaseHas('cotiz_generadora_registros', [
            'id' => $registro->id,
            'material' => 'Material editado',
        ]);
    });

    test('validar togglea el flag validado', function () {
        $registro = GeneradoraRegistro::factory()->create(['validado' => false]);

        $this->actingAs($this->user)
            ->patch(route('admin.cotiz.registros.validar', $registro))
            ->assertRedirect();

        expect($registro->fresh()->validado)->toBeTrue();

        $this->actingAs($this->user)
            ->patch(route('admin.cotiz.registros.validar', $registro))
            ->assertRedirect();

        expect($registro->fresh()->validado)->toBeFalse();
    });

    test('validar acepta un valor explícito en el body', function () {
        $registro = GeneradoraRegistro::factory()->create(['validado' => false]);

        $this->actingAs($this->user)
            ->patch(route('admin.cotiz.registros.validar', $registro), ['validado' => true])
            ->assertRedirect();

        expect($registro->fresh()->validado)->toBeTrue();
    });

    test('destroy elimina un registro', function () {
        $registro = GeneradoraRegistro::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('admin.cotiz.registros.destroy', $registro))
            ->assertRedirect(route('admin.cotiz.generadoras.edit', $registro->generadora_id));

        $this->assertDatabaseMissing('cotiz_generadora_registros', ['id' => $registro->id]);
    });
});

describe('cotiz generadora edit lock', function () {
    test('POST lock/generadora/{id} toma el lock y marca locked_by', function () {
        $generadora = Generadora::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.cotiz.lock', ['type' => 'generadora', 'id' => $generadora->id]))
            ->assertOk();

        expect($generadora->fresh()->locked_by)->toBe($this->user->id);
    });

    test('un segundo usuario recibe 423 si el lock está vigente', function () {
        $generadora = Generadora::factory()->create();
        $otro = User::factory()->create();
        $otro->assignRole('usuario-cotiz');

        $this->actingAs($otro)
            ->post(route('admin.cotiz.lock', ['type' => 'generadora', 'id' => $generadora->id]))
            ->assertOk();

        $this->actingAs($this->user)
            ->post(route('admin.cotiz.lock', ['type' => 'generadora', 'id' => $generadora->id]))
            ->assertStatus(423)
            ->assertJsonStructure(['message', 'locked_by' => ['id', 'name']]);
    });

    test('unlock libera el lock propio', function () {
        $generadora = Generadora::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.cotiz.lock', ['type' => 'generadora', 'id' => $generadora->id]))
            ->assertOk();

        $this->actingAs($this->user)
            ->post(route('admin.cotiz.unlock', ['type' => 'generadora', 'id' => $generadora->id]))
            ->assertOk();

        expect($generadora->fresh()->locked_by)->toBeNull();
    });

    test('un admin-cotiz puede forzar el unlock de un lock ajeno', function () {
        $generadora = Generadora::factory()->create();
        $otro = User::factory()->create();
        $otro->assignRole('usuario-cotiz');

        $this->actingAs($otro)
            ->post(route('admin.cotiz.lock', ['type' => 'generadora', 'id' => $generadora->id]))
            ->assertOk();

        expect($generadora->fresh()->locked_by)->toBe($otro->id);

        $this->actingAs($this->user)
            ->post(route('admin.cotiz.unlock', ['type' => 'generadora', 'id' => $generadora->id]))
            ->assertOk();

        expect($generadora->fresh()->locked_by)->toBeNull();
    });

    test('tipo de entidad inválido retorna 404', function () {
        $this->actingAs($this->user)
            ->post(route('admin.cotiz.lock', ['type' => 'monstruo', 'id' => 99]))
            ->assertNotFound();
    });
});

describe('cotiz generadora — override de peso por obra', function () {
    beforeEach(function () {
        $this->obra = Obra::factory()->create();
        $this->insumo = Insumo::factory()->create(['peso_lineal' => 10, 'peso_default' => 50]);
        $generadora = Generadora::factory()->create(['obra_id' => $this->obra->id]);
        $merma = Merma::factory()->create(['formula' => '']);
        $this->registro = GeneradoraRegistro::factory()->create([
            'generadora_id' => $generadora->id,
            'material_origen_id' => $this->insumo->id,
            'merma_id' => $merma->id,
        ]);
    });

    test('editar Peso ml/m² crea un override por obra (no toca el catálogo global)', function () {
        $this->actingAs($this->user)
            ->put(route('admin.cotiz.registros.insumo-override', $this->registro), ['field' => 'peso_lineal', 'value' => 12.5])
            ->assertRedirect();

        $this->assertDatabaseHas('cotiz_obra_insumo_override', [
            'obra_id' => $this->obra->id,
            'insumo_id' => $this->insumo->id,
            'peso_lineal' => 12.5,
        ]);
        // El catálogo global del insumo NO cambia.
        expect((float) $this->insumo->fresh()->peso_lineal)->toBe(10.0);
    });

    test('igualar al valor global limpia el override', function () {
        $this->actingAs($this->user)
            ->put(route('admin.cotiz.registros.insumo-override', $this->registro), ['field' => 'peso_default', 'value' => 99])
            ->assertRedirect();
        $this->assertDatabaseHas('cotiz_obra_insumo_override', ['obra_id' => $this->obra->id, 'peso_default' => 99]);

        // Volver a poner el valor global (50) borra la fila de override (queda vacía).
        $this->actingAs($this->user)
            ->put(route('admin.cotiz.registros.insumo-override', $this->registro), ['field' => 'peso_default', 'value' => 50])
            ->assertRedirect();

        $this->assertDatabaseMissing('cotiz_obra_insumo_override', ['obra_id' => $this->obra->id, 'insumo_id' => $this->insumo->id]);
    });

    test('el override de peso se ve en el catálogo de obra (Fase 2)', function () {
        $this->actingAs($this->user)
            ->put(route('admin.cotiz.registros.insumo-override', $this->registro), ['field' => 'peso_lineal', 'value' => 15])
            ->assertRedirect();

        $this->actingAs($this->user)
            ->get(route('admin.cotiz.obras.catalogo.index', $this->obra))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('insumos', fn ($insumos) => collect($insumos)->contains(
                    fn ($row) => data_get($row, 'override.peso_lineal') !== null
                ))
            );
    });
});

describe('cotiz generadora — peso % autocalculado', function () {
    test('peso % se calcula por agregados del mismo insumo: (Σ c/merma − Σ reales) / Σ reales', function () {
        $insumo = Insumo::factory()->create(['peso_lineal' => 10]);
        $merma = Merma::factory()->create(['formula' => 'kilos_reales * 1.1']); // +10% de merma
        $generadora = Generadora::factory()->create();

        // Dos registros del mismo insumo: reales 60 y 10; con merma 66 y 11.
        GeneradoraRegistro::factory()->create([
            'generadora_id' => $generadora->id, 'material_origen_id' => $insumo->id, 'merma_id' => $merma->id,
            'ancho' => 2, 'largo' => 3, 'cantidad' => 1, 'cant_pzas' => 1,
        ]);
        GeneradoraRegistro::factory()->create([
            'generadora_id' => $generadora->id, 'material_origen_id' => $insumo->id, 'merma_id' => $merma->id,
            'ancho' => 1, 'largo' => 1, 'cantidad' => 1, 'cant_pzas' => 1,
        ]);

        // peso % = (77 − 70) / 70 = 0.1; T. kilos = reales × 1.1 (66 y 11).
        $this->actingAs($this->user)
            ->get(route('admin.cotiz.generadoras.edit', $generadora))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('registros.0.peso_porcentual_ef', fn ($v) => abs((float) $v - 0.1) < 1e-6)
                ->where('registros.0.kilos_totales_ef', fn ($v) => abs((float) $v - 66.0) < 1e-6)
                ->where('registros.1.kilos_totales_ef', fn ($v) => abs((float) $v - 11.0) < 1e-6)
            );
    });

    test('un registro sin insumo deja peso % y T. kilos en null (sin ceros)', function () {
        $merma = Merma::factory()->create(['formula' => 'kilos_reales']);
        $generadora = Generadora::factory()->create();
        GeneradoraRegistro::factory()->create([
            'generadora_id' => $generadora->id, 'material_origen_id' => null, 'merma_id' => $merma->id,
            'ancho' => 2, 'largo' => 3, 'cantidad' => 1, 'cant_pzas' => 1,
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.cotiz.generadoras.edit', $generadora))
            ->assertInertia(fn ($page) => $page
                ->where('registros.0.peso_porcentual_ef', null)
                ->where('registros.0.kilos_totales_ef', null)
            );
    });

    test('un override de peso % gana sobre el calculado', function () {
        $insumo = Insumo::factory()->create(['peso_lineal' => 10]);
        $merma = Merma::factory()->create(['formula' => 'kilos_reales * 1.1']);
        $generadora = Generadora::factory()->create();
        GeneradoraRegistro::factory()->create([
            'generadora_id' => $generadora->id, 'material_origen_id' => $insumo->id, 'merma_id' => $merma->id,
            'ancho' => 2, 'largo' => 3, 'cantidad' => 1, 'cant_pzas' => 1,
            'peso_porcentual' => 0.25, // override
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.cotiz.generadoras.edit', $generadora))
            ->assertInertia(fn ($page) => $page
                ->where('registros.0.peso_porcentual_ef', fn ($v) => abs((float) $v - 0.25) < 1e-6)
                ->where('registros.0.kilos_totales_ef', fn ($v) => abs((float) $v - 75.0) < 1e-6) // 60 × 1.25
            );
    });
});
