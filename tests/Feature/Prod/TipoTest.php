<?php

use App\Models\Prod\PagoExtra;
use App\Models\Prod\Tipo;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin tipos pago', function () {
    test('index page can be rendered', function () {
        Tipo::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.tipos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/tipos/index')
            ->has('tipos.data', 3)
        );
    });

    test('tipo can be stored with orden and desgloce', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.tipos.store'), [
                'descripcion' => 'Bono Especial',
                'orden' => 5,
                'desgloce' => true,
            ]);

        $response->assertRedirect(route('admin.prod.tipos.index'));

        $this->assertDatabaseHas('prod_tipos', [
            'descripcion' => 'Bono Especial',
            'orden' => 5,
            'desgloce' => 1,
        ]);
    });

    test('tipo can be stored with defaults', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.tipos.store'), [
                'descripcion' => 'Simple',
            ]);

        $response->assertRedirect(route('admin.prod.tipos.index'));

        $this->assertDatabaseHas('prod_tipos', [
            'descripcion' => 'Simple',
            'orden' => 0,
            'desgloce' => 0,
        ]);
    });

    test('tipo can be updated', function () {
        $tipo = Tipo::factory()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.prod.tipos.update', $tipo), [
                'descripcion' => 'Updated',
                'orden' => 10,
                'desgloce' => true,
            ]);

        $response->assertRedirect(route('admin.prod.tipos.index'));

        $this->assertDatabaseHas('prod_tipos', [
            'id' => $tipo->id,
            'descripcion' => 'Updated',
            'orden' => 10,
            'desgloce' => 1,
        ]);
    });

    test('tipo can be deleted', function () {
        $tipo = Tipo::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.tipos.destroy', $tipo));

        $response->assertRedirect(route('admin.prod.tipos.index'));
        $this->assertDatabaseMissing('prod_tipos', ['id' => $tipo->id]);
    });

    test('tipo cannot be deleted with pagos extra', function () {
        $tipo = Tipo::factory()->create();
        PagoExtra::factory()->create(['tipo_id' => $tipo->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.tipos.destroy', $tipo));

        $response->assertSessionHasErrors(['error']);
        $this->assertDatabaseHas('prod_tipos', ['id' => $tipo->id]);
    });

    test('validation requires descripcion', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.tipos.store'), []);

        $response->assertSessionHasErrors(['descripcion']);
    });
});
