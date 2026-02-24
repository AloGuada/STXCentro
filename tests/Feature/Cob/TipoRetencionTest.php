<?php

use App\Models\Cob\Retencion;
use App\Models\Cob\TipoRetencion;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin cob tipos retenciones', function () {
    test('index page can be rendered', function () {
        TipoRetencion::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.cob.tipos-retenciones.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/cob/tipos-retenciones/index')
            ->has('tiposRetenciones.data', 3)
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.cob.tipos-retenciones.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/cob/tipos-retenciones/create')
        );
    });

    test('tipo retencion can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.cob.tipos-retenciones.store'), [
                'nombre' => 'Fondo de garantia',
                'descripcion' => '5% del monto de la estimacion',
            ]);

        $response->assertRedirect(route('admin.cob.tipos-retenciones.index'));
        $this->assertDatabaseHas('cob_tipos_retenciones', [
            'nombre' => 'Fondo de garantia',
        ]);
    });

    test('edit page can be rendered', function () {
        $tipoRetencion = TipoRetencion::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.cob.tipos-retenciones.edit', $tipoRetencion));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/cob/tipos-retenciones/edit')
            ->has('tipoRetencion')
        );
    });

    test('tipo retencion can be updated', function () {
        $tipoRetencion = TipoRetencion::factory()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.cob.tipos-retenciones.update', $tipoRetencion), [
                'nombre' => 'Retencion actualizada',
                'descripcion' => 'Descripcion actualizada',
            ]);

        $response->assertRedirect(route('admin.cob.tipos-retenciones.index'));
        $this->assertDatabaseHas('cob_tipos_retenciones', [
            'id' => $tipoRetencion->id,
            'nombre' => 'Retencion actualizada',
        ]);
    });

    test('tipo retencion can be deleted', function () {
        $tipoRetencion = TipoRetencion::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.cob.tipos-retenciones.destroy', $tipoRetencion));

        $response->assertRedirect(route('admin.cob.tipos-retenciones.index'));
        $this->assertDatabaseMissing('cob_tipos_retenciones', ['id' => $tipoRetencion->id]);
    });

    test('tipo retencion in use cannot be deleted', function () {
        $tipoRetencion = TipoRetencion::factory()->create();
        Retencion::factory()->create(['tipo_retencion_id' => $tipoRetencion->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.cob.tipos-retenciones.destroy', $tipoRetencion));

        $response->assertSessionHasErrors(['delete']);
        $this->assertDatabaseHas('cob_tipos_retenciones', ['id' => $tipoRetencion->id]);
    });
});
