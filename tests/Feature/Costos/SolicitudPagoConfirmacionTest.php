<?php

use App\Models\Costos\SolicitudPago;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->adminCostos = User::factory()->create();
    Permission::firstOrCreate(['name' => 'costos.solicitudes.confirmar-costos']);
    $this->adminCostos->givePermissionTo('costos.solicitudes.confirmar-costos');

    $this->contabilidad = User::factory()->create();
    Permission::firstOrCreate(['name' => 'costos.facturas.aceptar-contabilidad']);
    $this->contabilidad->givePermissionTo('costos.facturas.aceptar-contabilidad');

    $this->userSinPermiso = User::factory()->create();
});

describe('confirmación costos - contado', function () {
    test('admin_costos puede confirmar solicitud aprobada de contado y crea pago programado', function () {
        $solicitud = SolicitudPago::factory()->aprobada()->create([
            'tipo_pago' => 'transferencia',
            'fecha_pago_solicitada' => '2026-05-02',
        ]);

        $response = $this->actingAs($this->adminCostos)
            ->post(route('admin.costos.solicitudes-pago.confirmar-costos', $solicitud));

        $response->assertRedirect();

        $solicitud->refresh();
        expect($solicitud->confirmada_costos)->toBeTrue();
        expect($solicitud->confirmada_costos_por)->toBe($this->adminCostos->id);
        expect($solicitud->confirmada_costos_at)->not->toBeNull();

        $pago = $solicitud->pago;
        expect($pago)->not->toBeNull();
        expect($pago->estatus)->toBe('programado');
        expect($pago->tipo_pago)->toBe('contado');
        expect($pago->fecha_pago_programada->format('Y-m-d'))->toBe('2026-05-02');
        expect((float) $pago->monto_pago)->toBe((float) $solicitud->monto_total);
    });

    test('pago creado hereda moneda de la solicitud', function () {
        $solicitud = SolicitudPago::factory()->aprobada()->create([
            'tipo_pago' => 'cheque',
            'tipo_moneda' => 'usd',
            'fecha_pago_solicitada' => '2026-05-09',
        ]);

        $this->actingAs($this->adminCostos)
            ->post(route('admin.costos.solicitudes-pago.confirmar-costos', $solicitud));

        $pago = $solicitud->pago;
        expect($pago->moneda)->toBe('usd');
    });
});

describe('confirmación costos - crédito', function () {
    test('admin_costos confirma crédito pero NO crea pago aún', function () {
        $solicitud = SolicitudPago::factory()->aprobada()->create([
            'tipo_pago' => 'credito',
            'fecha_pago_solicitada' => '2026-05-02',
        ]);

        $this->actingAs($this->adminCostos)
            ->post(route('admin.costos.solicitudes-pago.confirmar-costos', $solicitud))
            ->assertRedirect();

        $solicitud->refresh();
        expect($solicitud->confirmada_costos)->toBeTrue();
        expect($solicitud->pago)->toBeNull();
    });
});

describe('confirmación contabilidad', function () {
    test('contabilidad puede confirmar solicitud de crédito después de costos y crea pago', function () {
        $solicitud = SolicitudPago::factory()->aprobada()->create([
            'tipo_pago' => 'credito',
            'fecha_pago_solicitada' => '2026-05-02',
            'confirmada_costos' => true,
            'confirmada_costos_por' => $this->adminCostos->id,
            'confirmada_costos_at' => now(),
        ]);

        $response = $this->actingAs($this->contabilidad)
            ->post(route('admin.costos.solicitudes-pago.confirmar-contabilidad', $solicitud));

        $response->assertRedirect();

        $solicitud->refresh();
        expect($solicitud->confirmada_contabilidad)->toBeTrue();
        expect($solicitud->confirmada_contabilidad_por)->toBe($this->contabilidad->id);

        $pago = $solicitud->pago;
        expect($pago)->not->toBeNull();
        expect($pago->estatus)->toBe('programado');
        expect($pago->tipo_pago)->toBe('credito');
        expect($pago->fecha_pago_programada->format('Y-m-d'))->toBe('2026-05-02');
    });

    test('contabilidad NO puede confirmar sin confirmación de costos', function () {
        $solicitud = SolicitudPago::factory()->aprobada()->create([
            'tipo_pago' => 'credito',
            'confirmada_costos' => false,
        ]);

        $response = $this->actingAs($this->contabilidad)
            ->post(route('admin.costos.solicitudes-pago.confirmar-contabilidad', $solicitud));

        $response->assertSessionHasErrors(['confirmada_costos']);
    });

    test('contabilidad NO puede confirmar solicitud ya confirmada', function () {
        $solicitud = SolicitudPago::factory()->aprobada()->create([
            'tipo_pago' => 'credito',
            'confirmada_costos' => true,
            'confirmada_costos_por' => $this->adminCostos->id,
            'confirmada_costos_at' => now(),
            'confirmada_contabilidad' => true,
            'confirmada_contabilidad_por' => $this->contabilidad->id,
            'confirmada_contabilidad_at' => now(),
        ]);

        $response = $this->actingAs($this->contabilidad)
            ->post(route('admin.costos.solicitudes-pago.confirmar-contabilidad', $solicitud));

        $response->assertSessionHasErrors(['confirmada_contabilidad']);
    });
});

describe('validaciones de confirmación', function () {
    test('no se puede confirmar solicitud que no esté aprobada', function () {
        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();

        $response = $this->actingAs($this->adminCostos)
            ->post(route('admin.costos.solicitudes-pago.confirmar-costos', $solicitud));

        $response->assertSessionHasErrors(['estatus']);
    });

    test('no se puede confirmar costos dos veces', function () {
        $solicitud = SolicitudPago::factory()->aprobada()->create([
            'tipo_pago' => 'transferencia',
            'confirmada_costos' => true,
            'confirmada_costos_por' => $this->adminCostos->id,
            'confirmada_costos_at' => now(),
        ]);

        $response = $this->actingAs($this->adminCostos)
            ->post(route('admin.costos.solicitudes-pago.confirmar-costos', $solicitud));

        $response->assertSessionHasErrors(['confirmada_costos']);
    });

    test('usuario sin permiso no puede confirmar costos', function () {
        $solicitud = SolicitudPago::factory()->aprobada()->create();

        $response = $this->actingAs($this->userSinPermiso)
            ->post(route('admin.costos.solicitudes-pago.confirmar-costos', $solicitud));

        $response->assertForbidden();
    });

    test('usuario sin permiso no puede confirmar contabilidad', function () {
        $solicitud = SolicitudPago::factory()->aprobada()->create([
            'tipo_pago' => 'credito',
            'confirmada_costos' => true,
        ]);

        $response = $this->actingAs($this->userSinPermiso)
            ->post(route('admin.costos.solicitudes-pago.confirmar-contabilidad', $solicitud));

        $response->assertForbidden();
    });
});
