<?php

use App\Models\Concepto;
use App\Models\Prod\Categoria;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin prod categorias', function () {
    test('index lists categorias with concepto count', function () {
        Categoria::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.categorias.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/categorias/index')
            ->has('categorias.data', 3)
        );
    });

    test('categoria can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.categorias.store'), [
                'nombre' => 'Columna',
            ]);

        $response->assertRedirect(route('admin.prod.categorias.index'));
        $this->assertDatabaseHas('prod_categorias', ['nombre' => 'Columna']);
    });

    test('categoria nombre must be unique', function () {
        Categoria::factory()->create(['nombre' => 'Trabe']);

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.categorias.store'), [
                'nombre' => 'Trabe',
            ]);

        $response->assertSessionHasErrors(['nombre']);
    });

    test('categoria can be updated', function () {
        $categoria = Categoria::factory()->create(['nombre' => 'Viga']);

        $response = $this->actingAs($this->user)
            ->put(route('admin.prod.categorias.update', $categoria), [
                'nombre' => 'Viga principal',
            ]);

        $response->assertRedirect(route('admin.prod.categorias.index'));
        $this->assertDatabaseHas('prod_categorias', ['id' => $categoria->id, 'nombre' => 'Viga principal']);
    });

    test('categoria keeps its own nombre on update', function () {
        $categoria = Categoria::factory()->create(['nombre' => 'Placa']);

        $response = $this->actingAs($this->user)
            ->put(route('admin.prod.categorias.update', $categoria), [
                'nombre' => 'Placa',
            ]);

        $response->assertRedirect(route('admin.prod.categorias.index'));
    });

    test('categoria can be deleted', function () {
        $categoria = Categoria::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.categorias.destroy', $categoria));

        $response->assertRedirect(route('admin.prod.categorias.index'));
        $this->assertDatabaseMissing('prod_categorias', ['id' => $categoria->id]);
    });

    test('categoria cannot be deleted with conceptos', function () {
        $categoria = Categoria::factory()->create();
        Concepto::factory()->create(['categoria_id' => $categoria->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.categorias.destroy', $categoria));

        $response->assertSessionHasErrors(['error']);
        $this->assertDatabaseHas('prod_categorias', ['id' => $categoria->id]);
    });
});
