<?php

use App\Models\Costos\AfectacionDetalle;
use App\Models\Costos\AfectacionPresupuestal;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\RubroAfectado;
use App\Models\Costos\SolicitudPago;
use App\Models\Costos\SolicitudPagoDetalle;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    darPermisosSolicitudesPago($this->user);
});

describe('rubros afectados polymorphic', function () {
    test('rubros afectados from afectacion presupuestal', function () {
        Storage::fake('public');

        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
        $afectacion = AfectacionPresupuestal::factory()->pendienteFirma()->create();
        AfectacionDetalle::factory()->create([
            'afectacion_id' => $afectacion->id,
            'obra_rubro_id' => $obraRubro->id,
            'monto' => 2000,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.afectaciones.upload-firmado', $afectacion), [
                'archivo' => UploadedFile::fake()->create('firmado.pdf', 100, 'application/pdf'),
            ]);

        $rubro = RubroAfectado::where('entrada_type', AfectacionPresupuestal::class)
            ->where('entrada_id', $afectacion->id)
            ->first();

        expect($rubro)->not->toBeNull();
        expect($rubro->tipo_movimiento)->toBe('cargo');
        expect((float) $rubro->monto)->toBe(2000.00);
    });

    test('rubros afectados from solicitud pago', function () {
        Storage::fake('public');

        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();
        SolicitudPagoDetalle::factory()->create([
            'solicitud_id' => $solicitud->id,
            'obra_rubro_id' => $obraRubro->id,
            'subtotal' => 3000,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.upload-firmado', $solicitud), [
                'archivo' => UploadedFile::fake()->create('firmado.pdf', 100, 'application/pdf'),
            ]);

        $rubro = RubroAfectado::where('entrada_type', SolicitudPago::class)
            ->where('entrada_id', $solicitud->id)
            ->first();

        expect($rubro)->not->toBeNull();
        expect($rubro->tipo_movimiento)->toBe('cargo');
    });

    test('sobre_giro detected when exceeding budget', function () {
        Storage::fake('public');

        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 1000, 'acumulado' => 800]);
        $afectacion = AfectacionPresupuestal::factory()->pendienteFirma()->create();
        AfectacionDetalle::factory()->create([
            'afectacion_id' => $afectacion->id,
            'obra_rubro_id' => $obraRubro->id,
            'monto' => 500,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.afectaciones.upload-firmado', $afectacion), [
                'archivo' => UploadedFile::fake()->create('firmado.pdf', 100, 'application/pdf'),
            ]);

        $rubro = RubroAfectado::where('entrada_type', AfectacionPresupuestal::class)
            ->where('entrada_id', $afectacion->id)
            ->first();

        expect($rubro->sobre_giro)->toBeTrue();
    });

    test('cancelation creates abono rubro afectado', function () {
        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 5000]);
        $afectacion = AfectacionPresupuestal::factory()->aprobada()->create();
        AfectacionDetalle::factory()->create([
            'afectacion_id' => $afectacion->id,
            'obra_rubro_id' => $obraRubro->id,
            'monto' => 5000,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.afectaciones.cancelar', $afectacion), ['motivo' => 'Cancelación motivada por test']);

        $rubros = RubroAfectado::where('entrada_type', AfectacionPresupuestal::class)
            ->where('entrada_id', $afectacion->id)
            ->get();

        $abono = $rubros->firstWhere('tipo_movimiento', 'abono');
        expect($abono)->not->toBeNull();
        expect($abono->estatus->value)->toBe('cancelado');
    });
});
