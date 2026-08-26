<?php

use App\Enums\Costos\TipoFiscalPartida;
use App\Models\Costos\Aprobacion;
use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\AprobacionSolicitud;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Permiso;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Costos\SolicitudPago;
use App\Models\Costos\SolicitudPagoDetalle;
use App\Models\Departamento;
use App\Models\Proveedor;
use App\Models\User;
use Spatie\Permission\Models\Role;

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
            // El historial no viaja en la carga inicial: la pantalla lo pide
            // cuando alguien abre su pestana. Lo unico que va son los conteos,
            // que rotulan las pestanas.
            ->missing('aprobadas')
            ->missing('rechazadas')
            ->has('conteos.aprobadas')
            ->has('conteos.rechazadas')
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

    test('el badge de mis aprobaciones no cuenta pendientes de documentos ya cerrados', function () {
        // Pendiente en documento vivo (pendiente_firma) → cuenta.
        $viva = SolicitudPago::factory()->pendienteFirma()->create();
        AprobacionSolicitud::create([
            'solicitud_id' => $viva->id,
            'nivel' => 1,
            'aprobador_id' => $this->user->id,
            'estatus' => 'pendiente',
        ]);

        // Pendiente colgado en documento cancelado → NO debe contar.
        $cerrada = SolicitudPago::factory()->create(['estatus' => 'cancelada']);
        AprobacionSolicitud::create([
            'solicitud_id' => $cerrada->id,
            'nivel' => 1,
            'aprobador_id' => $this->user->id,
            'estatus' => 'pendiente',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.aprobaciones.index'));

        $badges = $response->original->getData()['page']['props']['auth']['badges'];
        expect($badges['/admin/costos/aprobaciones']['count'])->toBe(1);
    });

    test('el monto de una requisicion en la bandeja es el neto (subtotal + IVA - retenciones)', function () {
        // Persona moral + partida de mercancia: sin retenciones, solo IVA 16%.
        $proveedor = Proveedor::factory()->create(['tipo_persona' => 'moral']);

        $requisicion = Requisicion::factory()->pendienteAprobacion()->create();
        $detalle = RequisicionDetalle::factory()->create([
            'requisicion_id' => $requisicion->id,
            'tipo_fiscal' => TipoFiscalPartida::Mercancia->value,
        ]);
        $precio = RequisicionCotizacionPrecio::factory()->create([
            'requisicion_detalle_id' => $detalle->id,
            'proveedor_id' => $proveedor->id,
            'precio_unitario' => 1000,
        ]);
        RequisicionSeleccion::factory()->create([
            'requisicion_detalle_id' => $detalle->id,
            'cotizacion_precio_id' => $precio->id,
            'proveedor_id' => $proveedor->id,
            'numero_oc' => 1,
            'cantidad' => 1,
        ]);

        Aprobacion::create([
            'aprobable_type' => Requisicion::class,
            'aprobable_id' => $requisicion->id,
            'nivel' => 1,
            'aprobador_id' => $this->user->id,
            'estatus' => 'pendiente',
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.costos.aprobaciones.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('pendientes', 1)
                // 1000 subtotal + 16% IVA = 1160 (no es el subtotal pelon de 1000).
                ->where('pendientes.0.requisicion_total', fn ($v) => abs((float) $v - 1160.0) < 0.01)
            );
    });

    test('guardar una solicitud de pago con firma adicional crea la aprobacion nivel 0', function () {
        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
        $departamento = $obraRubro->obra->departamento ?? Departamento::factory()->create();
        $tipoSolicitud = \App\Models\Costos\TipoSolicitud::factory()->create();
        $aprobadorAdicional = User::factory()->create();
        $solicitante = darPermisosSolicitudesPago(User::factory()->create());

        $this->actingAs($solicitante)
            ->post(route('admin.costos.solicitudes-pago.store'), [
                'departamento_id' => $departamento->id,
                'tipo_solicitud_id' => $tipoSolicitud->id,
                'concepto' => 'Compra con firma adicional',
                'tipo_pago' => 'transferencia',
                'tipo_moneda' => 'mxn',
                'firma_adicional_aprobador_id' => $aprobadorAdicional->id,
                'monto_total' => 1000,
                'detalles' => [[
                    'obra_rubro_id' => $obraRubro->id,
                    'concepto' => 'Material',
                    'cantidad' => 10,
                    'precio_unitario' => 100,
                ]],
            ])
            ->assertRedirect();

        $solicitud = SolicitudPago::latest('id')->first();
        expect($solicitud->firma_adicional_aprobador_id)->toBe($aprobadorAdicional->id);

        // La cadena (incluida la firma adicional nivel 0) se crea al enviar a aprobación.
        $this->actingAs($solicitante)
            ->post(route('admin.costos.solicitudes-pago.enviar-aprobacion', $solicitud))
            ->assertRedirect();

        $adicional = $solicitud->aprobaciones()->where('nivel', 0)->first();
        expect($adicional)->not->toBeNull();
        expect($adicional->es_adicional)->toBeTrue();
        expect($adicional->aprobador_id)->toBe($aprobadorAdicional->id);
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

describe('bandeja supervisora de aprobaciones (solo lectura)', function () {
    beforeEach(function () {
        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole(Role::firstOrCreate(['name' => 'super-admin']));
    });

    test('sin usuario, lista los aprobadores para elegir', function () {
        $aprobador = User::factory()->create();
        AprobacionDepartamento::create([
            'departamento_id' => Departamento::factory()->create()->id,
            'permiso_id' => Permiso::factory()->create()->id,
            'aprobador_id' => $aprobador->id,
        ]);

        $this->actingAs($this->supervisor)
            ->get(route('admin.costos.aprobaciones.bandeja'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/costos/aprobaciones/index')
                ->where('soloLectura', true)
                ->where('aprobador', null)
                ->has('aprobadores', 1)
            );
    });

    test('muestra las pendientes de otro aprobador sin entrar a su cuenta', function () {
        $aprobador = User::factory()->create();
        $solicitud = SolicitudPago::factory()->pendienteFirma()->create();
        AprobacionSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'nivel' => 1,
            'aprobador_id' => $aprobador->id,
            'estatus' => 'pendiente',
        ]);

        $this->actingAs($this->supervisor)
            ->get(route('admin.costos.aprobaciones.bandeja', $aprobador))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/costos/aprobaciones/index')
                ->where('soloLectura', true)
                ->where('aprobador.id', $aprobador->id)
                ->has('pendientes', 1)
            );
    });

    test('requiere el rol super-admin', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.costos.aprobaciones.bandeja'))
            ->assertForbidden();
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
        // Nace en borrador; el creador la envía a aprobación (aparta presupuesto + cadena).
        expect($solicitud->estatus->value)->toBe('borrador');

        $this->actingAs($solicitante)
            ->post(route('admin.costos.solicitudes-pago.enviar-aprobacion', $solicitud))
            ->assertRedirect();

        $solicitud->refresh();
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
        // la solicitud en pendiente_firma. El apartado es reserva viva: pesa como
        // comprometido pero NO como ejercido (acumulado). La conversión a
        // permanente ocurre al completar la aprobación (último nivel).
        $obraRubro->refresh();
        expect((float) $obraRubro->acumulado)->toBe(0.00);
        expect((float) $obraRubro->apartado)->toBe(15000.00);
        expect((float) $obraRubro->comprometido)->toBe(15000.00);
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

        // Aún pendiente (falta nivel 3) — apartado vigente, ejercido en cero
        $solicitud->refresh();
        expect($solicitud->estatus->value)->toBe('pendiente_firma');
        $obraRubro->refresh();
        expect((float) $obraRubro->acumulado)->toBe(0.00);
        expect((float) $obraRubro->apartado)->toBe(15000.00);

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

/**
 * El historial no tiene techo —son todos los documentos que esa persona firmo
 * en su vida, con el arbol de relaciones que sirve para decidir y una consulta
 * por fila en `shape()`— y la pestana que se usa para trabajar es Pendientes.
 * Asi que no viaja hasta que alguien lo pide.
 */
describe('el historial se carga cuando lo piden', function () {
    /**
     * Encabezados de una recarga parcial. La version la calcula el middleware
     * —no `Inertia::getVersion()`, que fuera del request viene vacia y hace que
     * Inertia conteste 409 en vez de servir la prop.
     *
     * @param  list<string>  $props
     * @return array<string, string>
     */
    $parcial = function (array $props): array {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'admin/costos/aprobaciones/index',
            'X-Inertia-Partial-Data' => implode(',', $props),
        ];
    };

    beforeEach(function () {
        $firmada = SolicitudPago::factory()->create();
        AprobacionSolicitud::create([
            'solicitud_id' => $firmada->id,
            'nivel' => 1,
            'aprobador_id' => $this->user->id,
            'estatus' => 'aprobada',
            'fecha_respuesta' => now(),
        ]);
    });

    test('la carga inicial no trae el historial, pero si su conteo', function () {
        $this->actingAs($this->user)
            ->get(route('admin.costos.aprobaciones.index'))
            ->assertInertia(fn ($page) => $page
                ->missing('aprobadas')
                ->where('conteos.aprobadas', 1)
            );
    });

    test('la recarga parcial si lo trae', function () use ($parcial) {
        $this->actingAs($this->user)
            ->get(route('admin.costos.aprobaciones.index'), $parcial(['aprobadas']))
            ->assertOk()
            ->assertJsonPath('props.aprobadas.0.estatus', 'aprobada');
    });

    /** Pedir una pestana no arrastra la otra. */
    test('pedir aprobadas no trae rechazadas', function () use ($parcial) {
        $respuesta = $this->actingAs($this->user)
            ->get(route('admin.costos.aprobaciones.index'), $parcial(['aprobadas']));

        $respuesta->assertOk();
        expect($respuesta->json('props'))->toHaveKey('aprobadas')->not->toHaveKey('rechazadas');
    });
});
