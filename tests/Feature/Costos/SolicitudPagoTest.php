<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\Presupuesto;
use App\Models\Costos\SolicitudPago;
use App\Models\Costos\SolicitudPagoDetalle;
use App\Models\Costos\TipoSolicitud;
use App\Models\Departamento;
use App\Models\Obra;
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

    test('search matches proveedor razon_social', function () {
        darPermisoVerTodasSolicitudes($this->user);
        $proveedor = \App\Models\Proveedor::factory()->create(['razon_social' => 'Aceros del Norte SA']);
        SolicitudPago::factory()->create(['proveedor_id' => $proveedor->id]);
        SolicitudPago::factory()->count(2)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.solicitudes-pago.index', ['search' => 'Aceros del Norte']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('solicitudes.data', 1));
    });

    test('search is case insensitive', function () {
        darPermisoVerTodasSolicitudes($this->user);
        $proveedor = \App\Models\Proveedor::factory()->create(['razon_social' => 'Aceros del Norte SA']);
        SolicitudPago::factory()->create(['proveedor_id' => $proveedor->id]);
        SolicitudPago::factory()->count(2)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.solicitudes-pago.index', ['search' => 'aceros DEL norte']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('solicitudes.data', 1));
    });

    test('search matches solicitante name', function () {
        darPermisoVerTodasSolicitudes($this->user);
        $solicitante = User::factory()->create(['name' => 'Juan Buscable Perez']);
        SolicitudPago::factory()->create(['solicitante_id' => $solicitante->id]);
        SolicitudPago::factory()->count(2)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.solicitudes-pago.index', ['search' => 'Buscable']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('solicitudes.data', 1));
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
            ->has('presupuestos')
        );
    });

    /**
     * Desde el presupuesto polimórfico (2026-07-06) un presupuesto puede colgar
     * de un proyecto o de una partida y entonces sus rubros no tienen obra_id.
     * La pantalla filtraba centros de costos por obra y ésos nunca aparecían:
     * "no carga los centros de costos". El selector es por presupuesto.
     */
    test('los centros de costos de un presupuesto de proyecto llegan al formulario', function () {
        $presupuesto = Presupuesto::factory()->paraProyecto()->create();
        $rubro = $presupuesto->crearRubro(\App\Models\Costos\Rubro::factory()->create()->id, 1000);

        expect($rubro->obra_id)->toBeNull();

        $this->actingAs($this->user)
            ->get(route('admin.costos.solicitudes-pago.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('presupuestos.0.id', $presupuesto->id)
                ->where('obraRubros.0.id', $rubro->id)
                ->where('obraRubros.0.presupuesto_id', $presupuesto->id));
    });

    test('solicitud sin desglose usa el monto_total capturado', function () {
        $departamento = Departamento::factory()->create();
        $tipoSolicitud = TipoSolicitud::factory()->create(['rubros' => false]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.store'), [
                'departamento_id' => $departamento->id,
                'tipo_solicitud_id' => $tipoSolicitud->id,
                'concepto' => 'Compra de materiales',
                'tipo_pago' => 'transferencia',
                'tipo_moneda' => 'mxn',
                'monto_total' => 7500.50,
            ]);

        $solicitud = SolicitudPago::latest('id')->first();
        $response->assertRedirect(route('admin.costos.solicitudes-pago.show', $solicitud));
        expect($solicitud->detalles)->toHaveCount(0)
            ->and((float) $solicitud->monto_total)->toBe(7500.50);
    });

    test('solicitud sin desglose ni monto_total falla la validación', function () {
        $departamento = Departamento::factory()->create();
        $tipoSolicitud = TipoSolicitud::factory()->create(['rubros' => false]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.store'), [
                'departamento_id' => $departamento->id,
                'tipo_solicitud_id' => $tipoSolicitud->id,
                'concepto' => 'Compra de materiales',
                'tipo_pago' => 'transferencia',
                'tipo_moneda' => 'mxn',
            ])
            ->assertSessionHasErrors('monto_total');

        expect(SolicitudPago::count())->toBe(0);
    });

    test('guarda comentarios opcionales junto con la solicitud', function () {
        $departamento = Departamento::factory()->create();
        $tipoSolicitud = TipoSolicitud::factory()->create(['rubros' => false]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.store'), [
                'departamento_id' => $departamento->id,
                'tipo_solicitud_id' => $tipoSolicitud->id,
                'concepto' => 'Compra de materiales',
                'comentarios' => 'Entregar en obra antes del viernes.',
                'tipo_pago' => 'transferencia',
                'tipo_moneda' => 'mxn',
                'monto_total' => 1000,
            ])
            ->assertRedirect();

        expect(SolicitudPago::latest('id')->first()->comentarios)
            ->toBe('Entregar en obra antes del viernes.');
    });

    test('el concepto no puede superar 75 caracteres y comentarios 250', function () {
        $departamento = Departamento::factory()->create();
        $tipoSolicitud = TipoSolicitud::factory()->create(['rubros' => false]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.store'), [
                'departamento_id' => $departamento->id,
                'tipo_solicitud_id' => $tipoSolicitud->id,
                'concepto' => str_repeat('a', 76),
                'comentarios' => str_repeat('b', 251),
                'tipo_pago' => 'transferencia',
                'tipo_moneda' => 'mxn',
                'monto_total' => 1000,
            ])
            ->assertSessionHasErrors(['concepto', 'comentarios']);

        expect(SolicitudPago::count())->toBe(0);
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

    test('store crea la solicitud en borrador', function () {
        $departamento = Departamento::factory()->create();
        $tipoSolicitud = TipoSolicitud::factory()->create(['rubros' => false]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.store'), [
                'departamento_id' => $departamento->id,
                'tipo_solicitud_id' => $tipoSolicitud->id,
                'concepto' => 'Compra',
                'tipo_pago' => 'transferencia',
                'tipo_moneda' => 'mxn',
                'monto_total' => 1000,
            ])
            ->assertRedirect();

        expect(SolicitudPago::latest('id')->first()->estatus->value)->toBe('borrador');
    });

    test('el monto_total capturado manda sobre la suma de los detalles', function () {
        $departamento = Departamento::factory()->create();
        $tipoSolicitud = TipoSolicitud::factory()->create(['rubros' => true]);
        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.store'), [
                'departamento_id' => $departamento->id,
                'tipo_solicitud_id' => $tipoSolicitud->id,
                'concepto' => 'Compra materiales',
                'tipo_pago' => 'cheque',
                'tipo_moneda' => 'mxn',
                'monto_total' => 2000, // distinto de la suma (1505)
                'detalles' => [
                    [
                        'obra_rubro_id' => $obraRubro->id,
                        'concepto' => 'Acero',
                        'cantidad' => 10,
                        'precio_unitario' => 150.50,
                    ],
                ],
            ])
            ->assertRedirect();

        $solicitud = SolicitudPago::latest('id')->first();
        // El total editado manda; el detalle se conserva para el apartado.
        expect((float) $solicitud->monto_total)->toBe(2000.00)
            ->and($solicitud->detalles)->toHaveCount(1)
            ->and((float) $solicitud->detalles->first()->subtotal)->toBe(1505.00);
    });

    test('folio is auto-generated', function () {
        $solicitud = SolicitudPago::factory()->create();

        expect($solicitud->folio)->toStartWith('SP-');
    });

    test('show page can be rendered', function () {
        $solicitud = SolicitudPago::factory()->aprobada()->create(['solicitante_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.solicitudes-pago.show', $solicitud));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/solicitudes-pago/show')
            ->has('solicitud')
        );
    });

    test('el catálogo de centros de costos no viaja con el show: se pide al abrir el modal de reasignar', function () {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'costos.centros-costos.reasignar', 'guard_name' => 'web']);
        $this->user->givePermissionTo('costos.centros-costos.reasignar');

        $obraRubro = ObraRubro::factory()->create();
        $solicitud = SolicitudPago::factory()->aprobada()->create(['solicitante_id' => $this->user->id, 'orden_compra_id' => null]);
        SolicitudPagoDetalle::factory()->create(['solicitud_id' => $solicitud->id, 'obra_rubro_id' => $obraRubro->id]);

        $this->actingAs($this->user)
            ->get(route('admin.costos.solicitudes-pago.show', $solicitud))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('solicitud.puede_reasignar', true)
                ->missing('presupuestos')
                ->missing('obraRubros')
                ->reloadOnly(['presupuestos', 'obraRubros'], fn ($reload) => $reload
                    ->where('obraRubros.0.id', $obraRubro->id)
                    ->where('obraRubros.0.presupuesto_id', $obraRubro->presupuesto_id)
                    ->has('obraRubros.0.rubro.codigo')
                    ->has('presupuestos', 1)
                )
            );
    });

    test('sin permiso de reasignar el reload del catálogo llega vacío', function () {
        $obraRubro = ObraRubro::factory()->create();
        $solicitud = SolicitudPago::factory()->aprobada()->create(['solicitante_id' => $this->user->id, 'orden_compra_id' => null]);
        SolicitudPagoDetalle::factory()->create(['solicitud_id' => $solicitud->id, 'obra_rubro_id' => $obraRubro->id]);

        $this->actingAs($this->user)
            ->get(route('admin.costos.solicitudes-pago.show', $solicitud))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->reloadOnly(['presupuestos', 'obraRubros'], fn ($reload) => $reload
                    ->where('presupuestos', [])
                    ->where('obraRubros', [])
                )
            );
    });

    test('el show incluye la OP, la descripción y el presupuesto del centro de costo en cada detalle', function () {
        $obra = Obra::factory()->create(['no' => 'OP-123', 'descripcion' => 'Nave Industrial']);
        $presupuesto = Presupuesto::factory()->paraObra($obra)->create([
            'nombre_interno' => null,
            'op_interno' => null,
        ]);
        $obraRubro = ObraRubro::factory()->create([
            'presupuesto_id' => $presupuesto->id,
            'presupuestado' => 100000,
            'acumulado' => 130000,
        ]);
        $solicitud = SolicitudPago::factory()->aprobada()->create(['solicitante_id' => $this->user->id]);
        SolicitudPagoDetalle::factory()->create([
            'solicitud_id' => $solicitud->id,
            'obra_rubro_id' => $obraRubro->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.costos.solicitudes-pago.show', $solicitud))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('solicitud.detalles.0.obra_rubro.presupuesto.op_mostrar', 'OP-123')
                ->where('solicitud.detalles.0.obra_rubro.presupuesto.descripcion_mostrar', 'Nave Industrial')
                // Datos que alimentan la columna "dentro / sobregirado".
                ->where('solicitud.detalles.0.obra_rubro.presupuestado', '100000.00')
                ->where('solicitud.detalles.0.obra_rubro.acumulado', '130000.00')
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

    test('solicitud desglosada se actualiza aunque el request mande monto_total null', function () {
        $solicitud = SolicitudPago::factory()->create(['estatus' => 'borrador', 'monto_total' => 500]);
        $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
        $detalle = SolicitudPagoDetalle::factory()->create([
            'solicitud_id' => $solicitud->id,
            'obra_rubro_id' => $obraRubro->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.costos.solicitudes-pago.update', $solicitud), [
                'departamento_id' => $solicitud->departamento_id,
                'tipo_solicitud_id' => $solicitud->tipo_solicitud_id,
                'concepto' => 'Con desglose',
                'tipo_pago' => 'transferencia',
                'tipo_moneda' => 'mxn',
                'monto_total' => null, // el front lo manda vacío cuando hay desglose
                'detalles' => [
                    [
                        'id' => $detalle->id,
                        'obra_rubro_id' => $obraRubro->id,
                        'concepto' => 'Renglon',
                        'cantidad' => 5,
                        'precio_unitario' => 200,
                    ],
                ],
                '_version' => $solicitud->updated_at->toIso8601String(),
            ]);

        $response->assertRedirect(route('admin.costos.solicitudes-pago.index'));
        // El total se recalcula del desglose (5 * 200), nunca queda en null.
        expect((float) $solicitud->fresh()->monto_total)->toBe(1000.00);
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
                'monto_total' => 1000,
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

    test('la fecha de pago debe ser un viernes', function () {
        $departamento = Departamento::factory()->create();
        $tipoSolicitud = TipoSolicitud::factory()->create(['rubros' => false]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.store'), [
                'departamento_id' => $departamento->id,
                'tipo_solicitud_id' => $tipoSolicitud->id,
                'concepto' => 'Compra de materiales',
                'tipo_pago' => 'transferencia',
                'tipo_moneda' => 'mxn',
                'monto_total' => 100,
                'fecha_pago_solicitada' => '2026-07-09', // jueves
            ])
            ->assertSessionHasErrors('fecha_pago_solicitada');
    });

    test('la fecha de pago anterior al corte es rechazada', function () {
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-07-08 13:30')); // miércoles, tras el corte
        $departamento = Departamento::factory()->create();
        $tipoSolicitud = TipoSolicitud::factory()->create(['rubros' => false]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.store'), [
                'departamento_id' => $departamento->id,
                'tipo_solicitud_id' => $tipoSolicitud->id,
                'concepto' => 'Compra de materiales',
                'tipo_pago' => 'transferencia',
                'tipo_moneda' => 'mxn',
                'monto_total' => 100,
                'fecha_pago_solicitada' => '2026-07-10', // viernes bloqueado por el corte
            ])
            ->assertSessionHasErrors('fecha_pago_solicitada');

        \Illuminate\Support\Carbon::setTestNow();
    });

    test('la fecha de pago en un viernes válido se acepta', function () {
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-07-08 12:00')); // miércoles, antes del corte
        $departamento = Departamento::factory()->create();
        $tipoSolicitud = TipoSolicitud::factory()->create(['rubros' => false]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.solicitudes-pago.store'), [
                'departamento_id' => $departamento->id,
                'tipo_solicitud_id' => $tipoSolicitud->id,
                'concepto' => 'Compra de materiales',
                'tipo_pago' => 'transferencia',
                'tipo_moneda' => 'mxn',
                'monto_total' => 100,
                'fecha_pago_solicitada' => '2026-07-10', // viernes de esta semana
            ]);

        $response->assertSessionDoesntHaveErrors('fecha_pago_solicitada');
        expect(SolicitudPago::latest('id')->first()->fecha_pago_solicitada->toDateString())->toBe('2026-07-10');

        \Illuminate\Support\Carbon::setTestNow();
    });
});
