<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\SolicitudPago;
use App\Models\Costos\SolicitudPagoDetalle;
use App\Models\Costos\TipoSolicitud;
use App\Models\Departamento;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    darPermisosSolicitudesPago($this->user);
});

describe('admin costos solicitudes pago', function () {
    test('index page can be rendered', function () {
        SolicitudPago::factory()->count(3)->create(['solicitante_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.solicitudes-pago.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/solicitudes-pago/index')
            ->has('solicitudes.data', 3)
        );
    });

    test('index only shows solicitudes of the logged-in user', function () {
        SolicitudPago::factory()->count(2)->create(['solicitante_id' => $this->user->id]);
        SolicitudPago::factory()->count(3)->create(); // other users

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.solicitudes-pago.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('solicitudes.data', 2)
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.solicitudes-pago.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/solicitudes-pago/create')
            ->has('departamentos')
            ->has('proveedores')
            ->has('tipoSolicitudes')
        );
    });

    test('solicitud can be stored without detalles', function () {
        $departamento = Departamento::factory()->create();
        $tipoSolicitud = TipoSolicitud::factory()->create(['rubros' => false]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.store'), [
                'departamento_id' => $departamento->id,
                'tipo_solicitud_id' => $tipoSolicitud->id,
                'concepto' => 'Compra de materiales',
                'tipo_pago' => 'transferencia',
                'tipo_moneda' => 'mxn',
            ]);

        $solicitud = SolicitudPago::latest('id')->first();
        $response->assertRedirect(route('admin.costos.solicitudes-pago.show', $solicitud));
        $this->assertDatabaseHas('costos_solicitudes_pago', [
            'concepto' => 'Compra de materiales',
            'solicitante_id' => $this->user->id,
            'estatus' => 'pendiente_firma',
        ]);
    });

    test('solicitud can be stored with detalles', function () {
        $departamento = Departamento::factory()->create();
        $tipoSolicitud = TipoSolicitud::factory()->create(['rubros' => true]);
        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.store'), [
                'departamento_id' => $departamento->id,
                'tipo_solicitud_id' => $tipoSolicitud->id,
                'concepto' => 'Compra materiales',
                'tipo_pago' => 'cheque',
                'tipo_moneda' => 'usd',
                'detalles' => [
                    [
                        'obra_rubro_id' => $obraRubro->id,
                        'concepto' => 'Acero',
                        'cantidad' => 10,
                        'precio_unitario' => 150.50,
                    ],
                ],
            ]);

        $solicitudCreated = SolicitudPago::latest('id')->first();
        $response->assertRedirect(route('admin.costos.solicitudes-pago.show', $solicitudCreated));

        $solicitud = SolicitudPago::latest('id')->first();
        expect($solicitud->detalles)->toHaveCount(1);
        expect((float) $solicitud->monto_total)->toBe(1505.00);
    });

    test('folio is auto-generated', function () {
        $solicitud = SolicitudPago::factory()->create();

        expect($solicitud->folio)->toStartWith('SP-');
    });

    test('show page can be rendered', function () {
        $solicitud = SolicitudPago::factory()->aprobada()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.solicitudes-pago.show', $solicitud));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/solicitudes-pago/show')
            ->has('solicitud')
        );
    });

    test('edit page redirects to show for non-borrador', function () {
        $solicitud = SolicitudPago::factory()->aprobada()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.solicitudes-pago.edit', $solicitud));

        $response->assertRedirect(route('admin.costos.solicitudes-pago.show', $solicitud));
    });

    test('edit page can be rendered for borrador', function () {
        $solicitud = SolicitudPago::factory()->create(['estatus' => 'borrador']);

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.solicitudes-pago.edit', $solicitud));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/solicitudes-pago/edit')
        );
    });

    test('solicitud can be updated syncing detalles', function () {
        $solicitud = SolicitudPago::factory()->create(['estatus' => 'borrador']);
        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
        $detalle = SolicitudPagoDetalle::factory()->create([
            'solicitud_id' => $solicitud->id,
            'obra_rubro_id' => $obraRubro->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.costos.solicitudes-pago.update', $solicitud), [
                'departamento_id' => $solicitud->departamento_id,
                'tipo_solicitud_id' => $solicitud->tipo_solicitud_id,
                'concepto' => 'Updated concepto',
                'tipo_pago' => 'efectivo',
                'tipo_moneda' => 'eur',
                'detalles' => [
                    [
                        'id' => $detalle->id,
                        'obra_rubro_id' => $obraRubro->id,
                        'concepto' => 'Updated detalle',
                        'cantidad' => 5,
                        'precio_unitario' => 200,
                    ],
                ],
                '_version' => $solicitud->updated_at->toIso8601String(),
            ]);

        $response->assertRedirect(route('admin.costos.solicitudes-pago.index'));
        $this->assertDatabaseHas('costos_solicitudes_pago', [
            'id' => $solicitud->id,
            'concepto' => 'Updated concepto',
        ]);
        $this->assertDatabaseHas('costos_solicitudes_pago_detalle', [
            'id' => $detalle->id,
            'concepto' => 'Updated detalle',
        ]);
    });

    test('non-borrador solicitud cannot be updated', function () {
        $solicitud = SolicitudPago::factory()->aprobada()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.costos.solicitudes-pago.update', $solicitud), [
                'departamento_id' => $solicitud->departamento_id,
                'tipo_solicitud_id' => $solicitud->tipo_solicitud_id,
                'concepto' => 'Should not update',
                'tipo_pago' => 'efectivo',
                'tipo_moneda' => 'mxn',
            ]);

        $response->assertSessionHasErrors(['estatus']);
    });

    test('only borrador can be deleted', function () {
        $solicitud = SolicitudPago::factory()->create(['estatus' => 'borrador']);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.costos.solicitudes-pago.destroy', $solicitud));

        $response->assertRedirect(route('admin.costos.solicitudes-pago.index'));
        $this->assertDatabaseMissing('costos_solicitudes_pago', ['id' => $solicitud->id]);
    });

    test('validation requires required fields', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.store'), []);

        $response->assertSessionHasErrors(['departamento_id', 'tipo_solicitud_id', 'concepto', 'tipo_pago', 'tipo_moneda']);
    });
});
