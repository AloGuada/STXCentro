<?php

use App\Models\Cotiz\Factor;
use App\Models\Cotiz\Insumo;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraFactorOverride;
use App\Models\Cotiz\ObraInsumoOverride;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-cotiz');
});

describe('catalogo de obra — index', function () {
    test('renderiza la página con insumos y factores (global + override)', function () {
        $obra = Obra::factory()->create();
        $insumo = Insumo::factory()->create();
        $factor = Factor::factory()->create();
        ObraInsumoOverride::factory()->create([
            'obra_id' => $obra->id,
            'insumo_id' => $insumo->id,
            'precio_unitario' => 500,
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.cotiz.obras.catalogo.index', $obra))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cotiz/catalogo-obra/index')
                ->has('obra')
                ->has('insumos')
                ->has('factores')
                ->has('unidades')
                ->has('centrosCosto')
            );
    });
});

describe('catalogo de obra — insumos', function () {
    test('crea un override de precio para la obra', function () {
        $obra = Obra::factory()->create();
        $insumo = Insumo::factory()->create();

        $this->actingAs($this->user)
            ->put(route('admin.cotiz.obras.catalogo.insumos.update', [$obra, $insumo]), [
                'precio_unitario' => 333.5,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cotiz_obra_insumo_override', [
            'obra_id' => $obra->id,
            'insumo_id' => $insumo->id,
            'precio_unitario' => 333.5,
        ]);
    });

    test('limpiar todos los campos elimina la fila de override', function () {
        $obra = Obra::factory()->create();
        $insumo = Insumo::factory()->create();
        ObraInsumoOverride::factory()->create([
            'obra_id' => $obra->id,
            'insumo_id' => $insumo->id,
            'precio_unitario' => 500,
        ]);

        $this->actingAs($this->user)
            ->put(route('admin.cotiz.obras.catalogo.insumos.update', [$obra, $insumo]), [
                'precio_unitario' => null,
                'descripcion' => null,
                'comentario' => '',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('cotiz_obra_insumo_override', [
            'obra_id' => $obra->id,
            'insumo_id' => $insumo->id,
        ]);
    });

    test('no crea fila cuando todos los campos llegan vacíos', function () {
        $obra = Obra::factory()->create();
        $insumo = Insumo::factory()->create();

        $this->actingAs($this->user)
            ->put(route('admin.cotiz.obras.catalogo.insumos.update', [$obra, $insumo]), [
                'precio_unitario' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('cotiz_obra_insumo_override', [
            'obra_id' => $obra->id,
            'insumo_id' => $insumo->id,
        ]);
    });

    test('actualiza un override existente sin duplicar la fila', function () {
        $obra = Obra::factory()->create();
        $insumo = Insumo::factory()->create();
        ObraInsumoOverride::factory()->create([
            'obra_id' => $obra->id,
            'insumo_id' => $insumo->id,
            'precio_unitario' => 100,
        ]);

        $this->actingAs($this->user)
            ->put(route('admin.cotiz.obras.catalogo.insumos.update', [$obra, $insumo]), [
                'precio_unitario' => 200,
            ])
            ->assertRedirect();

        expect(ObraInsumoOverride::where('obra_id', $obra->id)->where('insumo_id', $insumo->id)->count())
            ->toBe(1);
        $this->assertDatabaseHas('cotiz_obra_insumo_override', [
            'obra_id' => $obra->id,
            'insumo_id' => $insumo->id,
            'precio_unitario' => 200,
        ]);
    });

    test('destroy elimina el override del insumo', function () {
        $obra = Obra::factory()->create();
        $insumo = Insumo::factory()->create();
        ObraInsumoOverride::factory()->create([
            'obra_id' => $obra->id,
            'insumo_id' => $insumo->id,
        ]);

        $this->actingAs($this->user)
            ->delete(route('admin.cotiz.obras.catalogo.insumos.destroy', [$obra, $insumo]))
            ->assertRedirect();

        $this->assertDatabaseMissing('cotiz_obra_insumo_override', [
            'obra_id' => $obra->id,
            'insumo_id' => $insumo->id,
        ]);
    });
});

describe('catalogo de obra — factores', function () {
    test('crea un override de fórmula para la obra', function () {
        $obra = Obra::factory()->create();
        $factor = Factor::factory()->create();

        $this->actingAs($this->user)
            ->put(route('admin.cotiz.obras.catalogo.factores.update', [$obra, $factor]), [
                'formula' => 'kg_fab * 0.5',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cotiz_obra_factor_override', [
            'obra_id' => $obra->id,
            'factor_id' => $factor->id,
            'formula' => 'kg_fab * 0.5',
        ]);
    });

    test('limpiar todos los campos elimina la fila de override', function () {
        $obra = Obra::factory()->create();
        $factor = Factor::factory()->create();
        ObraFactorOverride::factory()->create([
            'obra_id' => $obra->id,
            'factor_id' => $factor->id,
            'formula' => 'kg_fab * 0.5',
        ]);

        $this->actingAs($this->user)
            ->put(route('admin.cotiz.obras.catalogo.factores.update', [$obra, $factor]), [
                'formula' => null,
                'nombre' => '',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('cotiz_obra_factor_override', [
            'obra_id' => $obra->id,
            'factor_id' => $factor->id,
        ]);
    });
});
