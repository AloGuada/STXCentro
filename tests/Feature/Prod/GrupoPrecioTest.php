<?php

use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin grupo precios', function () {
    test('index page shows obras with conceptos counts', function () {
        $obra = Obra::factory()->create();
        $concepto = Concepto::factory()->create(['obra_id' => $obra->id, 'activo' => true]);
        Concepto::factory()->create(['obra_id' => $obra->id, 'activo' => true]);

        $gp = GrupoPrecio::factory()->create(['obra_id' => $obra->id]);
        GrupoPrecioConcepto::factory()->create(['grupo_precio_id' => $gp->id, 'concepto_id' => $concepto->id]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.grupo-precios.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/grupo-precios/index')
            ->has('obras.data', 1)
        );
    });

    test('show by obra page can be rendered', function () {
        $obra = Obra::factory()->create();
        GrupoPrecio::factory()->create(['obra_id' => $obra->id]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.grupo-precios.show-by-obra', $obra));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/grupo-precios/show')
            ->has('obra')
            ->has('grupoPrecios')
            ->has('unassignedConceptos')
        );
    });

    test('create asume la obra que viene en la url', function () {
        $obra = Obra::factory()->create();
        Obra::factory()->count(2)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.grupo-precios.create', ['obra_id' => $obra->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/grupo-precios/create')
            ->where('obra.id', $obra->id)
            // Con la obra dada no hace falta mandar el catalogo completo.
            ->has('obras', 0)
        );
    });

    test('create sin obra en la url ofrece el selector', function () {
        Obra::factory()->count(2)->create();

        $this->actingAs($this->user)
            ->get(route('admin.prod.grupo-precios.create'))
            ->assertInertia(fn ($page) => $page
                ->where('obra', null)
                ->has('obras', 2)
            );
    });

    test('create cae al selector si la obra de la url no existe', function () {
        Obra::factory()->create();

        $this->actingAs($this->user)
            ->get(route('admin.prod.grupo-precios.create', ['obra_id' => 99999]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('obra', null)->has('obras', 1));
    });

    test('create no asume el proyecto de planta', function () {
        $planta = Obra::factory()->planta()->create();

        $this->actingAs($this->user)
            ->get(route('admin.prod.grupo-precios.create', ['obra_id' => $planta->id]))
            ->assertInertia(fn ($page) => $page->where('obra', null));
    });

    test('grupo precio can be stored', function () {
        $obra = Obra::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.grupo-precios.store'), [
                'obra_id' => $obra->id,
                'descripcion' => 'Precio Normal',
                'precio_kilo' => 15.5000,
            ]);

        $response->assertRedirect(route('admin.prod.grupo-precios.show-by-obra', $obra));

        $this->assertDatabaseHas('prod_grupos_precio', [
            'obra_id' => $obra->id,
            'descripcion' => 'Precio Normal',
        ]);
    });

    test('grupo precio can be updated', function () {
        $gp = GrupoPrecio::factory()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.prod.grupo-precios.update', $gp), [
                'obra_id' => $gp->obra_id,
                'descripcion' => 'Updated',
                'precio_kilo' => 20.0000,
            ]);

        $response->assertRedirect(route('admin.prod.grupo-precios.show-by-obra', $gp->obra_id));

        $this->assertDatabaseHas('prod_grupos_precio', [
            'id' => $gp->id,
            'descripcion' => 'Updated',
        ]);
    });

    test('grupo precio can be deleted', function () {
        $gp = GrupoPrecio::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.grupo-precios.destroy', $gp));

        $response->assertRedirect(route('admin.prod.grupo-precios.show-by-obra', $gp->obra_id));
        $this->assertDatabaseMissing('prod_grupos_precio', ['id' => $gp->id]);
    });

    test('grupo precio cannot be deleted with conceptos assigned', function () {
        $gp = GrupoPrecio::factory()->create();
        GrupoPrecioConcepto::factory()->create(['grupo_precio_id' => $gp->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.grupo-precios.destroy', $gp));

        $response->assertSessionHasErrors(['error']);
        $this->assertDatabaseHas('prod_grupos_precio', ['id' => $gp->id]);
    });

    test('edit page loads obras', function () {
        $gp = GrupoPrecio::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.grupo-precios.edit', $gp));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/grupo-precios/edit')
            ->has('obras')
            ->has('grupoPrecio')
        );
    });

    test('conceptos can be bulk assigned to grupo precio', function () {
        $obra = Obra::factory()->create();
        $gp = GrupoPrecio::factory()->create(['obra_id' => $obra->id]);
        $conceptos = Concepto::factory()->count(3)->create(['obra_id' => $obra->id]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.grupo-precios.assign-conceptos', $gp), [
                'concepto_ids' => $conceptos->pluck('id')->toArray(),
            ]);

        $response->assertRedirect();

        expect(GrupoPrecioConcepto::where('grupo_precio_id', $gp->id)->count())->toBe(3);
    });

    test('bulk assign does not duplicate existing assignments', function () {
        $obra = Obra::factory()->create();
        $gp = GrupoPrecio::factory()->create(['obra_id' => $obra->id]);
        $concepto = Concepto::factory()->create(['obra_id' => $obra->id]);
        GrupoPrecioConcepto::create(['concepto_id' => $concepto->id, 'grupo_precio_id' => $gp->id]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.grupo-precios.assign-conceptos', $gp), [
                'concepto_ids' => [$concepto->id],
            ]);

        $response->assertRedirect();

        expect(GrupoPrecioConcepto::where('grupo_precio_id', $gp->id)->count())->toBe(1);
    });

    test('pivot concepto can be stored', function () {
        $obra = Obra::factory()->create();
        $concepto = Concepto::factory()->create(['obra_id' => $obra->id]);
        $gp = GrupoPrecio::factory()->create(['obra_id' => $obra->id]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.grupo-precio-conceptos.store'), [
                'concepto_id' => $concepto->id,
                'grupo_precio_id' => $gp->id,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('prod_grupo_precio_conceptos', [
            'concepto_id' => $concepto->id,
            'grupo_precio_id' => $gp->id,
        ]);
    });

    test('pivot concepto can be destroyed', function () {
        $gpc = GrupoPrecioConcepto::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.grupo-precio-conceptos.destroy', $gpc));

        $response->assertRedirect();
        $this->assertDatabaseMissing('prod_grupo_precio_conceptos', ['id' => $gpc->id]);
    });
});
