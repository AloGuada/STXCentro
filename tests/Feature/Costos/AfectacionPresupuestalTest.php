<?php

use App\Models\Costos\AfectacionDetalle;
use App\Models\Costos\AfectacionPresupuestal;
use App\Models\Costos\ObraRubro;
use App\Models\Departamento;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin costos afectaciones presupuestales', function () {
    test('index page can be rendered', function () {
        AfectacionPresupuestal::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.afectaciones.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/afectaciones/index')
            ->has('afectaciones.data', 3)
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.afectaciones.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/afectaciones/create')
            ->has('departamentos')
            ->has('proveedores')
        );
    });

    test('afectacion can be stored without detalles', function () {
        $departamento = Departamento::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.afectaciones.store'), [
                'fecha' => '2026-02-12',
                'tipo_origen' => 'gasto_directo',
                'descripcion' => 'Gasto directo de prueba',
                'departamento_id' => $departamento->id,
            ]);

        $response->assertRedirect(route('admin.costos.afectaciones.index'));
        $this->assertDatabaseHas('costos_afectaciones_presupuestales', [
            'descripcion' => 'Gasto directo de prueba',
            'creado_por' => $this->user->id,
            'estatus' => 'borrador',
        ]);
    });

    test('afectacion can be stored with detalles', function () {
        $departamento = Departamento::factory()->create();
        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.afectaciones.store'), [
                'fecha' => '2026-02-12',
                'tipo_origen' => 'nomina',
                'descripcion' => 'Nómina quincenal',
                'departamento_id' => $departamento->id,
                'detalles' => [
                    [
                        'obra_rubro_id' => $obraRubro->id,
                        'concepto' => 'Salarios',
                        'cantidad' => 10,
                        'precio_unitario' => 150.50,
                    ],
                ],
            ]);

        $response->assertRedirect(route('admin.costos.afectaciones.index'));

        $afectacion = AfectacionPresupuestal::latest('id')->first();
        expect($afectacion->detalles)->toHaveCount(1);
        expect((float) $afectacion->monto_total)->toBe(1505.00);
    });

    test('folio is auto-generated', function () {
        $afectacion = AfectacionPresupuestal::factory()->create();

        expect($afectacion->folio)->toStartWith('AF-');
    });

    test('show page can be rendered', function () {
        $afectacion = AfectacionPresupuestal::factory()->aprobada()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.afectaciones.show', $afectacion));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/afectaciones/show')
            ->has('afectacion')
        );
    });

    test('edit page redirects to show for non-borrador', function () {
        $afectacion = AfectacionPresupuestal::factory()->aprobada()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.afectaciones.edit', $afectacion));

        $response->assertRedirect(route('admin.costos.afectaciones.show', $afectacion));
    });

    test('edit page can be rendered for borrador', function () {
        $afectacion = AfectacionPresupuestal::factory()->create(['estatus' => 'borrador']);

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.afectaciones.edit', $afectacion));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/afectaciones/edit')
        );
    });

    test('afectacion can be updated syncing detalles', function () {
        $afectacion = AfectacionPresupuestal::factory()->create(['estatus' => 'borrador']);
        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
        $detalle = AfectacionDetalle::factory()->create([
            'afectacion_id' => $afectacion->id,
            'obra_rubro_id' => $obraRubro->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.costos.afectaciones.update', $afectacion), [
                'fecha' => '2026-02-12',
                'tipo_origen' => 'reembolso',
                'descripcion' => 'Updated descripcion',
                'departamento_id' => $afectacion->departamento_id,
                'detalles' => [
                    [
                        'id' => $detalle->id,
                        'obra_rubro_id' => $obraRubro->id,
                        'concepto' => 'Updated concepto',
                        'cantidad' => 5,
                        'precio_unitario' => 200,
                    ],
                ],
                '_version' => $afectacion->updated_at->toIso8601String(),
            ]);

        $response->assertRedirect(route('admin.costos.afectaciones.index'));
        $this->assertDatabaseHas('costos_afectaciones_presupuestales', [
            'id' => $afectacion->id,
            'descripcion' => 'Updated descripcion',
        ]);
        $this->assertDatabaseHas('costos_afectaciones_detalle', [
            'id' => $detalle->id,
            'concepto' => 'Updated concepto',
        ]);
    });

    test('non-borrador afectacion cannot be updated', function () {
        $afectacion = AfectacionPresupuestal::factory()->aprobada()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.costos.afectaciones.update', $afectacion), [
                'fecha' => '2026-02-12',
                'tipo_origen' => 'nomina',
                'descripcion' => 'Should not update',
                'departamento_id' => $afectacion->departamento_id,
            ]);

        $response->assertSessionHasErrors(['estatus']);
    });

    test('only borrador can be deleted', function () {
        $afectacion = AfectacionPresupuestal::factory()->create(['estatus' => 'borrador']);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.costos.afectaciones.destroy', $afectacion));

        $response->assertRedirect(route('admin.costos.afectaciones.index'));
        $this->assertDatabaseMissing('costos_afectaciones_presupuestales', ['id' => $afectacion->id]);
    });

    test('validation requires required fields', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.afectaciones.store'), []);

        $response->assertSessionHasErrors(['fecha', 'tipo_origen', 'descripcion', 'departamento_id']);
    });
});
