<?php

use App\Models\Drive\Archivo;
use App\Models\Drive\Carpeta;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->carpeta = Carpeta::factory()->create();
});

test('comando elimina archivos con auto_eliminar_en pasado', function () {
    Storage::disk('local')->put('drive/to-delete.pdf', 'contenido');

    Archivo::factory()->create([
        'carpeta_id' => $this->carpeta->id,
        'path' => 'drive/to-delete.pdf',
        'auto_eliminar_en' => now()->subHour(),
    ]);

    $this->artisan('drive:limpiar-archivos')
        ->assertSuccessful();

    $this->assertDatabaseCount('drive_archivos', 0);
    Storage::disk('local')->assertMissing('drive/to-delete.pdf');
});

test('comando no toca archivos sin fecha de auto-eliminacion', function () {
    Storage::disk('local')->put('drive/keep.pdf', 'contenido');

    Archivo::factory()->create([
        'carpeta_id' => $this->carpeta->id,
        'path' => 'drive/keep.pdf',
        'auto_eliminar_en' => null,
    ]);

    $this->artisan('drive:limpiar-archivos')
        ->assertSuccessful();

    $this->assertDatabaseCount('drive_archivos', 1);
});

test('comando no toca archivos con fecha futura', function () {
    Storage::disk('local')->put('drive/future.pdf', 'contenido');

    Archivo::factory()->create([
        'carpeta_id' => $this->carpeta->id,
        'path' => 'drive/future.pdf',
        'auto_eliminar_en' => now()->addWeek(),
    ]);

    $this->artisan('drive:limpiar-archivos')
        ->assertSuccessful();

    $this->assertDatabaseCount('drive_archivos', 1);
});
