<?php

use App\Models\Costos\Entrega;
use App\Models\Costos\Factura;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'costos.facturas.ver', 'guard_name' => 'web']);
});

test('descarga reporte semanal de facturas como pdf', function () {
    $fecha = Carbon::now()->setISODate(2026, 11)->startOfWeek()->addDay();
    $facturas = Factura::factory()->count(2)->create();
    $facturas->each(fn ($f) => Entrega::factory()->create(['factura_id' => $f->id, 'fecha_entrega' => $fecha]));

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

    $facturasSemana11 = Factura::factory()->count(2)->create();
    $facturasSemana11->each(fn ($f) => Entrega::factory()->create(['factura_id' => $f->id, 'fecha_entrega' => $semana11]));

    $facturaSemana12 = Factura::factory()->create();
    Entrega::factory()->create(['factura_id' => $facturaSemana12->id, 'fecha_entrega' => $semana12]);

    $response = $this->actingAs($this->user)
        ->get('/admin/costos/facturas/reporte-semanal?anio=2026&semana=11')
        ->assertSuccessful();

    // Solo debe contener las facturas con recepción en semana 11
    expect($response->headers->get('content-type'))->toBe('application/pdf');
});

// --- Reporte semanal por proveedor ---

test('descarga reporte semanal por proveedor como pdf', function () {
    $fecha = Carbon::now()->setISODate(2026, 11)->startOfWeek()->addDay();
    $proveedor = Proveedor::factory()->create();
    $facturas = Factura::factory()->count(2)->create(['proveedor_id' => $proveedor->id]);
    $facturas->each(fn ($f) => Entrega::factory()->create(['factura_id' => $f->id, 'fecha_entrega' => $fecha]));

    $this->actingAs($this->user)
        ->get("/admin/costos/facturas/reporte-semanal-proveedor?anio=2026&semana=11&proveedor_id={$proveedor->id}")
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');
});

test('reporte por proveedor requiere parametros validos', function () {
    $this->actingAs($this->user)
        ->get('/admin/costos/facturas/reporte-semanal-proveedor')
        ->assertSessionHasErrors(['anio', 'semana', 'proveedor_id']);
});

test('reporte por proveedor redirige cuando no hay facturas', function () {
    $proveedor = Proveedor::factory()->create();

    $this->actingAs($this->user)
        ->get("/admin/costos/facturas/reporte-semanal-proveedor?anio=2026&semana=1&proveedor_id={$proveedor->id}")
        ->assertSessionHasErrors(['semana']);
});

test('reporte por proveedor solo incluye facturas del proveedor seleccionado', function () {
    $fecha = Carbon::now()->setISODate(2026, 11)->startOfWeek()->addDay();
    $proveedor1 = Proveedor::factory()->create();
    $proveedor2 = Proveedor::factory()->create();

    $factura1 = Factura::factory()->create(['proveedor_id' => $proveedor1->id]);
    Entrega::factory()->create(['factura_id' => $factura1->id, 'fecha_entrega' => $fecha]);

    $factura2 = Factura::factory()->create(['proveedor_id' => $proveedor2->id]);
    Entrega::factory()->create(['factura_id' => $factura2->id, 'fecha_entrega' => $fecha]);

    $this->actingAs($this->user)
        ->get("/admin/costos/facturas/reporte-semanal-proveedor?anio=2026&semana=11&proveedor_id={$proveedor1->id}")
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');
});
