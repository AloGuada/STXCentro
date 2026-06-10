<?php

use App\Models\Cotiz\CategoriaTarjeta;
use App\Models\Cotiz\CentroCosto;
use App\Models\Cotiz\Factor;
use App\Models\Cotiz\Insumo;
use App\Models\Cotiz\Unidad;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->withoutVite();
    // Las páginas frontend (.tsx) aún no existen; sólo validamos el contrato Inertia del backend.
    config(['inertia.testing.ensure_pages_exist' => false]);
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-cotiz');
});

describe('cotiz catálogos - insumos', function () {
    test('index renderiza la página de insumos', function () {
        Insumo::factory()->count(3)->create();

        $this->actingAs($this->user)
            ->get(route('admin.cotiz.insumos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cotiz/insumos/index')
                ->has('insumos', 3)
                ->has('unidades')
                ->has('centrosCosto')
                ->has('categoriasTarjeta')
            );
    });

    test('store crea un insumo y redirige', function () {
        $unidad = Unidad::factory()->create();
        $centroCosto = CentroCosto::factory()->create();
        $categoria = CategoriaTarjeta::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.cotiz.insumos.store'), [
                'descripcion' => 'Placa de acero A36',
                'codigo_stumis' => 'STU-1001',
                'unidad_id' => $unidad->id,
                'precio_unitario' => 125.50,
                'peso_lineal' => 7.85,
                'peso_default' => 100,
                'centro_costo_id' => $centroCosto->id,
                'categoria_tarjeta_id' => $categoria->id,
            ])
            ->assertRedirect(route('admin.cotiz.insumos.index'));

        $this->assertDatabaseHas('cotiz_insumos', [
            'descripcion' => 'Placa de acero A36',
            'codigo_stumis' => 'STU-1001',
            'unidad_id' => $unidad->id,
            'centro_costo_id' => $centroCosto->id,
        ]);
    });

    test('store valida campos requeridos', function () {
        $this->actingAs($this->user)
            ->post(route('admin.cotiz.insumos.store'), [])
            ->assertSessionHasErrors(['descripcion', 'unidad_id', 'precio_unitario', 'centro_costo_id']);
    });

    test('update modifica un insumo conservando su descripción única', function () {
        $insumo = Insumo::factory()->create();

        $this->actingAs($this->user)
            ->put(route('admin.cotiz.insumos.update', $insumo), [
                'descripcion' => $insumo->descripcion,
                'unidad_id' => $insumo->unidad_id,
                'precio_unitario' => 999.99,
                'centro_costo_id' => $insumo->centro_costo_id,
            ])
            ->assertRedirect(route('admin.cotiz.insumos.index'));

        $this->assertDatabaseHas('cotiz_insumos', [
            'id' => $insumo->id,
            'precio_unitario' => 999.99,
        ]);
    });

    test('destroy elimina un insumo', function () {
        $insumo = Insumo::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('admin.cotiz.insumos.destroy', $insumo))
            ->assertRedirect(route('admin.cotiz.insumos.index'));

        $this->assertDatabaseMissing('cotiz_insumos', ['id' => $insumo->id]);
    });
});

describe('cotiz catálogos - factores', function () {
    test('index renderiza la página de factores', function () {
        Factor::factory()->count(2)->create();

        $this->actingAs($this->user)
            ->get(route('admin.cotiz.factores.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cotiz/factores/index')
                ->has('factores', 2)
                ->has('insumos')
                ->has('categoriasTarjeta')
            );
    });

    test('store crea un factor y redirige', function () {
        $insumo = Insumo::factory()->create();
        $categoria = CategoriaTarjeta::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.cotiz.factores.store'), [
                'codigo' => 'FAC_SOLDADURA',
                'nombre' => 'Soldadura por kg',
                'insumo_id' => $insumo->id,
                'formula' => 'kg_fab * 0.002',
                'descripcion' => 'Consumo de electrodo',
                'categoria_tarjeta_id' => $categoria->id,
            ])
            ->assertRedirect(route('admin.cotiz.factores.index'));

        $this->assertDatabaseHas('cotiz_factores', [
            'codigo' => 'FAC_SOLDADURA',
            'insumo_id' => $insumo->id,
        ]);
    });

    test('store valida campos requeridos', function () {
        $this->actingAs($this->user)
            ->post(route('admin.cotiz.factores.store'), [])
            ->assertSessionHasErrors(['codigo', 'nombre', 'insumo_id']);
    });

    test('update modifica un factor', function () {
        $factor = Factor::factory()->create();

        $this->actingAs($this->user)
            ->put(route('admin.cotiz.factores.update', $factor), [
                'codigo' => $factor->codigo,
                'nombre' => 'Nombre actualizado',
                'insumo_id' => $factor->insumo_id,
                'formula' => $factor->formula,
            ])
            ->assertRedirect(route('admin.cotiz.factores.index'));

        $this->assertDatabaseHas('cotiz_factores', [
            'id' => $factor->id,
            'nombre' => 'Nombre actualizado',
        ]);
    });

    test('destroy elimina un factor', function () {
        $factor = Factor::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('admin.cotiz.factores.destroy', $factor))
            ->assertRedirect(route('admin.cotiz.factores.index'));

        $this->assertDatabaseMissing('cotiz_factores', ['id' => $factor->id]);
    });
});
