<?php

use App\Models\Costos\AfectacionDetalle;
use App\Models\Costos\AfectacionPresupuestal;
use App\Models\Costos\ObraRubro;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
            ->has('obras')
            ->has('obraRubros')
        );
    });

    test('afectacion requiere al menos un centro de costos', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.afectaciones.store'), [
                'fecha' => '2026-02-12',
                'tipo_origen' => 'gasto_directo',
                'descripcion' => 'Sin detalles',
            ]);

        $response->assertSessionHasErrors(['detalles']);
    });

    test('afectacion se guarda con monto directo y sin departamento', function () {
        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.afectaciones.store'), [
                'fecha' => '2026-02-12',
                'tipo_origen' => 'nomina',
                'descripcion' => 'Nómina quincenal',
                'detalles' => [
                    ['obra_rubro_id' => $obraRubro->id, 'monto' => 1505.00],
                ],
            ]);

        $response->assertRedirect(route('admin.costos.afectaciones.index'));

        $afectacion = AfectacionPresupuestal::latest('id')->first();
        expect($afectacion->detalles)->toHaveCount(1);
        expect($afectacion->departamento_id)->toBeNull();
        expect((float) $afectacion->monto_total)->toBe(1505.00);
        expect((float) $afectacion->detalles->first()->monto)->toBe(1505.00);
    });

    test('afectacion guarda documentos de sustento', function () {
        Storage::fake('public');
        $obraRubro = ObraRubro::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.costos.afectaciones.store'), [
                'fecha' => '2026-02-12',
                'tipo_origen' => 'otro',
                'descripcion' => 'Con sustento',
                'detalles' => [
                    ['obra_rubro_id' => $obraRubro->id, 'monto' => 500],
                ],
                'documentos' => [UploadedFile::fake()->create('sustento.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect();

        $afectacion = AfectacionPresupuestal::latest('id')->first();
        expect($afectacion->media)->toHaveCount(1);
        Storage::disk('public')->assertExists($afectacion->media->first()->path);
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
                'detalles' => [
                    ['id' => $detalle->id, 'obra_rubro_id' => $obraRubro->id, 'monto' => 1000],
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
            'monto' => 1000,
            'concepto' => 'Updated descripcion',
        ]);
    });

    test('non-borrador afectacion cannot be updated', function () {
        $afectacion = AfectacionPresupuestal::factory()->aprobada()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.costos.afectaciones.update', $afectacion), [
                'fecha' => '2026-02-12',
                'tipo_origen' => 'nomina',
                'descripcion' => 'Should not update',
                'detalles' => [
                    ['obra_rubro_id' => ObraRubro::factory()->create()->id, 'monto' => 100],
                ],
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

        $response->assertSessionHasErrors(['fecha', 'tipo_origen', 'descripcion', 'detalles']);
    });
});
