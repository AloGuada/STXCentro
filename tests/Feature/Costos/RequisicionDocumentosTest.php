<?php

use App\Models\Costos\Requisicion;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Storage::fake('public');
    Permission::firstOrCreate(['name' => 'costos.requisiciones.cotizar', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'costos.requisiciones.ver', 'guard_name' => 'web']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('costos.requisiciones.cotizar');
});

test('sube un PDF como documento de cotización', function () {
    $req = Requisicion::factory()->create();

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
    $req = Requisicion::factory()->create();

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
    $req = Requisicion::factory()->create();
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
    $req = Requisicion::factory()->create();
    $otra = Requisicion::factory()->create();
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

test('el solicitante sin permiso de cotizar recibe los documentos en el resumen', function () {
    $solicitante = User::factory()->create();
    $solicitante->givePermissionTo('costos.requisiciones.ver');
    $req = Requisicion::factory()->create(['solicitante_id' => $solicitante->id]);
    $req->media()->create([
        'descripcion' => 'Cotización proveedor X',
        'nombre_original' => 'cotizacion.pdf',
        'path' => "costos/requisiciones/{$req->id}/cotizacion.pdf",
        'mime' => 'application/pdf',
        'size' => 100,
    ]);

    expect($solicitante->can('costos.requisiciones.cotizar'))->toBeFalse();

    $this->actingAs($solicitante)
        ->get("/admin/costos/requisiciones/{$req->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('requisicion.media', 1)
            ->where('requisicion.media.0.descripcion', 'Cotización proveedor X')
        );
});
