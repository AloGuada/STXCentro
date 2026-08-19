<?php

use App\Exports\Costos\SolicitudesRequisicionesExport;
use App\Models\Costos\Requisicion;
use App\Models\Costos\SolicitudPago;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['costos.solicitudes-pago.ver', 'costos.solicitudes-pago.ver-todas'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
});

test('el reporte descarga un PDF', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['costos.solicitudes-pago.ver', 'costos.solicitudes-pago.ver-todas']);

    SolicitudPago::factory()->count(2)->create();
    Requisicion::factory()->count(3)->create();

    $this->actingAs($user)
        ->get(route('admin.costos.solicitudes-pago.reporte-pdf'))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('sin permiso ver no puede generar el reporte', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.costos.solicitudes-pago.reporte-pdf'))
        ->assertForbidden();
});

test('sin ver-todas el reporte solo considera lo propio', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('costos.solicitudes-pago.ver');

    SolicitudPago::factory()->create(['solicitante_id' => $user->id]);
    SolicitudPago::factory()->create(); // de otro solicitante

    // No revienta y responde PDF aun filtrando por solicitante.
    $this->actingAs($user)
        ->get(route('admin.costos.solicitudes-pago.reporte-pdf'))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('el reporte tambien descarga un Excel', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['costos.solicitudes-pago.ver', 'costos.solicitudes-pago.ver-todas']);

    SolicitudPago::factory()->count(2)->create();
    Requisicion::factory()->count(3)->create();

    $this->actingAs($user)
        ->get(route('admin.costos.solicitudes-pago.reporte-excel'))
        ->assertOk()
        ->assertDownload('reporte-solicitudes-requisiciones-'.now()->format('Y-m-d').'.xlsx');
});

test('sin permiso ver no puede descargar el Excel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.costos.solicitudes-pago.reporte-excel'))
        ->assertForbidden();
});

test('las hojas del Excel traen la misma data que el PDF', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('costos.solicitudes-pago.ver');

    $propia = SolicitudPago::factory()->create([
        'solicitante_id' => $user->id,
        'concepto' => 'Material de obra',
        'monto_total' => 1234.56,
    ]);
    SolicitudPago::factory()->create(); // de otro solicitante: no debe salir
    $req = Requisicion::factory()->create(['solicitante_id' => $user->id]);
    Requisicion::factory()->create(); // de otro solicitante: no debe salir

    $this->actingAs($user);

    $hojas = (new SolicitudesRequisicionesExport(
        SolicitudPago::whereKey($propia->id)->with(['departamento', 'proveedor', 'solicitante'])->get(),
        Requisicion::whereKey($req->id)->with(['departamento', 'solicitante'])->get(),
    ))->sheets();

    $solicitudes = $hojas[0]->collection();
    expect($solicitudes)->toHaveCount(1);
    expect($solicitudes->first()['folio'])->toBe($propia->folio);
    expect($solicitudes->first()['concepto'])->toBe('Material de obra');
    expect($solicitudes->first()['total'])->toBe(1234.56);

    $requisiciones = $hojas[1]->collection();
    expect($requisiciones)->toHaveCount(1);
    expect($requisiciones->first()['folio'])->toBe($req->folio);
});

test('el Excel respeta la visibilidad: sin ver-todas solo trae lo propio', function () {
    Excel::fake();

    $user = User::factory()->create();
    $user->givePermissionTo('costos.solicitudes-pago.ver');

    $propia = SolicitudPago::factory()->create(['solicitante_id' => $user->id]);
    SolicitudPago::factory()->create(); // de otro solicitante

    $this->actingAs($user)
        ->get(route('admin.costos.solicitudes-pago.reporte-excel'))
        ->assertOk();

    Excel::assertDownloaded(
        'reporte-solicitudes-requisiciones-'.now()->format('Y-m-d').'.xlsx',
        function (SolicitudesRequisicionesExport $export) use ($propia) {
            $filas = $export->sheets()[0]->collection();

            return $filas->count() === 1 && $filas->first()['folio'] === $propia->folio;
        }
    );
});
