<?php

use App\Models\Drive\Archivo;
use App\Models\Drive\Carpeta;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::disk('local')->put('drive/shared.pdf', 'contenido');

    $this->carpeta = Carpeta::factory()->create();
});

test('link publico permite descarga sin autenticacion', function () {
    $archivo = Archivo::factory()->create([
        'carpeta_id' => $this->carpeta->id,
        'path' => 'drive/shared.pdf',
        'link_token' => 'test-token-uuid',
        'link_expira_en' => now()->addDays(7),
    ]);

    $this->get('/drive/compartido/test-token-uuid')
        ->assertOk();
});

test('link publico expirado retorna 404', function () {
    $archivo = Archivo::factory()->create([
        'carpeta_id' => $this->carpeta->id,
        'path' => 'drive/shared.pdf',
        'link_token' => 'expired-token',
        'link_expira_en' => now()->subDay(),
    ]);

    $this->get('/drive/compartido/expired-token')
        ->assertNotFound();
});

test('token invalido retorna 404', function () {
    $this->get('/drive/compartido/non-existent-token')
        ->assertNotFound();
});

test('link sin fecha de expiracion funciona indefinidamente', function () {
    $archivo = Archivo::factory()->create([
        'carpeta_id' => $this->carpeta->id,
        'path' => 'drive/shared.pdf',
        'link_token' => 'no-expiry-token',
        'link_expira_en' => null,
    ]);

    $this->get('/drive/compartido/no-expiry-token')
        ->assertOk();
});
