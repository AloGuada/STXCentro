<?php

use App\Models\Proveedor;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin proveedores', function () {
    test('index page can be rendered', function () {
        Proveedor::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.proveedores.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/proveedores/index')
            ->has('proveedores.data', 3)
        );
    });

    test('index supports search filter', function () {
        Proveedor::factory()->create(['razon_social' => 'Acme Corp']);
        Proveedor::factory()->create(['razon_social' => 'Beta Inc']);

        $response = $this->actingAs($this->user)
            ->get(route('admin.proveedores.index', ['search' => 'Acme']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('proveedores.data', 1)
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.proveedores.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/proveedores/create')
            ->has('departamentos')
        );
    });

    test('proveedor can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.proveedores.store'), [
                'codigo' => 'PROV001',
                'razon_social' => 'Test SA de CV',
                'rfc' => 'TST123456AB0',
                'tiene_acceso_portal' => false,
                'maneja_credito' => false,
                'activo' => true,
            ]);

        $response->assertRedirect(route('admin.proveedores.index'));
        $this->assertDatabaseHas('proveedores', [
            'codigo' => 'PROV001',
            'razon_social' => 'Test SA de CV',
        ]);
    });

    test('edit page can be rendered', function () {
        $proveedor = Proveedor::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.proveedores.edit', $proveedor));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/proveedores/edit')
            ->has('proveedor')
            ->has('departamentos')
        );
    });

    test('proveedor can be updated', function () {
        $proveedor = Proveedor::factory()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.proveedores.update', $proveedor), [
                'codigo' => $proveedor->codigo,
                'razon_social' => 'Updated SA',
                'rfc' => $proveedor->rfc,
                'tiene_acceso_portal' => false,
                'maneja_credito' => false,
                'activo' => true,
            ]);

        $response->assertRedirect(route('admin.proveedores.index'));
        $this->assertDatabaseHas('proveedores', [
            'id' => $proveedor->id,
            'razon_social' => 'Updated SA',
        ]);
    });

    test('proveedor can be deleted', function () {
        $proveedor = Proveedor::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.proveedores.destroy', $proveedor));

        $response->assertRedirect(route('admin.proveedores.index'));
        $this->assertDatabaseMissing('proveedores', ['id' => $proveedor->id]);
    });

    test('validation requires codigo and razon_social and rfc', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.proveedores.store'), []);

        $response->assertSessionHasErrors(['codigo', 'razon_social', 'rfc']);
    });

    test('codigo must be unique', function () {
        Proveedor::factory()->create(['codigo' => 'DUP001']);

        $response = $this->actingAs($this->user)
            ->post(route('admin.proveedores.store'), [
                'codigo' => 'DUP001',
                'razon_social' => 'Test',
                'rfc' => 'UNIQUE12345AB',
                'tiene_acceso_portal' => false,
                'maneja_credito' => false,
                'activo' => true,
            ]);

        $response->assertSessionHasErrors(['codigo']);
    });
});
