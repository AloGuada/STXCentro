<?php

use App\Models\Obra;
use App\Models\Pieza;
use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\MarcaGrupo;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin grupo precios', function () {
    test('index page shows obras', function () {
        Obra::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.grupo-precios.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/grupo-precios/index')
            ->has('obras.data', 3)
        );
    });

    test('showByObra page can be rendered', function () {
        $obra = Obra::factory()->create();
        Pieza::factory()->count(2)->create(['obra_id' => $obra->id]);
        GrupoPrecio::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.grupo-precios.show-by-obra', $obra));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/grupo-precios/show')
            ->has('obra')
            ->has('grupoPrecios')
            ->has('marcaGrupos')
        );
    });

    test('grupo precio can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.grupo-precios.store'), [
                'descripcion' => 'Precio Normal',
                'precio' => 15.50,
            ]);

        $response->assertRedirect(route('admin.prod.grupo-precios.index'));

        $this->assertDatabaseHas('prod_grupo_precios', [
            'descripcion' => 'Precio Normal',
        ]);
    });

    test('grupo precio can be updated', function () {
        $gp = GrupoPrecio::factory()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.prod.grupo-precios.update', $gp), [
                'descripcion' => 'Updated',
                'precio' => 20,
            ]);

        $response->assertRedirect(route('admin.prod.grupo-precios.index'));

        $this->assertDatabaseHas('prod_grupo_precios', [
            'id' => $gp->id,
            'descripcion' => 'Updated',
        ]);
    });

    test('grupo precio can be deleted', function () {
        $gp = GrupoPrecio::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.grupo-precios.destroy', $gp));

        $response->assertRedirect(route('admin.prod.grupo-precios.index'));
        $this->assertDatabaseMissing('prod_grupo_precios', ['id' => $gp->id]);
    });

    test('grupo precio cannot be deleted with marca grupos', function () {
        $gp = GrupoPrecio::factory()->create();
        MarcaGrupo::factory()->create(['grupo_precio_id' => $gp->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.grupo-precios.destroy', $gp));

        $response->assertSessionHasErrors(['error']);
        $this->assertDatabaseHas('prod_grupo_precios', ['id' => $gp->id]);
    });

    test('edit page loads obras with piezas', function () {
        $gp = GrupoPrecio::factory()->create();
        $obra = Obra::factory()->create();
        Pieza::factory()->count(2)->create(['obra_id' => $obra->id]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.grupo-precios.edit', $gp));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/grupo-precios/edit')
            ->has('obras')
            ->has('grupoPrecio')
        );
    });

    test('piezas can be bulk assigned to grupo precio', function () {
        $gp = GrupoPrecio::factory()->create();
        $obra = Obra::factory()->create();
        $piezas = Pieza::factory()->count(3)->create(['obra_id' => $obra->id]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.grupo-precios.assign-piezas', $gp), [
                'pieza_ids' => $piezas->pluck('id')->toArray(),
            ]);

        $response->assertRedirect();

        expect(MarcaGrupo::where('grupo_precio_id', $gp->id)->count())->toBe(3);
    });

    test('bulk assign does not duplicate existing assignments', function () {
        $gp = GrupoPrecio::factory()->create();
        $pieza = Pieza::factory()->create();
        MarcaGrupo::create(['pieza_id' => $pieza->id, 'grupo_precio_id' => $gp->id]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.grupo-precios.assign-piezas', $gp), [
                'pieza_ids' => [$pieza->id],
            ]);

        $response->assertRedirect();

        expect(MarcaGrupo::where('grupo_precio_id', $gp->id)->count())->toBe(1);
    });
});
