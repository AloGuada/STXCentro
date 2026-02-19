<?php

use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Pago;
use App\Models\Costos\Permiso;
use App\Models\Costos\SolicitudPago;
use App\Models\Costos\SolicitudPagoDetalle;
use App\Models\Departamento;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin costos solicitud pago workflow', function () {
    test('generar pdf changes estatus to pendiente_firma', function () {
        $departamento = Departamento::factory()->create();
        $permiso = Permiso::factory()->create(['nivel' => 1, 'descripcion' => 'Jefe Depto']);
        AprobacionDepartamento::factory()->create([
            'departamento_id' => $departamento->id,
            'permiso_id' => $permiso->id,
        ]);

        $solicitud = SolicitudPago::factory()->create([
            'departamento_id' => $departamento->id,
            'estatus' => 'borrador',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.solicitudes-pago.pdf', $solicitud));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $solicitud->refresh();
        expect($solicitud->estatus)->toBe('pendiente_firma');
        expect($solicitud->aprobaciones)->toHaveCount(1);
    });

    test('generar pdf creates aprobaciones from cadena departamento', function () {
        $departamento = Departamento::factory()->create();
        $permiso1 = Permiso::factory()->create(['nivel' => 1, 'descripcion' => 'Jefe Depto']);
        $permiso2 = Permiso::factory()->create(['nivel' => 2, 'descripcion' => 'Gerente']);
        AprobacionDepartamento::factory()->create([
            'departamento_id' => $departamento->id,
            'permiso_id' => $permiso1->id,
        ]);
        AprobacionDepartamento::factory()->create([
            'departamento_id' => $departamento->id,
            'permiso_id' => $permiso2->id,
        ]);

        $solicitud = SolicitudPago::factory()->create([
            'departamento_id' => $departamento->id,
            'estatus' => 'borrador',
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.costos.solicitudes-pago.pdf', $solicitud));

        $solicitud->refresh();
        expect($solicitud->aprobaciones)->toHaveCount(2);
    });

    test('upload firmado changes estatus to aprobada', function () {
        Storage::fake('public');

        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.upload-firmado', $solicitud), [
                'archivo' => UploadedFile::fake()->create('firmado.pdf', 100, 'application/pdf'),
            ]);

        $response->assertRedirect();

        $solicitud->refresh();
        expect($solicitud->estatus)->toBe('aprobada');
        expect($solicitud->comprobante_aprobacion_presupuesto)->not->toBeNull();
    });

    test('upload firmado applies budget impact', function () {
        Storage::fake('public');

        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();
        SolicitudPagoDetalle::factory()->create([
            'solicitud_id' => $solicitud->id,
            'obra_rubro_id' => $obraRubro->id,
            'subtotal' => 5000,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.upload-firmado', $solicitud), [
                'archivo' => UploadedFile::fake()->create('firmado.pdf', 100, 'application/pdf'),
            ]);

        $obraRubro->refresh();
        expect((float) $obraRubro->acumulado)->toBe(5000.00);
    });

    test('cancelar aprobada reverts budget impact', function () {
        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 5000]);
        $solicitud = SolicitudPago::factory()->aprobada()->create();
        SolicitudPagoDetalle::factory()->create([
            'solicitud_id' => $solicitud->id,
            'obra_rubro_id' => $obraRubro->id,
            'subtotal' => 5000,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.cancelar', $solicitud));

        $response->assertRedirect();

        $solicitud->refresh();
        expect($solicitud->estatus)->toBe('cancelada');

        $obraRubro->refresh();
        expect((float) $obraRubro->acumulado)->toBe(0.00);
    });

    test('crear pago creates pago record and redirects', function () {
        $solicitud = SolicitudPago::factory()->aprobada()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.crear-pago', $solicitud), [
                'tipo_pago' => 'contado',
                'fecha_pago_programada' => now()->addDays(5)->toDateString(),
            ]);

        $response->assertRedirect();

        $solicitud->refresh();
        expect($solicitud->pago)->not->toBeNull();
        expect($solicitud->pago->tipo_pago)->toBe('contado');
        expect($solicitud->pago->monto_pago)->toBe($solicitud->monto_total);
    });

    test('cannot upload firmado on non-pendiente solicitud', function () {
        Storage::fake('public');
        $solicitud = SolicitudPago::factory()->create(['estatus' => 'borrador']);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.upload-firmado', $solicitud), [
                'archivo' => UploadedFile::fake()->create('firmado.pdf', 100, 'application/pdf'),
            ]);

        $response->assertSessionHasErrors(['estatus']);
    });

    test('cannot crear pago on non-aprobada solicitud', function () {
        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.crear-pago', $solicitud), [
                'tipo_pago' => 'contado',
                'fecha_pago_programada' => now()->addDays(5)->toDateString(),
            ]);

        $response->assertSessionHasErrors(['estatus']);
    });

    test('crear pago redirects to existing pago if already exists', function () {
        $solicitud = SolicitudPago::factory()->aprobada()->create();
        $pago = Pago::factory()->contado()->create([
            'pagable_type' => SolicitudPago::class,
            'pagable_id' => $solicitud->id,
            'monto_pago' => $solicitud->monto_total,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.crear-pago', $solicitud), [
                'tipo_pago' => 'contado',
                'fecha_pago_programada' => now()->addDays(5)->toDateString(),
            ]);

        $response->assertRedirect(route('admin.costos.pagos.show', $pago));
    });
});
