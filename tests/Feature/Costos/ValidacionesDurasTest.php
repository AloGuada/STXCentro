<?php

use App\Models\Costos\AprobacionSolicitud;
use App\Models\Costos\Factura;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\SolicitudPago;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create(['firma_path' => 'firmas/test.png']);
});

describe('motivo de rechazo obligatorio', function () {
    test('rechazo sin motivo falla', function () {
        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();
        $aprobacion = AprobacionSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'nivel' => 1,
            'aprobador_id' => $this->user->id,
            'estatus' => 'pendiente',
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.aprobaciones.rechazar', $aprobacion), [])
            ->assertSessionHasErrors(['observaciones']);

        expect($aprobacion->fresh()->estatus->value)->toBe('pendiente');
    });

    test('rechazo con motivo demasiado corto falla', function () {
        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();
        $aprobacion = AprobacionSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'nivel' => 1,
            'aprobador_id' => $this->user->id,
            'estatus' => 'pendiente',
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.aprobaciones.rechazar', $aprobacion), [
                'observaciones' => 'No',
            ])
            ->assertSessionHasErrors(['observaciones']);
    });

    test('rechazo valido persiste el motivo en motivo_rechazo y observaciones', function () {
        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();
        $aprobacion = AprobacionSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'nivel' => 1,
            'aprobador_id' => $this->user->id,
            'estatus' => 'pendiente',
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.aprobaciones.rechazar', $aprobacion), [
                'observaciones' => 'Faltan comprobantes de entrega',
            ])
            ->assertRedirect();

        $aprobacion->refresh();
        expect($aprobacion->motivo_rechazo)->toBe('Faltan comprobantes de entrega');
        expect($aprobacion->observaciones)->toBe('Faltan comprobantes de entrega');
    });
});

describe('cancelacion de orden de compra', function () {
    beforeEach(function () {
        Permission::firstOrCreate(['name' => 'costos.ordenes-compra.cancelar', 'guard_name' => 'web']);
        $this->user->givePermissionTo('costos.ordenes-compra.cancelar');
    });

    test('no se puede cancelar orden con factura activa', function () {
        $oc = OrdenCompra::factory()->pendienteEntrega()->create();
        Factura::factory()->create([
            'orden_compra_id' => $oc->id,
            'estatus' => 'pendiente_aprobacion',
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.ordenes-compra.cancelar', $oc), ['motivo' => 'Cancelación motivada por test'])
            ->assertSessionHasErrors(['estatus']);

        expect($oc->fresh()->estatus->value)->toBe('pendiente_entrega');
    });

    test('si todas las facturas estan canceladas la orden si se puede cancelar', function () {
        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 5000]);
        $oc = OrdenCompra::factory()->create(['total' => 5000, 'estatus' => 'pendiente_entrega']);
        OrdenCompraDetalle::factory()->create([
            'orden_compra_id' => $oc->id,
            'obra_rubro_id' => $obraRubro->id,
            'monto' => 5000,
        ]);
        Factura::factory()->create([
            'orden_compra_id' => $oc->id,
            'estatus' => 'cancelada',
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.ordenes-compra.cancelar', $oc), ['motivo' => 'Cancelación motivada por test'])
            ->assertRedirect();

        expect($oc->fresh()->estatus->value)->toBe('cancelada');
    });
});
