<?php

use App\Models\Costos\Requisicion;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Storage::fake('public');
    Permission::firstOrCreate(['name' => 'costos.requisiciones.cotizar', 'guard_name' => 'web']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('costos.requisiciones.cotizar');
});

test('sube un PDF como documento de cotización', function () {
    $req = Requisicion::factory()->cotizada()->create();

    $this->actingAs($this->user)
        ->post("/admin/costos/requisiciones/{$req->id}/documentos", [
            'documento' => UploadedFile::fake()->create('cotizacion.pdf', 200, 'application/pdf'),
            'titulo' => 'Cotización proveedor X',
        ])
        ->assertRedirect();

    $media = $req->fresh()->media()->first();
    expect($media)->not->toBeNull()
        ->and($media->descripcion)->toBe('Cotización proveedor X');
    Storage::disk('public')->assertExists($media->path);
});

test('rechaza archivos que no son PDF', function () {
    $req = Requisicion::factory()->cotizada()->create();

    $this->actingAs($this->user)
        ->post("/admin/costos/requisiciones/{$req->id}/documentos", [
            'documento' => UploadedFile::fake()->image('foto.jpg'),
        ])
        ->assertSessionHasErrors('documento');

    expect($req->fresh()->media()->count())->toBe(0);
});

test('no permite subir documentos si la requisición ya no es editable', function () {
    $req = Requisicion::factory()->aprobada()->create();

    $this->actingAs($this->user)
        ->post("/admin/costos/requisiciones/{$req->id}/documentos", [
            'documento' => UploadedFile::fake()->create('cotizacion.pdf', 200, 'application/pdf'),
        ])
        ->assertStatus(422);
});

test('elimina un documento de cotización', function () {
    $req = Requisicion::factory()->cotizada()->create();
    $media = $req->media()->create([
        'descripcion' => 'doc',
        'nombre_original' => 'doc.pdf',
        'path' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')->store("costos/requisiciones/{$req->id}", 'public'),
        'mime' => 'application/pdf',
        'size' => 100,
    ]);

    $this->actingAs($this->user)
        ->delete("/admin/costos/requisiciones/{$req->id}/documentos/{$media->id}")
        ->assertRedirect();

    expect(Media::find($media->id))->toBeNull();
    Storage::disk('public')->assertMissing($media->path);
});

test('no elimina un documento de otra requisición', function () {
    $req = Requisicion::factory()->cotizada()->create();
    $otra = Requisicion::factory()->cotizada()->create();
    $media = $otra->media()->create([
        'descripcion' => 'ajeno',
        'nombre_original' => 'ajeno.pdf',
        'path' => 'costos/requisiciones/x/ajeno.pdf',
        'mime' => 'application/pdf',
        'size' => 100,
    ]);

    $this->actingAs($this->user)
        ->delete("/admin/costos/requisiciones/{$req->id}/documentos/{$media->id}")
        ->assertNotFound();

    expect(Media::find($media->id))->not->toBeNull();
});
