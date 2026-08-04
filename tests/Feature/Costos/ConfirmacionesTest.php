<?php

use App\Exports\Costos\ConfirmacionesExport;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\SolicitudPago;
use App\Models\User;
use App\Services\Costos\PuntosDeControl;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'costos.solicitudes.confirmar-costos',
        'costos.facturas.aprobar',
        'costos.facturas.aceptar-contabilidad',
    ] as $permiso) {
        Permission::firstOrCreate(['name' => $permiso]);
    }

    $this->costos = User::factory()->create();
    $this->costos->givePermissionTo(['costos.solicitudes.confirmar-costos', 'costos.facturas.aprobar']);

    $this->contabilidad = User::factory()->create();
    $this->contabilidad->givePermissionTo('costos.facturas.aceptar-contabilidad');

    $this->sinPermiso = User::factory()->create();
});

test('el usuario de costos ve solicitudes y facturas pendientes de costos', function () {
    SolicitudPago::factory()->aprobada()->create();
    Factura::factory()->pendienteAprobacion()->create();

    // Ruido: ya confirmada por costos → pasa a contabilidad, no debe salir en costos.
    SolicitudPago::factory()->aprobada()->create([
        'tipo_pago' => 'credito',
        'confirmada_costos' => true,
        'confirmada_costos_por' => $this->costos->id,
        'confirmada_costos_at' => now(),
    ]);

    $this->actingAs($this->costos)
        ->get(route('admin.costos.confirmaciones.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/costos/confirmaciones/index')
            ->has('costos', 2)
            ->has('contabilidad', 0)
        );
});

test('el usuario de contabilidad ve créditos confirmados por costos y facturas en pendiente de pago', function () {
    SolicitudPago::factory()->aprobada()->create([
        'tipo_pago' => 'credito',
        'confirmada_costos' => true,
        'confirmada_costos_por' => $this->costos->id,
        'confirmada_costos_at' => now(),
    ]);
    Factura::factory()->pendientePago()->create();

    // Ruido: aún no confirmada por costos → todavía no le toca a contabilidad.
    SolicitudPago::factory()->aprobada()->create(['tipo_pago' => 'credito']);

    $this->actingAs($this->contabilidad)
        ->get(route('admin.costos.confirmaciones.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('costos', 0)
            ->has('contabilidad', 2)
        );
});

test('un usuario sin permisos de control no ve nada', function () {
    SolicitudPago::factory()->aprobada()->create();
    Factura::factory()->pendienteAprobacion()->create();

    $this->actingAs($this->sinPermiso)
        ->get(route('admin.costos.confirmaciones.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('costos', 0)->has('contabilidad', 0));
});

test('la pantalla es accesible sin firma configurada', function () {
    expect($this->costos->firma_path)->toBeNull();

    $this->actingAs($this->costos)
        ->get(route('admin.costos.confirmaciones.index'))
        ->assertOk();
});

test('las facturas de OC de contado no aparecen en la bandeja', function () {
    $ocContado = OrdenCompra::factory()->create(['tipo_pago' => 'contado']);
    Factura::factory()->pendienteAprobacion()->create(['orden_compra_id' => $ocContado->id]);

    $this->actingAs($this->costos)
        ->get(route('admin.costos.confirmaciones.index'))
        ->assertInertia(fn (Assert $page) => $page->has('costos', 0));
});

test('el reporte descarga la bandeja de costos con las columnas de la tabla', function () {
    $sp = SolicitudPago::factory()->aprobada()->create(['tipo_moneda' => 'mxn', 'monto_total' => 1500]);

    $this->actingAs($this->costos)
        ->get(route('admin.costos.confirmaciones.exportar', ['paso' => 'costos']))
        ->assertOk()
        ->assertDownload('por-confirmar-costos-'.now()->format('Ymd').'.xlsx');

    $filas = (new ConfirmacionesExport(
        app(PuntosDeControl::class)->paraUsuario($this->costos)['costos'],
        'costos',
    ))->collection();

    expect($filas)->toHaveCount(1)
        ->and($filas->first())->toMatchArray([
            'tipo' => 'Solicitud de pago',
            'folio' => $sp->folio,
            'monto' => 1500.0,
            'moneda' => 'MXN',
        ]);
});

test('el reporte solo lleva lo que el usuario puede confirmar', function () {
    SolicitudPago::factory()->aprobada()->create();
    Factura::factory()->pendienteAprobacion()->create();

    $filas = (new ConfirmacionesExport(
        app(PuntosDeControl::class)->paraUsuario($this->sinPermiso)['costos'],
        'costos',
    ))->collection();

    expect($filas)->toHaveCount(0);
});

test('el reporte exige una bandeja válida', function () {
    $this->actingAs($this->costos)
        ->get(route('admin.costos.confirmaciones.exportar', ['paso' => 'otra']))
        ->assertSessionHasErrors('paso');
});

test('el badge cuenta solo lo que cada usuario puede confirmar', function () {
    SolicitudPago::factory()->aprobada()->create();
    Factura::factory()->pendienteAprobacion()->create();

    $servicio = app(PuntosDeControl::class);

    expect($servicio->contar($this->costos))->toBe(2);
    expect($servicio->contar($this->contabilidad))->toBe(0);
    expect($servicio->contar($this->sinPermiso))->toBe(0);
});
