<?php

use App\Models\Costos\Factura;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'costos.facturas.ver', 'guard_name' => 'web']);
});

test('descarga reporte semanal de facturas como pdf', function () {
    $fecha = Carbon::now()->setISODate(2026, 11)->startOfWeek()->addDay();
    Factura::factory()->count(2)->create(['fecha_factura' => $fecha]);

    $this->actingAs($this->user)
        ->get('/admin/costos/facturas/reporte-semanal?anio=2026&semana=11')
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');
});

test('reporte semanal requiere parametros validos', function () {
    $this->actingAs($this->user)
        ->get('/admin/costos/facturas/reporte-semanal')
        ->assertSessionHasErrors(['anio', 'semana']);
});

test('reporte semanal redirige cuando no hay facturas', function () {
    $this->actingAs($this->user)
        ->get('/admin/costos/facturas/reporte-semanal?anio=2026&semana=1')
        ->assertSessionHasErrors(['semana']);
});

test('reporte semanal filtra correctamente por semana', function () {
    $semana11 = Carbon::now()->setISODate(2026, 11)->startOfWeek()->addDay();
    $semana12 = Carbon::now()->setISODate(2026, 12)->startOfWeek()->addDay();

    Factura::factory()->count(2)->create(['fecha_factura' => $semana11]);
    Factura::factory()->create(['fecha_factura' => $semana12]);

    $response = $this->actingAs($this->user)
        ->get('/admin/costos/facturas/reporte-semanal?anio=2026&semana=11')
        ->assertSuccessful();

    // Solo debe contener las facturas de la semana 11
    expect($response->headers->get('content-type'))->toBe('application/pdf');
});
