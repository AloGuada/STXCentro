<?php

use App\Models\Dg\Carpeta;
use App\Models\Dg\Reporte;
use App\Models\Dg\ReporteArchivo;
use App\Models\Usuario;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Storage::fake('local');

    Permission::firstOrCreate(['name' => 'dg.reportes.ver', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'dg.reportes.subir', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'dg.reportes.administrar', 'guard_name' => 'web']);

    $this->admin = Usuario::factory()->create();
    $this->admin->givePermissionTo(['dg.reportes.ver', 'dg.reportes.administrar']);
});

test('admin puede descargar archivo', function () {
    $carpeta = Carpeta::factory()->create();
    $reporte = Reporte::factory()->create(['carpeta_id' => $carpeta->id]);
    Storage::disk('local')->put('dg-reportes/test.pdf', 'contenido');
    $archivo = ReporteArchivo::factory()->create([
        'reporte_id' => $reporte->id,
        'path' => 'dg-reportes/test.pdf',
        'nombre_original' => 'test.pdf',
    ]);

    $this->actingAs($this->admin)
        ->get("/admin/dg/archivos/{$archivo->id}/descargar")
        ->assertOk();
});

test('usuario sin acceso a la carpeta no puede descargar', function () {
    $user = Usuario::factory()->create();
    $user->givePermissionTo('dg.reportes.ver');
    $carpeta = Carpeta::factory()->create();
    $reporte = Reporte::factory()->create(['carpeta_id' => $carpeta->id]);
    $archivo = ReporteArchivo::factory()->create(['reporte_id' => $reporte->id]);

    $this->actingAs($user)
        ->get("/admin/dg/archivos/{$archivo->id}/descargar")
        ->assertForbidden();
});

test('usuario con puede_escribir puede eliminar archivo de su carpeta', function () {
    $gerente = Usuario::factory()->create();
    $gerente->givePermissionTo(['dg.reportes.ver', 'dg.reportes.subir']);

    $carpeta = Carpeta::factory()->create();
    $carpeta->usuarios()->attach($gerente->id, ['puede_escribir' => true]);
    $reporte = Reporte::factory()->create(['carpeta_id' => $carpeta->id]);
    Storage::disk('local')->put('dg-reportes/test.pdf', 'contenido');
    $archivo = ReporteArchivo::factory()->create([
        'reporte_id' => $reporte->id,
        'path' => 'dg-reportes/test.pdf',
    ]);

    $this->actingAs($gerente)
        ->delete("/admin/dg/archivos/{$archivo->id}")
        ->assertRedirect();

    expect(ReporteArchivo::withTrashed()->find($archivo->id)->trashed())->toBeTrue();
});

test('admin puede agregar acceso a carpeta', function () {
    $carpeta = Carpeta::factory()->create();
    $user = Usuario::factory()->create();

    $this->actingAs($this->admin)
        ->post("/admin/dg/carpetas/{$carpeta->id}/accesos", [
            'usuario_id' => $user->id,
            'puede_escribir' => true,
        ])
        ->assertRedirect();

    expect((bool) $carpeta->usuarios()->where('usuario_id', $user->id)->first()?->pivot->puede_escribir)->toBeTrue();
});
