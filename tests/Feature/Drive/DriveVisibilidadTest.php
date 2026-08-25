<?php

use App\Models\Drive\Carpeta;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Storage::fake('local');

    $gestionar = Permission::firstOrCreate(['name' => 'drive.gestionar', 'guard_name' => 'web']);
    $propias = Permission::firstOrCreate(['name' => 'drive.propias', 'guard_name' => 'web']);

    $this->admin = Usuario::factory()->create();
    $this->admin->givePermissionTo($gestionar);

    $this->duenio = Usuario::factory()->create();
    $this->duenio->givePermissionTo($propias);

    $this->invitado = Usuario::factory()->create();
    $this->invitado->givePermissionTo($propias);

    $this->ajeno = Usuario::factory()->create();
    $this->ajeno->givePermissionTo($propias);

    $this->carpeta = Carpeta::factory()->create(['usuario_id' => $this->duenio->id]);
    $this->carpetaAjena = Carpeta::factory()->create(['usuario_id' => $this->ajeno->id]);
});

test('el usuario acotado solo lista sus carpetas', function () {
    $this->actingAs($this->duenio)
        ->get('/admin/drive/carpetas')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/drive/carpetas/index')
            ->where('esAdmin', false)
            ->has('carpetas.data', 1)
            ->where('carpetas.data.0.id', $this->carpeta->id));
});

test('el admin lista todas las carpetas', function () {
    $this->actingAs($this->admin)
        ->get('/admin/drive/carpetas')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('esAdmin', true)
            ->has('carpetas.data', 2));
});

test('el usuario acotado no entra a una carpeta ajena', function () {
    $this->actingAs($this->duenio)
        ->get("/admin/drive/carpetas/{$this->carpetaAjena->id}")
        ->assertForbidden();
});

test('el usuario sin permisos de drive no entra al modulo', function () {
    $sinPermisos = Usuario::factory()->create();

    $this->actingAs($sinPermisos)
        ->get('/admin/drive/carpetas')
        ->assertForbidden();
});

test('la carpeta compartida aparece en el listado del invitado', function () {
    $this->carpeta->usuarios()->attach($this->invitado->id, ['puede_escribir' => false]);

    $this->actingAs($this->invitado)
        ->get('/admin/drive/carpetas')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('carpetas.data', 1)
            ->where('carpetas.data.0.id', $this->carpeta->id));
});

test('el invitado de solo lectura ve la carpeta pero no puede subir', function () {
    $this->carpeta->usuarios()->attach($this->invitado->id, ['puede_escribir' => false]);

    $this->actingAs($this->invitado)
        ->get("/admin/drive/carpetas/{$this->carpeta->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('permisos.subir', false)
            ->where('permisos.editar', false)
            ->where('permisos.gestionar_accesos', false));

    $this->actingAs($this->invitado)
        ->post("/admin/drive/carpetas/{$this->carpeta->id}/archivos", [
            'archivo' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
        ])
        ->assertForbidden();
});

test('el invitado con escritura si puede subir archivos', function () {
    $this->carpeta->usuarios()->attach($this->invitado->id, ['puede_escribir' => true]);

    $this->actingAs($this->invitado)
        ->post("/admin/drive/carpetas/{$this->carpeta->id}/archivos", [
            'archivo' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('drive_archivos', [
        'carpeta_id' => $this->carpeta->id,
        'subido_por_id' => $this->invitado->id,
    ]);
});

test('el invitado con escritura no puede borrar ni renombrar la carpeta', function () {
    $this->carpeta->usuarios()->attach($this->invitado->id, ['puede_escribir' => true]);

    $this->actingAs($this->invitado)
        ->patch("/admin/drive/carpetas/{$this->carpeta->id}", ['nombre' => 'Otro nombre'])
        ->assertForbidden();

    $this->actingAs($this->invitado)
        ->delete("/admin/drive/carpetas/{$this->carpeta->id}")
        ->assertForbidden();
});

test('el creador comparte la carpeta con un interno', function () {
    $this->actingAs($this->duenio)
        ->post("/admin/drive/carpetas/{$this->carpeta->id}/accesos-internos", [
            'usuario_id' => $this->invitado->id,
            'puede_escribir' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('drive_carpeta_usuario', [
        'carpeta_id' => $this->carpeta->id,
        'usuario_id' => $this->invitado->id,
        'puede_escribir' => true,
    ]);
});

test('el permiso de escritura del interno se alterna y se revoca', function () {
    $this->carpeta->usuarios()->attach($this->invitado->id, ['puede_escribir' => false]);

    $this->actingAs($this->duenio)
        ->patch("/admin/drive/carpetas/{$this->carpeta->id}/accesos-internos/{$this->invitado->id}")
        ->assertRedirect();

    $this->assertDatabaseHas('drive_carpeta_usuario', [
        'carpeta_id' => $this->carpeta->id,
        'usuario_id' => $this->invitado->id,
        'puede_escribir' => true,
    ]);

    $this->actingAs($this->duenio)
        ->delete("/admin/drive/carpetas/{$this->carpeta->id}/accesos-internos/{$this->invitado->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('drive_carpeta_usuario', [
        'carpeta_id' => $this->carpeta->id,
        'usuario_id' => $this->invitado->id,
    ]);
});

test('el invitado no puede repartir la carpeta de otro', function () {
    $this->carpeta->usuarios()->attach($this->invitado->id, ['puede_escribir' => true]);
    $tercero = Usuario::factory()->create();

    $this->actingAs($this->invitado)
        ->post("/admin/drive/carpetas/{$this->carpeta->id}/accesos-internos", [
            'usuario_id' => $tercero->id,
        ])
        ->assertForbidden();
});

test('el admin administra la carpeta de cualquiera', function () {
    $this->actingAs($this->admin)
        ->get("/admin/drive/carpetas/{$this->carpeta->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('permisos.editar', true)
            ->where('permisos.subir', true)
            ->where('permisos.gestionar_accesos', true));
});

test('dar de alta cuentas externas sigue siendo solo del admin', function () {
    $this->actingAs($this->duenio)
        ->get('/admin/drive/externos')
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->get('/admin/drive/externos')
        ->assertOk();
});
