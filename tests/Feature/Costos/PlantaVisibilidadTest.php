<?php

use App\Models\Obra;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->planta = Obra::factory()->planta()->create(['descripcion' => 'Gasto Operativo Planta']);
    $this->obra = Obra::factory()->create(['descripcion' => 'Obra Normal']);
});

describe('visibilidad del proyecto de planta fuera de costos', function () {
    test('no aparece en el catalogo de obras', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.obras.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('obras.data', 1)
            ->where('obras.data.0.id', $this->obra->id)
        );
    });

    test('no se puede editar desde el catalogo de obras', function () {
        $this->actingAs($this->user)
            ->get(route('admin.obras.edit', $this->planta))
            ->assertNotFound();
    });

    test('no se puede eliminar desde el catalogo de obras', function () {
        $this->actingAs($this->user)
            ->delete(route('admin.obras.destroy', $this->planta))
            ->assertNotFound();
    });

    test('no aparece en obras de cobranza', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.cob.obras.index', ['estatus' => 'todas']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('obras', 1)
            ->where('obras.0.id', $this->obra->id)
        );
    });

    test('su detalle de cobranza regresa 404', function () {
        $this->actingAs($this->user)
            ->get(route('admin.cob.obras.show', $this->planta))
            ->assertNotFound();
    });

    test('no aparece en conceptos de produccion', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.conceptos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('obras.data', 1)
            ->where('obras.data.0.id', $this->obra->id)
        );
    });

    test('sus conceptos de produccion regresan 404', function () {
        $this->actingAs($this->user)
            ->get(route('admin.prod.conceptos.show-by-obra', $this->planta))
            ->assertNotFound();
    });

    test('no aparece en grupos de precio de produccion', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.grupo-precios.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('obras.data', 1)
            ->where('obras.data.0.id', $this->obra->id)
        );
    });

    test('si aparece en los selectores de requisiciones de costos', function () {
        Permission::firstOrCreate(['name' => 'costos.requisiciones.crear', 'guard_name' => 'web']);
        $this->user->givePermissionTo('costos.requisiciones.crear');

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.requisiciones.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('obras', 2));
    });
});
