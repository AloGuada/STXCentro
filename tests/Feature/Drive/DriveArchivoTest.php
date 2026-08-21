<?php

use App\Models\Drive\Archivo;
use App\Models\Drive\Carpeta;
use App\Models\Drive\Externo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->externo = Externo::factory()->create([
        'activo' => true,
    ]);

    $this->carpeta = Carpeta::factory()->create();
    $this->carpeta->externos()->attach($this->externo->id);
});

test('externo puede ver archivos de carpeta asignada', function () {
    Archivo::factory()->create(['carpeta_id' => $this->carpeta->id]);

    $this->actingAs($this->externo, 'externo')
        ->get("/drive/carpetas/{$this->carpeta->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('drive/carpetas/show'));
});

test('externo no puede ver carpeta no asignada', function () {
    $otraCarpeta = Carpeta::factory()->create();

    $this->actingAs($this->externo, 'externo')
        ->get("/drive/carpetas/{$otraCarpeta->id}")
        ->assertForbidden();
});

test('externo puede subir archivo a carpeta asignada', function () {
    $file = UploadedFile::fake()->create('documento.pdf', 1024, 'application/pdf');

    $this->actingAs($this->externo, 'externo')
        ->post('/drive/archivos', [
            'archivo' => $file,
            'carpeta_id' => $this->carpeta->id,
            'descripcion' => 'Archivo de prueba',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('drive_archivos', [
        'carpeta_id' => $this->carpeta->id,
        'nombre_original' => 'documento.pdf',
        'subido_por_type' => 'externo',
        'subido_por_id' => (string) $this->externo->id,
    ]);
});

test('externo no puede subir archivo a carpeta no asignada', function () {
    $otraCarpeta = Carpeta::factory()->create();
    $file = UploadedFile::fake()->create('documento.pdf', 1024, 'application/pdf');

    $this->actingAs($this->externo, 'externo')
        ->post('/drive/archivos', [
            'archivo' => $file,
            'carpeta_id' => $otraCarpeta->id,
        ])
        ->assertForbidden();
});

test('se rechazan archivos con mime no permitido', function () {
    $file = UploadedFile::fake()->create('malware.exe', 1024, 'application/x-msdownload');

    $this->actingAs($this->externo, 'externo')
        ->post('/drive/archivos', [
            'archivo' => $file,
            'carpeta_id' => $this->carpeta->id,
        ])
        ->assertSessionHasErrors('archivo');
});

test('externo puede descargar archivo de carpeta asignada', function () {
    Storage::disk('local')->put('drive/test.pdf', 'contenido');

    $archivo = Archivo::factory()->create([
        'carpeta_id' => $this->carpeta->id,
        'path' => 'drive/test.pdf',
    ]);

    $this->actingAs($this->externo, 'externo')
        ->get("/drive/archivos/{$archivo->id}/descargar")
        ->assertOk();
});

test('externo puede eliminar archivo propio', function () {
    Storage::disk('local')->put('drive/test.pdf', 'contenido');

    $archivo = Archivo::factory()->create([
        'carpeta_id' => $this->carpeta->id,
        'path' => 'drive/test.pdf',
        'subido_por_type' => 'externo',
        'subido_por_id' => (string) $this->externo->id,
    ]);

    $this->actingAs($this->externo, 'externo')
        ->delete("/drive/archivos/{$archivo->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('drive_archivos', ['id' => $archivo->id]);
});

test('externo no puede eliminar archivo subido por otro', function () {
    $archivo = Archivo::factory()->create([
        'carpeta_id' => $this->carpeta->id,
        'subido_por_type' => 'interno',
        'subido_por_id' => '1',
    ]);

    $this->actingAs($this->externo, 'externo')
        ->delete("/drive/archivos/{$archivo->id}")
        ->assertForbidden();
});
