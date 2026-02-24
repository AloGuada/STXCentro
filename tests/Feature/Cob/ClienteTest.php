<?php

use App\Models\Cliente;
use App\Models\Obra;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin cob clientes', function () {
    test('index page can be rendered', function () {
        Cliente::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.cob.clientes.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/cob/clientes/index')
            ->has('clientes.data', 3)
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.cob.clientes.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/cob/clientes/create')
        );
    });

    test('cliente can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.cob.clientes.store'), [
                'nombre' => 'Cliente Test SA',
                'rfc' => 'CTE1234567A0',
                'direccion' => 'Calle Falsa 123',
                'telefono' => '5551234567',
                'email' => 'test@cliente.com',
                'activo' => true,
            ]);

        $response->assertRedirect(route('admin.cob.clientes.index'));
        $this->assertDatabaseHas('clientes', [
            'nombre' => 'Cliente Test SA',
            'rfc' => 'CTE1234567A0',
        ]);
    });

    test('edit page can be rendered', function () {
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.cob.clientes.edit', $cliente));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/cob/clientes/edit')
            ->has('cliente')
        );
    });

    test('cliente can be updated', function () {
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.cob.clientes.update', $cliente), [
                'nombre' => 'Updated SA',
                'rfc' => $cliente->rfc,
                'activo' => true,
            ]);

        $response->assertRedirect(route('admin.cob.clientes.index'));
        $this->assertDatabaseHas('clientes', [
            'id' => $cliente->id,
            'nombre' => 'Updated SA',
        ]);
    });

    test('cliente can be deleted', function () {
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.cob.clientes.destroy', $cliente));

        $response->assertRedirect(route('admin.cob.clientes.index'));
        $this->assertDatabaseMissing('clientes', ['id' => $cliente->id]);
    });

    test('cliente with obras cannot be deleted', function () {
        $cliente = Cliente::factory()->create();
        Obra::factory()->create(['cliente_id' => $cliente->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.cob.clientes.destroy', $cliente));

        $response->assertSessionHasErrors(['delete']);
        $this->assertDatabaseHas('clientes', ['id' => $cliente->id]);
    });
});
