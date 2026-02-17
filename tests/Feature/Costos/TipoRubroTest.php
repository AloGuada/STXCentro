<?php

use App\Models\Costos\Rubro;
use App\Models\Costos\TipoRubro;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin costos tipo rubros', function () {
    test('index page can be rendered', function () {
        TipoRubro::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.tipo-rubros.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/tipo-rubros/index')
            ->has('tipoRubros.data', 3)
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.tipo-rubros.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/tipo-rubros/create')
        );
    });

    test('tipo rubro can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.tipo-rubros.store'), [
                'descripcion' => 'Materiales Directos',
            ]);

        $response->assertRedirect(route('admin.costos.tipo-rubros.index'));
        $this->assertDatabaseHas('costos_tipo_rubros', [
            'descripcion' => 'Materiales Directos',
        ]);
    });

    test('edit page can be rendered', function () {
        $tipoRubro = TipoRubro::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.tipo-rubros.edit', $tipoRubro));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/tipo-rubros/edit')
            ->has('tipoRubro')
        );
    });

    test('tipo rubro can be updated', function () {
        $tipoRubro = TipoRubro::factory()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.costos.tipo-rubros.update', $tipoRubro), [
                'descripcion' => 'Updated',
            ]);

        $response->assertRedirect(route('admin.costos.tipo-rubros.index'));
        $this->assertDatabaseHas('costos_tipo_rubros', [
            'id' => $tipoRubro->id,
            'descripcion' => 'Updated',
        ]);
    });

    test('tipo rubro can be deleted when no rubros', function () {
        $tipoRubro = TipoRubro::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.costos.tipo-rubros.destroy', $tipoRubro));

        $response->assertRedirect(route('admin.costos.tipo-rubros.index'));
        $this->assertDatabaseMissing('costos_tipo_rubros', ['id' => $tipoRubro->id]);
    });

    test('tipo rubro cannot be deleted with rubros', function () {
        $tipoRubro = TipoRubro::factory()->create();
        Rubro::factory()->create(['tipo_rubro_id' => $tipoRubro->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.costos.tipo-rubros.destroy', $tipoRubro));

        $response->assertSessionHasErrors(['delete']);
        $this->assertDatabaseHas('costos_tipo_rubros', ['id' => $tipoRubro->id]);
    });

    test('validation requires descripcion', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.tipo-rubros.store'), []);

        $response->assertSessionHasErrors(['descripcion']);
    });
});
