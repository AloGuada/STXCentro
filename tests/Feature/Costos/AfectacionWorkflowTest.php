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

describe('afectacion presupuestal workflow', function () {
    test('generar pdf changes estatus to pendiente_firma', function () {
        $afectacion = AfectacionPresupuestal::factory()->create(['estatus' => 'borrador']);
        AfectacionDetalle::factory()->create(['afectacion_id' => $afectacion->id]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.afectaciones.pdf', $afectacion));

        $response->assertOk();
        $afectacion->refresh();
        expect($afectacion->estatus->value)->toBe('pendiente_firma');
        expect($afectacion->historial)->toHaveCount(1);
    });

    test('upload firmado changes estatus to aprobada and applies budget', function () {
        Storage::fake('public');

        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
        $afectacion = AfectacionPresupuestal::factory()->pendienteFirma()->create();
        AfectacionDetalle::factory()->create([
            'afectacion_id' => $afectacion->id,
            'obra_rubro_id' => $obraRubro->id,
            'monto' => 5000,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.afectaciones.upload-firmado', $afectacion), [
                'archivo' => UploadedFile::fake()->create('firmado.pdf', 100, 'application/pdf'),
            ]);

        $response->assertRedirect();
        $afectacion->refresh();
        expect($afectacion->estatus->value)->toBe('aprobada');
        expect($afectacion->aprobado_por)->toBe($this->user->id);

        $obraRubro->refresh();
        expect((float) $obraRubro->acumulado)->toBe(5000.00);
    });

    test('upload firmado creates rubros afectados', function () {
        Storage::fake('public');

        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
        $afectacion = AfectacionPresupuestal::factory()->pendienteFirma()->create();
        AfectacionDetalle::factory()->create([
            'afectacion_id' => $afectacion->id,
            'obra_rubro_id' => $obraRubro->id,
            'monto' => 3000,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.afectaciones.upload-firmado', $afectacion), [
                'archivo' => UploadedFile::fake()->create('firmado.pdf', 100, 'application/pdf'),
            ]);

        $afectacion->refresh();
        expect($afectacion->rubrosAfectados)->toHaveCount(1);
        expect($afectacion->rubrosAfectados->first()->tipo_movimiento)->toBe('cargo');
        expect($afectacion->rubrosAfectados->first()->estatus->value)->toBe('aplicado');
    });

    test('cancelar reverts budget if aprobada', function () {
        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 5000]);
        $afectacion = AfectacionPresupuestal::factory()->aprobada()->create();
        AfectacionDetalle::factory()->create([
            'afectacion_id' => $afectacion->id,
            'obra_rubro_id' => $obraRubro->id,
            'monto' => 5000,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.afectaciones.cancelar', $afectacion), ['motivo' => 'Cancelación motivada por test']);

        $response->assertRedirect();
        $afectacion->refresh();
        expect($afectacion->estatus->value)->toBe('cancelada');

        $obraRubro->refresh();
        expect((float) $obraRubro->acumulado)->toBe(0.00);
    });

    test('cancelar pendiente_firma does not revert budget', function () {
        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
        $afectacion = AfectacionPresupuestal::factory()->pendienteFirma()->create();
        AfectacionDetalle::factory()->create([
            'afectacion_id' => $afectacion->id,
            'obra_rubro_id' => $obraRubro->id,
            'monto' => 3000,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.afectaciones.cancelar', $afectacion), ['motivo' => 'Cancelación motivada por test']);

        $afectacion->refresh();
        expect($afectacion->estatus->value)->toBe('cancelada');

        $obraRubro->refresh();
        expect((float) $obraRubro->acumulado)->toBe(0.00);
    });

    test('afectar aplica el presupuesto y deja la afectacion en aprobada', function () {
        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
        $afectacion = AfectacionPresupuestal::factory()->create(['estatus' => 'borrador']);
        AfectacionDetalle::factory()->create([
            'afectacion_id' => $afectacion->id,
            'obra_rubro_id' => $obraRubro->id,
            'monto' => 4000,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.afectaciones.afectar', $afectacion));

        $response->assertRedirect();
        $afectacion->refresh();
        expect($afectacion->estatus->value)->toBe('aprobada');
        expect($afectacion->aprobado_por)->toBe($this->user->id);
        expect($afectacion->rubrosAfectados)->toHaveCount(1);

        $obraRubro->refresh();
        expect((float) $obraRubro->acumulado)->toBe(4000.00);
    });

    test('no se puede afectar dos veces la misma afectacion', function () {
        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
        $afectacion = AfectacionPresupuestal::factory()->create(['estatus' => 'borrador']);
        AfectacionDetalle::factory()->create([
            'afectacion_id' => $afectacion->id,
            'obra_rubro_id' => $obraRubro->id,
            'monto' => 4000,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.afectaciones.afectar', $afectacion))
            ->assertRedirect();

        // Segundo intento: ya está aprobada, debe rechazarse.
        $this->actingAs($this->user)
            ->post(route('admin.costos.afectaciones.afectar', $afectacion))
            ->assertSessionHasErrors(['estatus']);

        $obraRubro->refresh();
        expect((float) $obraRubro->acumulado)->toBe(4000.00);
    });

    test('no se puede afectar sin centros de costos', function () {
        $afectacion = AfectacionPresupuestal::factory()->create(['estatus' => 'borrador']);

        $this->actingAs($this->user)
            ->post(route('admin.costos.afectaciones.afectar', $afectacion))
            ->assertSessionHasErrors(['detalles']);
    });

    test('cannot upload firmado if not pendiente_firma', function () {
        Storage::fake('public');

        $afectacion = AfectacionPresupuestal::factory()->create(['estatus' => 'borrador']);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.afectaciones.upload-firmado', $afectacion), [
                'archivo' => UploadedFile::fake()->create('firmado.pdf', 100, 'application/pdf'),
            ]);

        $response->assertSessionHasErrors(['estatus']);
    });

    test('cannot cancelar borrador', function () {
        $afectacion = AfectacionPresupuestal::factory()->create(['estatus' => 'borrador']);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.afectaciones.cancelar', $afectacion), ['motivo' => 'Cancelación motivada por test']);

        $response->assertSessionHasErrors(['estatus']);
    });
});
