<?php

use App\Models\Sti\Equipo;
use App\Models\Sti\Grupo;
use App\Models\Sti\Item;
use App\Models\Sti\ItemHistorial;
use App\Models\Sti\ItemTipo;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin items', function () {
    test('index page can be rendered', function () {
        $tipo = ItemTipo::factory()->create();
        Item::factory()->count(3)->create(['tipo_id' => $tipo->id]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.items.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/sti/items/index')
            ->has('items.data', 3)
            ->has('tipos')
        );
    });

    test('index filters by tipo_id', function () {
        $tipoA = ItemTipo::factory()->create(['descripcion' => 'RAM']);
        $tipoB = ItemTipo::factory()->create(['descripcion' => 'SSD']);
        Item::factory()->count(2)->create(['tipo_id' => $tipoA->id]);
        Item::factory()->create(['tipo_id' => $tipoB->id]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.items.index', ['tipo_id' => $tipoA->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('items.data', 2)
        );
    });

    test('index filters by estado', function () {
        $tipo = ItemTipo::factory()->create();
        Item::factory()->count(2)->create(['tipo_id' => $tipo->id, 'estado' => 'disponible']);
        Item::factory()->create(['tipo_id' => $tipo->id, 'estado' => 'dañado']);

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.items.index', ['estado' => 'disponible']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('items.data', 2)
        );
    });

    test('create page can be rendered with tipos', function () {
        ItemTipo::factory()->count(2)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.items.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/sti/items/create')
            ->has('tipos', 2)
        );
    });

    test('item can be stored', function () {
        $tipo = ItemTipo::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.sti.items.store'), [
                'descripcion' => 'Kingston 16GB DDR5',
                'tipo_id' => $tipo->id,
                'costo' => 1500.50,
                'no_serie' => 'KNG12345678',
                'estado' => 'disponible',
            ]);

        $response->assertRedirect(route('admin.sti.items.index'));
        $this->assertDatabaseHas('sti_items', [
            'descripcion' => 'Kingston 16GB DDR5',
            'tipo_id' => $tipo->id,
        ]);
    });

    test('edit page can be rendered with relations', function () {
        $item = Item::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.items.edit', $item));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/sti/items/edit')
            ->has('item')
            ->has('tipos')
        );
    });

    test('item can be updated', function () {
        $item = Item::factory()->create(['descripcion' => 'Old']);

        $response = $this->actingAs($this->user)
            ->put(route('admin.sti.items.update', $item), [
                'descripcion' => 'Updated',
                'tipo_id' => $item->tipo_id,
                'costo' => 2000,
                'no_serie' => 'NEW123',
                'estado' => 'disponible',
            ]);

        $response->assertRedirect(route('admin.sti.items.edit', $item));
        $this->assertDatabaseHas('sti_items', [
            'id' => $item->id,
            'descripcion' => 'Updated',
        ]);
    });

    test('item can be deleted when no grupo and no historial', function () {
        $item = Item::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.sti.items.destroy', $item));

        $response->assertRedirect(route('admin.sti.items.index'));
        $this->assertDatabaseMissing('sti_items', ['id' => $item->id]);
    });

    test('item cannot be deleted when assigned to equipo', function () {
        $item = Item::factory()->instalado()->create();
        Grupo::factory()->create(['item_id' => $item->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.sti.items.destroy', $item));

        $response->assertSessionHasErrors(['delete']);
        $this->assertDatabaseHas('sti_items', ['id' => $item->id]);
    });

    test('item cannot be deleted when has historial', function () {
        $item = Item::factory()->create();
        $equipo = Equipo::factory()->create();

        ItemHistorial::create([
            'item_id' => $item->id,
            'equipo_id' => $equipo->id,
            'accion' => 'recepcion',
            'fecha' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.sti.items.destroy', $item));

        $response->assertSessionHasErrors(['delete']);
        $this->assertDatabaseHas('sti_items', ['id' => $item->id]);
    });

    test('validation requires descripcion and tipo_id', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.sti.items.store'), [
                'costo' => 100,
                'estado' => 'disponible',
            ]);

        $response->assertSessionHasErrors(['descripcion', 'tipo_id']);
    });

    test('validation rejects invalid estado', function () {
        $tipo = ItemTipo::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.sti.items.store'), [
                'descripcion' => 'Test',
                'tipo_id' => $tipo->id,
                'costo' => 100,
                'estado' => 'invalido',
            ]);

        $response->assertSessionHasErrors(['estado']);
    });
});
