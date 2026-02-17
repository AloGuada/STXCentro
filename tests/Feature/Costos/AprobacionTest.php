<?php

use App\Models\Costos\AprobacionSolicitud;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\SolicitudPago;
use App\Models\Costos\SolicitudPagoDetalle;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin costos aprobaciones', function () {
    test('index page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.aprobaciones.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/aprobaciones/index')
            ->has('pendientes')
            ->has('aprobadas')
            ->has('rechazadas')
        );
    });

    test('index shows pending aprobaciones for current user', function () {
        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();
        AprobacionSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'nivel' => 1,
            'aprobador_id' => $this->user->id,
            'estatus' => 'pendiente',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.aprobaciones.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('pendientes', 1)
        );
    });

    test('pending aprobacion not shown if lower level still pending', function () {
        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();
        $otherUser = User::factory()->create();

        AprobacionSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'nivel' => 1,
            'aprobador_id' => $otherUser->id,
            'estatus' => 'pendiente',
        ]);
        AprobacionSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'nivel' => 2,
            'aprobador_id' => $this->user->id,
            'estatus' => 'pendiente',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.aprobaciones.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('pendientes', 0)
        );
    });

    test('aprobacion can be approved', function () {
        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();
        $aprobacion = AprobacionSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'nivel' => 1,
            'aprobador_id' => $this->user->id,
            'estatus' => 'pendiente',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.aprobaciones.aprobar', $aprobacion), [
                'observaciones' => 'Todo en orden',
            ]);

        $response->assertRedirect();

        $aprobacion->refresh();
        expect($aprobacion->estatus)->toBe('aprobada');
        expect($aprobacion->fecha_respuesta)->not->toBeNull();
        expect($aprobacion->observaciones)->toBe('Todo en orden');
    });

    test('last level approval changes solicitud to aprobada and applies budget', function () {
        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();
        SolicitudPagoDetalle::factory()->create([
            'solicitud_id' => $solicitud->id,
            'obra_rubro_id' => $obraRubro->id,
            'subtotal' => 3000,
        ]);

        $aprobacion = AprobacionSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'nivel' => 1,
            'aprobador_id' => $this->user->id,
            'estatus' => 'pendiente',
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.aprobaciones.aprobar', $aprobacion));

        $solicitud->refresh();
        expect($solicitud->estatus)->toBe('aprobada');

        $obraRubro->refresh();
        expect((float) $obraRubro->acumulado)->toBe(3000.00);
    });

    test('aprobacion can be rejected', function () {
        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();
        $aprobacion = AprobacionSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'nivel' => 1,
            'aprobador_id' => $this->user->id,
            'estatus' => 'pendiente',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.aprobaciones.rechazar', $aprobacion), [
                'observaciones' => 'No cumple requisitos',
            ]);

        $response->assertRedirect();

        $aprobacion->refresh();
        expect($aprobacion->estatus)->toBe('rechazada');
        expect($aprobacion->observaciones)->toBe('No cumple requisitos');

        $solicitud->refresh();
        expect($solicitud->estatus)->toBe('cancelada');
    });

    test('rejection cancels remaining pending aprobaciones', function () {
        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();
        $aprobacion1 = AprobacionSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'nivel' => 1,
            'aprobador_id' => $this->user->id,
            'estatus' => 'pendiente',
        ]);
        $aprobacion2 = AprobacionSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'nivel' => 2,
            'aprobador_id' => User::factory()->create()->id,
            'estatus' => 'pendiente',
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.aprobaciones.rechazar', $aprobacion1), [
                'observaciones' => 'Rechazado',
            ]);

        $aprobacion2->refresh();
        expect($aprobacion2->estatus)->toBe('cancelada');
    });

    test('rejection requires observaciones', function () {
        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();
        $aprobacion = AprobacionSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'nivel' => 1,
            'aprobador_id' => $this->user->id,
            'estatus' => 'pendiente',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.aprobaciones.rechazar', $aprobacion), []);

        $response->assertSessionHasErrors(['observaciones']);
    });

    test('cannot approve another users aprobacion', function () {
        $otherUser = User::factory()->create();
        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();
        $aprobacion = AprobacionSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'nivel' => 1,
            'aprobador_id' => $otherUser->id,
            'estatus' => 'pendiente',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.aprobaciones.aprobar', $aprobacion));

        $response->assertForbidden();
    });

    test('show page can be rendered for own aprobacion', function () {
        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();
        $aprobacion = AprobacionSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'nivel' => 1,
            'aprobador_id' => $this->user->id,
            'estatus' => 'pendiente',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.aprobaciones.show', $aprobacion));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/aprobaciones/show')
            ->has('aprobacion')
            ->has('solicitud')
        );
    });

    test('show page returns 403 for another users aprobacion', function () {
        $otherUser = User::factory()->create();
        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();
        $aprobacion = AprobacionSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'nivel' => 1,
            'aprobador_id' => $otherUser->id,
            'estatus' => 'pendiente',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.aprobaciones.show', $aprobacion));

        $response->assertForbidden();
    });

    test('cannot approve if lower levels still pending', function () {
        $otherUser = User::factory()->create();
        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();
        AprobacionSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'nivel' => 1,
            'aprobador_id' => $otherUser->id,
            'estatus' => 'pendiente',
        ]);
        $aprobacion2 = AprobacionSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'nivel' => 2,
            'aprobador_id' => $this->user->id,
            'estatus' => 'pendiente',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.aprobaciones.aprobar', $aprobacion2));

        $response->assertSessionHasErrors(['nivel']);
    });
});
