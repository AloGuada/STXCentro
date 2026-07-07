<?php

use App\Models\Costos\AprobacionSolicitud;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\SolicitudPago;
use App\Models\Costos\SolicitudPagoDetalle;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['firma_path' => 'firmas/test.png']);
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

    test('no muestra pendientes de una solicitud cancelada aunque la aprobacion siga pendiente', function () {
        // Simula una cancelación previa al fix: la solicitud queda cancelada
        // pero su aprobación se quedó en `pendiente`.
        $solicitud = SolicitudPago::factory()->create(['estatus' => 'cancelada']);
        AprobacionSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'nivel' => 1,
            'aprobador_id' => $this->user->id,
            'estatus' => 'pendiente',
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.costos.aprobaciones.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('pendientes', 0));
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
        expect($aprobacion->estatus->value)->toBe('aprobada');
        expect($aprobacion->fecha_respuesta)->not->toBeNull();
        expect($aprobacion->observaciones)->toBe('Todo en orden');
        expect($aprobacion->ip)->not->toBeNull();
        expect($aprobacion->hostname)->not->toBeNull();
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
            ->post(route('admin.costos.aprobaciones.aprobar', $aprobacion), [
                'observaciones' => 'Aprobado conforme',
            ]);

        $solicitud->refresh();
        expect($solicitud->estatus->value)->toBe('aprobada');

        $obraRubro->refresh();
        expect((float) $obraRubro->acumulado)->toBe(3000.00);
    });

    test('approval requires observaciones', function () {
        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();
        $aprobacion = AprobacionSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'nivel' => 1,
            'aprobador_id' => $this->user->id,
            'estatus' => 'pendiente',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.aprobaciones.aprobar', $aprobacion), []);

        $response->assertSessionHasErrors(['observaciones']);
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
        expect($aprobacion->estatus->value)->toBe('rechazada');
        expect($aprobacion->observaciones)->toBe('No cumple requisitos');
        expect($aprobacion->motivo_rechazo)->toBe('No cumple requisitos');
        expect($aprobacion->ip)->not->toBeNull();
        expect($aprobacion->hostname)->not->toBeNull();

        $solicitud->refresh();
        expect($solicitud->estatus->value)->toBe('cancelada');
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
                'observaciones' => 'Solicitud rechazada por inconsistencias',
            ]);

        $aprobacion2->refresh();
        expect($aprobacion2->estatus->value)->toBe('cancelada');
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

    test('show page includes obra rubro budget data for approver', function () {
        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 50000, 'acumulado' => 45000]);
        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();
        SolicitudPagoDetalle::factory()->create([
            'solicitud_id' => $solicitud->id,
            'obra_rubro_id' => $obraRubro->id,
            'subtotal' => 10000,
        ]);
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
            ->has('solicitud.detalles.0.obra_rubro', fn ($obraRubroPage) => $obraRubroPage
                ->where('presupuestado', '50000.00')
                ->where('acumulado', '45000.00')
                ->has('rubro')
                ->has('presupuesto')
                ->etc()
            )
        );
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
            ->post(route('admin.costos.aprobaciones.aprobar', $aprobacion2), [
                'observaciones' => 'Intento aprobar',
            ]);

        $response->assertSessionHasErrors(['nivel']);
    });
});

describe('flujo completo de solicitud con aprobación multinivel y pago', function () {
    test('solicitud se crea, aprueba por cada nivel, impacta presupuesto y se registra pago', function () {
        // 1. Configurar obra + rubro con presupuesto
        $obraRubro = ObraRubro::factory()->create([
            'presupuestado' => 200000,
            'acumulado' => 0,
        ]);

        $departamento = $obraRubro->obra->departamento ?? \App\Models\Departamento::factory()->create();
        $tipoSolicitud = \App\Models\Costos\TipoSolicitud::factory()->create();
        $proveedor = \App\Models\Proveedor::factory()->create();

        // 2. Configurar cadena de aprobación: 3 niveles
        $aprobador1 = User::factory()->create(['firma_path' => 'firmas/aprobador1.png']);
        $aprobador2 = User::factory()->create(['firma_path' => 'firmas/aprobador2.png']);
        $aprobador3 = User::factory()->create(['firma_path' => 'firmas/aprobador3.png']);

        $permiso1 = \App\Models\Costos\Permiso::factory()->create(['descripcion' => 'Jefe Depto', 'nivel' => 1]);
        $permiso2 = \App\Models\Costos\Permiso::factory()->create(['descripcion' => 'Gerente', 'nivel' => 2]);
        $permiso3 = \App\Models\Costos\Permiso::factory()->create(['descripcion' => 'Director', 'nivel' => 3]);

        \App\Models\Costos\AprobacionDepartamento::create([
            'departamento_id' => $departamento->id,
            'permiso_id' => $permiso1->id,
            'aprobador_id' => $aprobador1->id,
        ]);
        \App\Models\Costos\AprobacionDepartamento::create([
            'departamento_id' => $departamento->id,
            'permiso_id' => $permiso2->id,
            'aprobador_id' => $aprobador2->id,
        ]);
        \App\Models\Costos\AprobacionDepartamento::create([
            'departamento_id' => $departamento->id,
            'permiso_id' => $permiso3->id,
            'aprobador_id' => $aprobador3->id,
        ]);

        // 3. Crear solicitud de pago como usuario solicitante
        $solicitante = darPermisosSolicitudesPago(User::factory()->create());

        $response = $this->actingAs($solicitante)
            ->post(route('admin.costos.solicitudes-pago.store'), [
                'departamento_id' => $departamento->id,
                'proveedor_id' => $proveedor->id,
                'tipo_solicitud_id' => $tipoSolicitud->id,
                'concepto' => 'Compra de material para obra',
                'tipo_pago' => 'transferencia',
                'tipo_moneda' => 'mxn',
                'detalles' => [
                    [
                        'obra_rubro_id' => $obraRubro->id,
                        'concepto' => 'Cemento gris',
                        'cantidad' => 100,
                        'precio_unitario' => 150,
                    ],
                ],
            ]);

        $response->assertRedirect();

        $solicitud = SolicitudPago::latest('id')->first();
        expect($solicitud->estatus->value)->toBe('pendiente_firma');
        expect((float) $solicitud->monto_total)->toBe(15000.00);
        expect($solicitud->aprobaciones)->toHaveCount(3);

        // Verificar que las aprobaciones se crearon con los niveles correctos
        $aprobaciones = $solicitud->aprobaciones()->orderBy('nivel')->get();
        expect($aprobaciones[0]->nivel)->toBe(1);
        expect($aprobaciones[0]->aprobador_id)->toBe($aprobador1->id);
        expect($aprobaciones[1]->nivel)->toBe(2);
        expect($aprobaciones[1]->aprobador_id)->toBe($aprobador2->id);
        expect($aprobaciones[2]->nivel)->toBe(3);
        expect($aprobaciones[2]->aprobador_id)->toBe($aprobador3->id);

        // 4. Nivel 1 aprueba
        $this->actingAs($aprobador1)
            ->post(route('admin.costos.aprobaciones.aprobar', $aprobaciones[0]), [
                'observaciones' => 'Conforme al presupuesto',
            ])
            ->assertRedirect();

        $aprobaciones[0]->refresh();
        expect($aprobaciones[0]->estatus->value)->toBe('aprobada');
        expect($aprobaciones[0]->ip)->not->toBeNull();
        expect($aprobaciones[0]->hostname)->not->toBeNull();

        // Solicitud sigue en pendiente_firma (faltan niveles)
        $solicitud->refresh();
        expect($solicitud->estatus->value)->toBe('pendiente_firma');

        // Presupuesto YA está apartado temporalmente (5 días) desde que se creó
        // la solicitud en pendiente_firma. La conversión a permanente ocurre
        // al completar la aprobación (último nivel).
        $obraRubro->refresh();
        expect((float) $obraRubro->acumulado)->toBe(15000.00);
        expect($solicitud->rubrosAfectados()->where('estatus', 'apartado')->count())->toBe(1);

        // 5. Nivel 2 aprueba
        $this->actingAs($aprobador2)
            ->post(route('admin.costos.aprobaciones.aprobar', $aprobaciones[1]), [
                'observaciones' => 'Revisado y aprobado',
            ])
            ->assertRedirect();

        $aprobaciones[1]->refresh();
        expect($aprobaciones[1]->estatus->value)->toBe('aprobada');
        expect($aprobaciones[1]->ip)->not->toBeNull();

        // Aún pendiente (falta nivel 3) — apartado vigente
        $solicitud->refresh();
        expect($solicitud->estatus->value)->toBe('pendiente_firma');
        $obraRubro->refresh();
        expect((float) $obraRubro->acumulado)->toBe(15000.00);

        // 6. Nivel 3 (último) aprueba → se aplica impacto presupuestal
        $this->actingAs($aprobador3)
            ->post(route('admin.costos.aprobaciones.aprobar', $aprobaciones[2]), [
                'observaciones' => 'Autorizado por dirección',
            ])
            ->assertRedirect();

        $aprobaciones[2]->refresh();
        expect($aprobaciones[2]->estatus->value)->toBe('aprobada');

        // Solicitud cambia a aprobada
        $solicitud->refresh();
        expect($solicitud->estatus->value)->toBe('aprobada');

        // Presupuesto impactado
        $obraRubro->refresh();
        expect((float) $obraRubro->acumulado)->toBe(15000.00);

        // El apartado original ahora está convertido a Aplicado (permanente)
        expect($solicitud->rubrosAfectados()->where('estatus', 'aplicado')->count())->toBe(1);
        $rubroAplicado = $solicitud->rubrosAfectados()->where('estatus', 'aplicado')->first();
        expect($rubroAplicado->tipo_movimiento)->toBe('cargo');
        expect((float) $rubroAplicado->monto)->toBe(15000.00);

        // 7. Confirmar costos → crea pago automáticamente
        $adminCostos = User::factory()->create();
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'costos.solicitudes.confirmar-costos']);
        $adminCostos->givePermissionTo('costos.solicitudes.confirmar-costos');

        $this->actingAs($adminCostos)
            ->post(route('admin.costos.solicitudes-pago.confirmar-costos', $solicitud))
            ->assertRedirect();

        $solicitud->refresh();
        expect($solicitud->confirmada_costos)->toBeTrue();

        $pago = $solicitud->pago;
        expect($pago)->not->toBeNull();
        expect((float) $pago->monto_pago)->toBe(15000.00);
        expect($pago->tipo_pago)->toBe('contado');
        expect($pago->estatus->value)->toBe('programado');
    });
});
