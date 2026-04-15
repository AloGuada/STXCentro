<?php

use App\Models\Dg\Carpeta;
use App\Models\Dg\Reporte;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Storage::fake('local');

    Permission::firstOrCreate(['name' => 'dg.reportes.ver', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'dg.reportes.administrar', 'guard_name' => 'web']);

    // Usuario sin roles ni permisos — solo tiene acceso via pivote
    $this->gerente = Usuario::factory()->create();

    $this->carpeta = Carpeta::factory()->create();
    $this->carpeta->usuarios()->attach($this->gerente->id, ['puede_escribir' => true]);
});

test('usuario con permiso escribir puede subir reporte a su carpeta', function () {
    $file = UploadedFile::fake()->create('reporte.pdf', 100, 'application/pdf');

    $response = $this->actingAs($this->gerente)
        ->post('/admin/dg/reportes', [
            'carpeta_id' => $this->carpeta->id,
            'anio' => 2026,
            'semana' => 15,
            'archivos' => [$file],
        ]);

    $response->assertRedirect("/admin/dg/carpetas/{$this->carpeta->id}");

    $reporte = Reporte::query()
        ->where('carpeta_id', $this->carpeta->id)
        ->where('anio', 2026)
        ->where('semana', 15)
        ->first();

    expect($reporte)->not->toBeNull();
    expect($reporte->archivos)->toHaveCount(1);
});

test('usuario sin permiso escribir no puede subir a una carpeta ajena', function () {
    $otraCarpeta = Carpeta::factory()->create();

    $file = UploadedFile::fake()->create('reporte.pdf', 100, 'application/pdf');

    $this->actingAs($this->gerente)
        ->post('/admin/dg/reportes', [
            'carpeta_id' => $otraCarpeta->id,
            'anio' => 2026,
            'semana' => 15,
            'archivos' => [$file],
        ])
        ->assertForbidden();

    expect(Reporte::query()->where('carpeta_id', $otraCarpeta->id)->exists())->toBeFalse();
});

test('subir reporte en semana existente agrega archivos en vez de duplicar', function () {
    $this->actingAs($this->gerente)
        ->post('/admin/dg/reportes', [
            'carpeta_id' => $this->carpeta->id,
            'anio' => 2026,
            'semana' => 15,
            'archivos' => [UploadedFile::fake()->create('a.pdf', 50, 'application/pdf')],
        ])->assertRedirect();

    $this->actingAs($this->gerente)
        ->post('/admin/dg/reportes', [
            'carpeta_id' => $this->carpeta->id,
            'anio' => 2026,
            'semana' => 15,
            'archivos' => [UploadedFile::fake()->create('b.pdf', 50, 'application/pdf')],
        ])->assertRedirect();

    $reportes = Reporte::query()->where('carpeta_id', $this->carpeta->id)->get();
    expect($reportes)->toHaveCount(1);
    expect($reportes->first()->archivos)->toHaveCount(2);
});

test('usuario sin permiso no puede acceder al dashboard', function () {
    $usuario = Usuario::factory()->create();

    $this->actingAs($usuario)
        ->get('/admin/dg')
        ->assertForbidden();
});
