<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * «Mi firma» es de cualquier usuario: la rúbrica la estampan Costos y los
 * formatos de Calidad, así que no puede depender de ser aprobador de Costos.
 */
beforeEach(function () {
    Storage::fake('public');
    $this->pngDataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
});

test('cualquier usuario abre su firma sin permisos de ningun modulo', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.firma.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/firma/edit')->where('firmaUrl', null));
});

test('guarda la rubrica dibujada y reemplaza la anterior', function () {
    Storage::disk('public')->put('firmas/vieja.png', 'vieja');
    $usuario = User::factory()->create(['firma_path' => 'firmas/vieja.png']);

    $this->actingAs($usuario)
        ->post(route('admin.firma.update'), ['firma' => $this->pngDataUrl])
        ->assertRedirect();

    $usuario->refresh();
    Storage::disk('public')->assertMissing('firmas/vieja.png');
    Storage::disk('public')->assertExists($usuario->firma_path);
});

test('borra la rubrica', function () {
    Storage::disk('public')->put('firmas/actual.png', 'png');
    $usuario = User::factory()->create(['firma_path' => 'firmas/actual.png']);

    $this->actingAs($usuario)->delete(route('admin.firma.destroy'))->assertRedirect();

    expect($usuario->fresh()->firma_path)->toBeNull();
    Storage::disk('public')->assertMissing('firmas/actual.png');
});
