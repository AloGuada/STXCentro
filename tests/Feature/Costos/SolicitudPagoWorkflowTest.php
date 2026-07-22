<?php

use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Permiso;
use App\Models\Costos\SolicitudPago;
use App\Models\Costos\SolicitudPagoDetalle;
use App\Models\Departamento;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    darPermisosSolicitudesPago($this->user);
});

describe('admin costos solicitud pago workflow', function () {
    test('enviar a aprobacion cambia estatus a pendiente_firma y crea la cadena', function () {
        $departamento = Departamento::factory()->create();
        $permiso = Permiso::factory()->create(['nivel' => 1, 'descripcion' => 'Jefe Depto']);
        AprobacionDepartamento::factory()->create([
            'departamento_id' => $departamento->id,
            'permiso_id' => $permiso->id,
        ]);

        $solicitud = SolicitudPago::factory()->create([
            'departamento_id' => $departamento->id,
            'estatus' => 'borrador',
            'solicitante_id' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.enviar-aprobacion', $solicitud))
            ->assertRedirect();

        $solicitud->refresh();
        expect($solicitud->estatus->value)->toBe('pendiente_firma');
        expect($solicitud->aprobaciones)->toHaveCount(1);
    });

    test('generar pdf en borrador no cambia el estatus (solo previsualiza)', function () {
        $solicitud = SolicitudPago::factory()->create(['estatus' => 'borrador']);

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.solicitudes-pago.pdf', $solicitud));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        expect($solicitud->fresh()->estatus->value)->toBe('borrador');
    });

    test('solo el creador puede enviar la solicitud a aprobacion', function () {
        $solicitud = SolicitudPago::factory()->create([
            'estatus' => 'borrador',
            'solicitante_id' => User::factory()->create()->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.enviar-aprobacion', $solicitud))
            ->assertForbidden();

        expect($solicitud->fresh()->estatus->value)->toBe('borrador');
    });

    test('generar pdf con detalles muestra obra y centro de costos', function () {
        $obraRubro = ObraRubro::factory()->create();
        $solicitud = SolicitudPago::factory()->create(['estatus' => 'borrador']);
        SolicitudPagoDetalle::factory()->create([
            'solicitud_id' => $solicitud->id,
            'obra_rubro_id' => $obraRubro->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.solicitudes-pago.pdf', $solicitud));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    });

    test('enviar a aprobacion crea aprobaciones de la cadena del departamento', function () {
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
            'solicitante_id' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.enviar-aprobacion', $solicitud));

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
        expect($solicitud->estatus->value)->toBe('aprobada');
        expect($solicitud->media)->not->toBeNull();
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

    test('upload firmado convierte el apartado sin doble conteo', function () {
        Storage::fake('public');

        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();
        SolicitudPagoDetalle::factory()->create([
            'solicitud_id' => $solicitud->id,
            'obra_rubro_id' => $obraRubro->id,
            'subtotal' => 5000,
        ]);

        // La solicitud ya tiene una reserva viva (apartado) al estar PendienteFirma.
        app(\App\Services\Costos\ApartadoPresupuestal::class)
            ->apartarDocumento($solicitud, [['obra_rubro_id' => $obraRubro->id, 'monto' => 5000]]);

        expect((float) $obraRubro->fresh()->apartado)->toBe(5000.00);
        expect((float) $obraRubro->fresh()->acumulado)->toBe(0.00);

        $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.upload-firmado', $solicitud), [
                'archivo' => UploadedFile::fake()->create('firmado.pdf', 100, 'application/pdf'),
            ]);

        $obraRubro->refresh();
        // El apartado se convierte a ejercido: 5000 (no 10000 por doble conteo).
        expect((float) $obraRubro->acumulado)->toBe(5000.00);
        expect((float) $obraRubro->apartado)->toBe(0.00);
        expect((float) $obraRubro->comprometido)->toBe(5000.00);
    });

    test('cancelar aprobada reverts budget impact', function () {
        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
        $solicitud = SolicitudPago::factory()->aprobada()->create();
        SolicitudPagoDetalle::factory()->create([
            'solicitud_id' => $solicitud->id,
            'obra_rubro_id' => $obraRubro->id,
            'subtotal' => 5000,
        ]);

        // Estado realista: el impacto aplicado crea el RubroAfectado que respalda
        // el acumulado; la cancelación lo revierte por rubro afectado.
        $solicitud->load('detalles');
        $solicitud->aplicarImpactoPresupuestal();
        expect((float) $obraRubro->fresh()->acumulado)->toBe(5000.00);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.cancelar', $solicitud), ['motivo' => 'Cancelación motivada por test']);

        $response->assertRedirect();

        $solicitud->refresh();
        expect($solicitud->estatus->value)->toBe('cancelada');

        $obraRubro->refresh();
        expect((float) $obraRubro->acumulado)->toBe(0.00);
    });

    test('confirmar costos creates pago for contado solicitud', function () {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'costos.solicitudes.confirmar-costos']);
        $this->user->givePermissionTo('costos.solicitudes.confirmar-costos');

        $solicitud = SolicitudPago::factory()->aprobada()->create([
            'tipo_pago' => 'transferencia',
            'fecha_pago_solicitada' => now()->addDays(5)->toDateString(),
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.confirmar-costos', $solicitud));

        $response->assertRedirect();

        $solicitud->refresh();
        expect($solicitud->confirmada_costos)->toBeTrue();
        expect($solicitud->pago)->not->toBeNull();
        expect($solicitud->pago->tipo_pago)->toBe('contado');
        expect($solicitud->pago->estatus->value)->toBe('programado');
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
});
