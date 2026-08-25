<?php

use App\Models\Drive\Archivo;
use App\Models\Drive\Carpeta;
use App\Models\Drive\Externo;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Storage::fake('local');

    $permission = Permission::firstOrCreate(['name' => 'drive.gestionar', 'guard_name' => 'web']);

    $this->admin = Usuario::factory()->create();
    $this->admin->givePermissionTo($permission);
});

test('admin puede ver lista de carpetas', function () {
    Carpeta::factory()->count(3)->create();
    // Carpeta con creador: fuerza el eager-load de usuario (usuarios.name).
    Carpeta::factory()->create(['usuario_id' => $this->admin->id]);

    $this->actingAs($this->admin)
        ->get('/admin/drive/carpetas')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/drive/carpetas/index'));
});

test('admin puede crear carpeta', function () {
    $this->actingAs($this->admin)
        ->post('/admin/drive/carpetas', [
            'nombre' => 'Carpeta Test',
            'descripcion' => 'Descripción test',
        ])
        ->assertRedirect('/admin/drive/carpetas');

    $this->assertDatabaseHas('drive_carpetas', [
        'nombre' => 'Carpeta Test',
    ]);
});

test('admin puede ver detalle de carpeta', function () {
    $carpeta = Carpeta::factory()->create();

    $this->actingAs($this->admin)
        ->get("/admin/drive/carpetas/{$carpeta->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/drive/carpetas/show'));
});

test('admin puede eliminar carpeta y sus archivos', function () {
    $carpeta = Carpeta::factory()->create();
    Storage::disk('local')->put('drive/test.pdf', 'contenido');

    Archivo::factory()->create([
        'carpeta_id' => $carpeta->id,
        'path' => 'drive/test.pdf',
    ]);

    $this->actingAs($this->admin)
        ->delete("/admin/drive/carpetas/{$carpeta->id}")
        ->assertRedirect('/admin/drive/carpetas');

    $this->assertDatabaseMissing('drive_carpetas', ['id' => $carpeta->id]);
    Storage::disk('local')->assertMissing('drive/test.pdf');
});

test('admin puede toggle acceso de externo a carpeta', function () {
    $carpeta = Carpeta::factory()->create();
    $externo = Externo::factory()->create();

    // Agregar acceso
    $this->actingAs($this->admin)
        ->patch("/admin/drive/carpetas/{$carpeta->id}/acceso/{$externo->id}")
        ->assertRedirect();

    $this->assertDatabaseHas('drive_carpeta_accesos', [
        'carpeta_id' => $carpeta->id,
        'externo_id' => $externo->id,
    ]);

    // Quitar acceso
    $this->actingAs($this->admin)
        ->patch("/admin/drive/carpetas/{$carpeta->id}/acceso/{$externo->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('drive_carpeta_accesos', [
        'carpeta_id' => $carpeta->id,
        'externo_id' => $externo->id,
    ]);
});

test('admin puede subir archivo a carpeta', function () {
    $carpeta = Carpeta::factory()->create();
    $file = UploadedFile::fake()->create('documento.pdf', 1024, 'application/pdf');

    $this->actingAs($this->admin)
        ->post("/admin/drive/carpetas/{$carpeta->id}/archivos", [
            'archivo' => $file,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('drive_archivos', [
        'carpeta_id' => $carpeta->id,
        'subido_por_type' => 'interno',
        'subido_por_id' => $this->admin->id,
    ]);
});

test('subido_por_id es texto porque un interno se identifica con uuid', function () {
    expect(Schema::getColumnType('drive_archivos', 'subido_por_id'))->not->toContain('int');
});

test('admin puede generar link publico para archivo', function () {
    $carpeta = Carpeta::factory()->create();
    $archivo = Archivo::factory()->create(['carpeta_id' => $carpeta->id]);

    $this->actingAs($this->admin)
        ->post("/admin/drive/archivos/{$archivo->id}/generar-link", [
            'link_expira_en' => now()->addWeek()->format('Y-m-d H:i:s'),
        ])
        ->assertRedirect();

    $archivo->refresh();
    expect($archivo->link_token)->not->toBeNull();
    expect($archivo->link_expira_en)->not->toBeNull();
});

test('admin puede revocar link publico', function () {
    $carpeta = Carpeta::factory()->create();
    $archivo = Archivo::factory()->create([
        'carpeta_id' => $carpeta->id,
        'link_token' => 'test-token',
        'link_expira_en' => now()->addWeek(),
    ]);

    $this->actingAs($this->admin)
        ->delete("/admin/drive/archivos/{$archivo->id}/revocar-link")
        ->assertRedirect();

    $archivo->refresh();
    expect($archivo->link_token)->toBeNull();
});
