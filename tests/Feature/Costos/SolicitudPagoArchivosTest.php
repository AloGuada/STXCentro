<?php

use App\Models\Costos\Documento;
use App\Models\Costos\SolicitudArchivo;
use App\Models\Costos\SolicitudPago;
use App\Models\Costos\TipoSolicitud;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    darPermisosSolicitudesPago($this->user);
    Storage::fake('public');
});

describe('admin costos solicitud archivos', function () {
    test('archivo can be uploaded', function () {
        $tipoSolicitud = TipoSolicitud::factory()->create();
        $documento = Documento::create([
            'tipo_solicitud_id' => $tipoSolicitud->id,
            'titulo' => 'Factura',
            'multiple' => false,
        ]);
        $solicitud = SolicitudPago::factory()->create(['tipo_solicitud_id' => $tipoSolicitud->id]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.archivos.store', $solicitud), [
                'archivo' => UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf'),
                'archivo_id' => $documento->id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('costos_solicitud_archivos', [
            'solicitud_id' => $solicitud->id,
            'archivo_id' => $documento->id,
        ]);
        $archivo = SolicitudArchivo::where('solicitud_id', $solicitud->id)->first();
        expect($archivo->media)->not->toBeNull();
        expect($archivo->media->nombre_original)->toBe('factura.pdf');
    });

    test('archivo can be deleted', function () {
        $solicitud = SolicitudPago::factory()->create();
        $tipoSolicitud = $solicitud->tipoSolicitud;
        $documento = Documento::create([
            'tipo_solicitud_id' => $tipoSolicitud->id,
            'titulo' => 'Cotizacion',
            'multiple' => false,
        ]);

        Storage::disk('public')->put('costos/solicitudes/test.pdf', 'content');

        $media = \App\Models\Media::create([
            'descripcion' => 'archivo',
            'nombre_original' => 'test.pdf',
            'path' => 'costos/solicitudes/test.pdf',
            'mime' => 'application/pdf',
            'size' => 100,
        ]);
        $archivo = SolicitudArchivo::create([
            'solicitud_id' => $solicitud->id,
            'archivo_id' => $documento->id,
            'media_id' => $media->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.costos.solicitudes-pago.archivos.destroy', [$solicitud, $archivo]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('costos_solicitud_archivos', ['id' => $archivo->id]);
    });

    /**
     * El limite vive en dos lugares que tienen que decir lo mismo: la regla del
     * Form Request y `MAX_FILE_SIZE_MB` de `resources/js/lib/uploads.ts`, que es
     * lo que el navegador enseña y valida antes de mandar. Si se separan, el
     * usuario elige un archivo que la pantalla acepta y el servidor rechaza.
     * Al mover el numero, mover tambien php.ini `upload_max_filesize`.
     */
    test('archivo mayor a 15 MB es rechazado', function () {
        $tipoSolicitud = TipoSolicitud::factory()->create();
        $documento = Documento::create([
            'tipo_solicitud_id' => $tipoSolicitud->id,
            'titulo' => 'Factura',
            'multiple' => false,
        ]);
        $solicitud = SolicitudPago::factory()->create(['tipo_solicitud_id' => $tipoSolicitud->id]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.archivos.store', $solicitud), [
                'archivo' => UploadedFile::fake()->create('grande.pdf', 15361, 'application/pdf'),
                'archivo_id' => $documento->id,
            ]);

        $response->assertSessionHasErrors('archivo');
        $this->assertDatabaseMissing('costos_solicitud_archivos', [
            'solicitud_id' => $solicitud->id,
            'archivo_id' => $documento->id,
        ]);
    });

    test('archivo de hasta 15 MB es aceptado', function () {
        $tipoSolicitud = TipoSolicitud::factory()->create();
        $documento = Documento::create([
            'tipo_solicitud_id' => $tipoSolicitud->id,
            'titulo' => 'Factura',
            'multiple' => false,
        ]);
        $solicitud = SolicitudPago::factory()->create(['tipo_solicitud_id' => $tipoSolicitud->id]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.archivos.store', $solicitud), [
                'archivo' => UploadedFile::fake()->create('grande.pdf', 15360, 'application/pdf'),
                'archivo_id' => $documento->id,
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('costos_solicitud_archivos', [
            'solicitud_id' => $solicitud->id,
            'archivo_id' => $documento->id,
        ]);
    });

    test('upload requires archivo and archivo_id', function () {
        $solicitud = SolicitudPago::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.archivos.store', $solicitud), []);

        $response->assertSessionHasErrors(['archivo', 'archivo_id']);
    });

    test('cannot delete archivo from different solicitud', function () {
        $solicitud1 = SolicitudPago::factory()->create();
        $solicitud2 = SolicitudPago::factory()->create();
        $documento = Documento::create([
            'tipo_solicitud_id' => $solicitud1->tipo_solicitud_id,
            'titulo' => 'Doc',
            'multiple' => false,
        ]);

        $archivo = SolicitudArchivo::create([
            'solicitud_id' => $solicitud1->id,
            'archivo_id' => $documento->id,
            'ruta_archivo' => 'test.pdf',
            'nombre_original' => 'test.pdf',
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.costos.solicitudes-pago.archivos.destroy', [$solicitud2, $archivo]));

        $response->assertNotFound();
    });
});
