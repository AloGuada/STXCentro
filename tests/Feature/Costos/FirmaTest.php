<?php

use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\AprobacionSolicitud;
use App\Models\Costos\SolicitudPago;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    Storage::fake('public');

    // Las rutas de firma exigen el gate 'aprobador-costos', que requiere
    // estar asignado al menos una vez como aprobador en algun departamento.
    AprobacionDepartamento::factory()->create(['aprobador_id' => $this->user->id]);

    // 1x1 pixel PNG válido en base64
    $this->validDataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
});

describe('admin costos firma', function () {
    test('firma edit page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.firma.edit'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/firma/edit')
            ->where('firmaUrl', null)
        );
    });

    test('firma can be saved from canvas data url', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.firma.update'), [
                'firma' => $this->validDataUrl,
            ]);

        $response->assertRedirect();

        $this->user->refresh();
        expect($this->user->firma_path)->not->toBeNull();
        Storage::disk('public')->assertExists($this->user->firma_path);
    });

    test('firma update requires string data', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.firma.update'), [
                'firma' => '',
            ]);

        $response->assertSessionHasErrors(['firma']);
    });

    test('firma can be deleted', function () {
        Storage::disk('public')->put('firmas/test.png', 'fake-image');
        $this->user->update(['firma_path' => 'firmas/test.png']);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.costos.firma.destroy'));

        $response->assertRedirect();

        $this->user->refresh();
        expect($this->user->firma_path)->toBeNull();
        Storage::disk('public')->assertMissing('firmas/test.png');
    });

    test('old firma is deleted when saving a new one', function () {
        Storage::disk('public')->put('firmas/old.png', 'old-image');
        $this->user->update(['firma_path' => 'firmas/old.png']);

        $this->actingAs($this->user)
            ->post(route('admin.costos.firma.update'), [
                'firma' => $this->validDataUrl,
            ]);

        Storage::disk('public')->assertMissing('firmas/old.png');

        $this->user->refresh();
        expect($this->user->firma_path)->not->toBe('firmas/old.png');
        Storage::disk('public')->assertExists($this->user->firma_path);
    });

    test('aprobaciones index redirects if no firma configured', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.aprobaciones.index'));

        $response->assertRedirect(route('admin.costos.firma.edit'));
    });

    test('aprobaciones show redirects if no firma configured', function () {
        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();
        $aprobacion = AprobacionSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'nivel' => 1,
            'aprobador_id' => $this->user->id,
            'estatus' => 'pendiente',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.aprobaciones.show', $aprobacion));

        $response->assertRedirect(route('admin.costos.firma.edit'));
    });

    test('aprobaciones accessible with firma configured', function () {
        $this->user->update(['firma_path' => 'firmas/test.png']);

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.aprobaciones.index'));

        $response->assertOk();
    });
});
