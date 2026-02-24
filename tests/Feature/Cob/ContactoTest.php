<?php

use App\Models\Cliente;
use App\Models\Cob\Contacto;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->cliente = Cliente::factory()->create();
});

describe('admin cob contactos', function () {
    test('contacto can be stored for a cliente', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.cob.clientes.contactos.store', $this->cliente), [
                'cliente_id' => $this->cliente->id,
                'nombre' => 'Juan Perez',
                'email' => 'juan@example.com',
                'telefono' => '5559876543',
                'cargo' => 'Gerente',
                'activo' => true,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cob_contactos', [
            'cliente_id' => $this->cliente->id,
            'nombre' => 'Juan Perez',
            'email' => 'juan@example.com',
        ]);
    });

    test('contacto can be updated', function () {
        $contacto = Contacto::factory()->create(['cliente_id' => $this->cliente->id]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.cob.clientes.contactos.update', [$this->cliente, $contacto]), [
                'nombre' => 'Updated Name',
                'email' => 'updated@example.com',
                'telefono' => '5551111111',
                'cargo' => 'Director',
                'activo' => true,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cob_contactos', [
            'id' => $contacto->id,
            'nombre' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    });

    test('contacto can be deleted', function () {
        $contacto = Contacto::factory()->create(['cliente_id' => $this->cliente->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.cob.clientes.contactos.destroy', [$this->cliente, $contacto]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('cob_contactos', ['id' => $contacto->id]);
    });
});
