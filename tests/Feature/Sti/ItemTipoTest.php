<?php

use App\Models\Sti\Item;
use App\Models\Sti\ItemTipo;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin items tipos', function () {
    test('index page can be rendered', function () {
        ItemTipo::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.items-tipos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/sti/items-tipos/index')
            ->has('tipos.data', 3)
        );
    });

    test('index supports search filter', function () {
        ItemTipo::factory()->create(['descripcion' => 'RAM']);
        ItemTipo::factory()->create(['descripcion' => 'SSD']);

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.items-tipos.index', ['search' => 'RAM']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('tipos.data', 1)
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.items-tipos.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/sti/items-tipos/create')
        );
    });

    test('tipo can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.sti.items-tipos.store'), [
                'descripcion' => 'RAM DDR5',
            ]);

        $response->assertRedirect(route('admin.sti.items-tipos.index'));
        $this->assertDatabaseHas('sti_items_tipos', [
            'descripcion' => 'RAM DDR5',
        ]);
    });

    test('edit page can be rendered', function () {
        $tipo = ItemTipo::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.items-tipos.edit', $tipo));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/sti/items-tipos/edit')
            ->has('tipo')
        );
    });

    test('tipo can be updated', function () {
        $tipo = ItemTipo::factory()->create(['descripcion' => 'Old']);

        $response = $this->actingAs($this->user)
            ->put(route('admin.sti.items-tipos.update', $tipo), [
                'descripcion' => 'Updated',
            ]);

        $response->assertRedirect(route('admin.sti.items-tipos.index'));
        $this->assertDatabaseHas('sti_items_tipos', [
            'id' => $tipo->id,
            'descripcion' => 'Updated',
        ]);
    });

    test('tipo can be deleted when no items', function () {
        $tipo = ItemTipo::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.sti.items-tipos.destroy', $tipo));

        $response->assertRedirect(route('admin.sti.items-tipos.index'));
        $this->assertDatabaseMissing('sti_items_tipos', ['id' => $tipo->id]);
    });

    test('tipo cannot be deleted with items', function () {
        $tipo = ItemTipo::factory()->create();
        Item::factory()->create(['tipo_id' => $tipo->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.sti.items-tipos.destroy', $tipo));

        $response->assertSessionHasErrors(['delete']);
        $this->assertDatabaseHas('sti_items_tipos', ['id' => $tipo->id]);
    });

    test('validation requires descripcion', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.sti.items-tipos.store'), []);

        $response->assertSessionHasErrors(['descripcion']);
    });
});
