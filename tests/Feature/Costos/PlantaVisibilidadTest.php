<?php

use App\Models\Obra;
use App\Models\Prod\Catalogo;
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

    test('no se ofrece al crear un catalogo de conceptos', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.catalogos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('obrasDisponibles', 1)
            ->where('obrasDisponibles.0.id', $this->obra->id)
        );
    });

    test('no se le puede crear un catalogo de conceptos', function () {
        $this->actingAs($this->user)
            ->post(route('admin.prod.catalogos.store'), [
                'modo' => 'existente',
                'obra_id' => $this->planta->id,
                'nombre' => 'Catalogo de planta',
            ])
            ->assertNotFound();
    });

    test('no aparece en grupos de precio de produccion', function () {
        // Ambas con catalogo vigente: la pantalla solo lista obras que lo tengan,
        // asi que la unica razon por la que planta queda fuera es ser planta.
        Catalogo::factory()->create(['obra_id' => $this->planta->id, 'vigente' => true]);
        Catalogo::factory()->create(['obra_id' => $this->obra->id, 'vigente' => true]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.grupo-precios.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('obras.data', 1)
            ->where('obras.data.0.id', $this->obra->id)
        );
    });

    test('sus presupuestos si aparecen en los selectores de requisiciones de costos', function () {
        Permission::firstOrCreate(['name' => 'costos.requisiciones.crear', 'guard_name' => 'web']);
        $this->user->givePermissionTo('costos.requisiciones.crear');

        // El presupuesto de planta y el de la obra normal deben ser seleccionables.
        \App\Models\Costos\Presupuesto::factory()->paraObra($this->planta)->create();
        \App\Models\Costos\Presupuesto::factory()->paraObra($this->obra)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.requisiciones.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('presupuestos', 2));
    });
});
