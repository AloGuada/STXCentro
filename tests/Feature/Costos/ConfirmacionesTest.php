<?php

use App\Exports\Costos\ConfirmacionesExport;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\SolicitudPago;
use App\Models\Proveedor;
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

test('la fecha de pago viaja como fecha de calendario, sin hora ni zona', function () {
    SolicitudPago::factory()->aprobada()->create(['fecha_pago_solicitada' => '2026-09-18']);

    $this->actingAs($this->costos)
        ->get(route('admin.costos.confirmaciones.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('costos.0.fecha', '2026-09-18')
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

test('la fila trae razón social y nombre comercial por separado', function () {
    $proveedor = Proveedor::factory()->create([
        'razon_social' => 'ACEROS DEL NORTE SA DE CV',
        'nombre_comercial' => 'Aceros Norte',
    ]);
    SolicitudPago::factory()->aprobada()->create(['proveedor_id' => $proveedor->id]);

    $fila = app(PuntosDeControl::class)->paraUsuario($this->costos)['costos']->first();

    expect($fila['proveedor'])->toBe('ACEROS DEL NORTE SA DE CV');
    expect($fila['proveedor_comercial'])->toBe('Aceros Norte');
});

test('el filtro ignora mayúsculas y acentos', function () {
    $conAcento = Proveedor::factory()->create(['razon_social' => 'CAÑÓN Y ASOCIADOS', 'nombre_comercial' => null]);
    $otro = Proveedor::factory()->create(['razon_social' => 'TORNILLOS DEL BAJIO', 'nombre_comercial' => null]);
    SolicitudPago::factory()->aprobada()->create(['proveedor_id' => $conAcento->id]);
    SolicitudPago::factory()->aprobada()->create(['proveedor_id' => $otro->id]);

    $servicio = app(PuntosDeControl::class);
    $filas = $servicio->paraUsuario($this->costos)['costos'];

    expect($servicio->filtrar($filas, 'canon')->pluck('proveedor')->all())->toBe(['CAÑÓN Y ASOCIADOS']);
    expect($servicio->filtrar($filas, 'CAÑÓN')->pluck('proveedor')->all())->toBe(['CAÑÓN Y ASOCIADOS']);
    expect($servicio->filtrar($filas, 'bajio')->pluck('proveedor')->all())->toBe(['TORNILLOS DEL BAJIO']);
    expect($servicio->filtrar($filas, '')->all())->toHaveCount(2);
});

test('el filtro busca por nombre comercial, folio y monto', function () {
    $proveedor = Proveedor::factory()->create([
        'razon_social' => 'DISTRIBUIDORA XYZ SA',
        'nombre_comercial' => 'Ferretera Lopez',
    ]);
    $sp = SolicitudPago::factory()->aprobada()->create([
        'proveedor_id' => $proveedor->id,
        'monto_total' => 1500,
        'tipo_moneda' => 'mxn',
    ]);
    SolicitudPago::factory()->aprobada()->create(['monto_total' => 99]);

    $servicio = app(PuntosDeControl::class);
    $filas = $servicio->paraUsuario($this->costos)['costos'];

    expect($servicio->filtrar($filas, 'ferretera')->pluck('folio')->all())->toBe([$sp->folio]);
    expect($servicio->filtrar($filas, $sp->folio)->pluck('folio')->all())->toBe([$sp->folio]);
    // El monto se encuentra con y sin separador de miles.
    expect($servicio->filtrar($filas, '1500')->pluck('folio')->all())->toBe([$sp->folio]);
    expect($servicio->filtrar($filas, '1,500.00')->pluck('folio')->all())->toBe([$sp->folio]);
});

test('el reporte respeta el filtro de la pantalla', function () {
    $proveedor = Proveedor::factory()->create(['razon_social' => 'VIGAS Y PERFILES SA', 'nombre_comercial' => null]);
    $sp = SolicitudPago::factory()->aprobada()->create(['proveedor_id' => $proveedor->id]);
    SolicitudPago::factory()->aprobada()->create();

    $this->actingAs($this->costos)
        ->get(route('admin.costos.confirmaciones.exportar', ['paso' => 'costos', 'q' => 'vigas']))
        ->assertOk();

    $servicio = app(PuntosDeControl::class);
    $filas = (new ConfirmacionesExport(
        $servicio->filtrar($servicio->paraUsuario($this->costos)['costos'], 'vigas'),
        'costos',
    ))->collection();

    expect($filas)->toHaveCount(1);
    expect($filas->first()['folio'])->toBe($sp->folio);
});
