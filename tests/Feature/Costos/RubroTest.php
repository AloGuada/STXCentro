<?php

use App\Models\Costos\Rubro;
use App\Models\Costos\TipoRubro;
use App\Models\Departamento;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin costos rubros', function () {
    test('index page can be rendered', function () {
        Rubro::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.rubros.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/rubros/index')
            ->has('rubros.data', 3)
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.rubros.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/rubros/create')
            ->has('tipoRubros')
            ->has('departamentos')
        );
    });

    test('rubro can be stored', function () {
        $tipoRubro = TipoRubro::factory()->create();
        $departamento = Departamento::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.rubros.store'), [
                'codigo' => 'RB001',
                'descripcion' => 'Materiales',
                'tipo_rubro_id' => $tipoRubro->id,
                'departamento_id' => $departamento->id,
            ]);

        $response->assertRedirect(route('admin.costos.rubros.index'));
        $this->assertDatabaseHas('costos_rubros', [
            'codigo' => 'RB001',
            'descripcion' => 'Materiales',
            'tipo_rubro_id' => $tipoRubro->id,
        ]);
    });

    test('edit page can be rendered', function () {
        $rubro = Rubro::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.rubros.edit', $rubro));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/rubros/edit')
            ->has('rubro')
            ->has('tipoRubros')
            ->has('departamentos')
        );
    });

    test('rubro can be updated', function () {
        $rubro = Rubro::factory()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.costos.rubros.update', $rubro), [
                'codigo' => $rubro->codigo,
                'descripcion' => 'Updated',
                'tipo_rubro_id' => $rubro->tipo_rubro_id,
            ]);

        $response->assertRedirect(route('admin.costos.rubros.index'));
        $this->assertDatabaseHas('costos_rubros', [
            'id' => $rubro->id,
            'descripcion' => 'Updated',
        ]);
    });

    test('rubro can be deleted', function () {
        $rubro = Rubro::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.costos.rubros.destroy', $rubro));

        $response->assertRedirect(route('admin.costos.rubros.index'));
        $this->assertDatabaseMissing('costos_rubros', ['id' => $rubro->id]);
    });

    test('validation requires codigo, descripcion and tipo_rubro_id', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.rubros.store'), []);

        $response->assertSessionHasErrors(['codigo', 'descripcion', 'tipo_rubro_id']);
    });
});
