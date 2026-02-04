<?php

use App\Models\Intra\Area;
use App\Models\Intra\Documento;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin area', function () {
    test('index page can be rendered', function () {
        Area::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.intra.areas.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/intra/areas/index')
            ->has('areas.data', 3)
        );
    });

    test('index page can search areas', function () {
        Area::factory()->create(['descripcion' => 'Recursos Humanos']);
        Area::factory()->create(['descripcion' => 'Contabilidad']);

        $response = $this->actingAs($this->user)
            ->get(route('admin.intra.areas.index', ['search' => 'Recursos']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('areas.data', 1)
            ->where('filters.search', 'Recursos')
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.intra.areas.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/intra/areas/create')
            ->has('parentAreas')
        );
    });

    test('area can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.intra.areas.store'), [
                'descripcion' => 'Recursos Humanos',
                'parent_id' => null,
                'order' => 1,
                'activo' => true,
            ]);

        $response->assertRedirect(route('admin.intra.areas.index'));

        $this->assertDatabaseHas('intra_area', [
            'descripcion' => 'Recursos Humanos',
            'parent_id' => null,
        ]);
    });

    test('area can be stored with parent', function () {
        $parent = Area::factory()->create(['descripcion' => 'Departamentos']);

        $response = $this->actingAs($this->user)
            ->post(route('admin.intra.areas.store'), [
                'descripcion' => 'Recursos Humanos',
                'parent_id' => $parent->id,
                'order' => 1,
                'activo' => true,
            ]);

        $response->assertRedirect(route('admin.intra.areas.index'));

        $this->assertDatabaseHas('intra_area', [
            'descripcion' => 'Recursos Humanos',
            'parent_id' => $parent->id,
        ]);
    });

    test('edit page can be rendered', function () {
        $area = Area::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.intra.areas.edit', $area));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/intra/areas/edit')
            ->has('area')
            ->has('parentAreas')
        );
    });

    test('area can be updated', function () {
        $area = Area::factory()->create([
            'descripcion' => 'Old Description',
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.intra.areas.update', $area), [
                'descripcion' => 'New Description',
                'parent_id' => null,
                'order' => 5,
                'activo' => false,
            ]);

        $response->assertRedirect(route('admin.intra.areas.index'));

        $this->assertDatabaseHas('intra_area', [
            'id' => $area->id,
            'descripcion' => 'New Description',
            'activo' => false,
        ]);
    });

    test('area cannot be set as its own parent', function () {
        $area = Area::factory()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.intra.areas.update', $area), [
                'descripcion' => $area->descripcion,
                'parent_id' => $area->id,
                'order' => 1,
                'activo' => true,
            ]);

        $response->assertSessionHasErrors(['parent_id']);
    });

    test('area can be deleted', function () {
        $area = Area::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.intra.areas.destroy', $area));

        $response->assertRedirect(route('admin.intra.areas.index'));

        $this->assertDatabaseMissing('intra_area', ['id' => $area->id]);
    });

    test('area with children cannot be deleted', function () {
        $parent = Area::factory()->create();
        Area::factory()->withParent($parent)->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.intra.areas.destroy', $parent));

        $response->assertSessionHasErrors(['delete']);

        $this->assertDatabaseHas('intra_area', ['id' => $parent->id]);
    });

    test('area with documents cannot be deleted', function () {
        $area = Area::factory()->create();
        Documento::factory()->forArea($area)->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.intra.areas.destroy', $area));

        $response->assertSessionHasErrors(['delete']);

        $this->assertDatabaseHas('intra_area', ['id' => $area->id]);
    });

    test('validation requires descripcion', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.intra.areas.store'), [
                'order' => 1,
            ]);

        $response->assertSessionHasErrors(['descripcion']);
    });
});

describe('guest access', function () {
    test('guests cannot access admin areas index', function () {
        $response = $this->get(route('admin.intra.areas.index'));

        $response->assertRedirect(route('login'));
    });

    test('guests cannot create areas', function () {
        $response = $this->post(route('admin.intra.areas.store'), []);

        $response->assertRedirect(route('login'));
    });
});
