<?php

use App\Models\Dg\Carpeta;
use App\Models\Dg\Reporte;
use App\Models\Dg\ReporteArchivo;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'dg.reportes.ver', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'dg.reportes.administrar', 'guard_name' => 'web']);

    $this->admin = Usuario::factory()->create();
    $this->admin->givePermissionTo(['dg.reportes.ver', 'dg.reportes.administrar']);
});

test('admin ve todas las carpetas del dashboard', function () {
    $hoy = CarbonImmutable::now();
    $anio = $hoy->isoWeekYear;
    $semana = $hoy->isoWeek;

    $carpetaConReporte = Carpeta::factory()->create(['nombre' => 'Planta']);
    Carpeta::factory()->create(['nombre' => 'Ventas']);

    $reporte = Reporte::factory()->create([
        'carpeta_id' => $carpetaConReporte->id,
        'anio' => $anio,
        'semana' => $semana,
    ]);
    ReporteArchivo::factory()->create(['reporte_id' => $reporte->id]);

    $this->actingAs($this->admin)
        ->get('/admin/dg')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/dg/dashboard')
            ->has('carpetas', 2)
            ->where('periodo.anio_actual', $anio)
            ->where('periodo.semana_actual', $semana)
        );
});

test('usuario sin admin solo ve sus carpetas asignadas', function () {
    $user = Usuario::factory()->create();
    $user->givePermissionTo('dg.reportes.ver');

    $carpetaAsignada = Carpeta::factory()->create(['nombre' => 'RRHH']);
    $carpetaAsignada->usuarios()->attach($user->id, ['puede_escribir' => false]);
    Carpeta::factory()->create(['nombre' => 'Ajena']);

    $this->actingAs($user)
        ->get('/admin/dg')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('carpetas', 1)
            ->where('carpetas.0.nombre', 'RRHH')
        );
});

test('vista de carpeta separa semana actual, anterior e historial', function () {
    $hoy = CarbonImmutable::now();
    $anioActual = $hoy->isoWeekYear;
    $semanaActual = $hoy->isoWeek;
    $anteriorFecha = $hoy->subWeek();
    $anioAnterior = $anteriorFecha->isoWeekYear;
    $semanaAnterior = $anteriorFecha->isoWeek;

    $carpeta = Carpeta::factory()->create();

    Reporte::factory()->create([
        'carpeta_id' => $carpeta->id,
        'anio' => $anioActual,
        'semana' => $semanaActual,
    ]);
    Reporte::factory()->create([
        'carpeta_id' => $carpeta->id,
        'anio' => $anioAnterior,
        'semana' => $semanaAnterior,
    ]);
    $historico = Reporte::factory()->create([
        'carpeta_id' => $carpeta->id,
        'anio' => 2025,
        'semana' => 10,
    ]);

    $this->actingAs($this->admin)
        ->get("/admin/dg/carpetas/{$carpeta->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/dg/carpetas/show')
            ->has('reporte_actual')
            ->has('reporte_anterior')
            ->has('historial.data', 1)
            ->where('historial.data.0.id', $historico->id)
            ->where('puede_administrar', true)
        );
});

test('usuario no asignado recibe 403 al intentar ver una carpeta', function () {
    $user = Usuario::factory()->create();
    $user->givePermissionTo('dg.reportes.ver');
    $carpeta = Carpeta::factory()->create();

    $this->actingAs($user)
        ->get("/admin/dg/carpetas/{$carpeta->id}")
        ->assertForbidden();
});
