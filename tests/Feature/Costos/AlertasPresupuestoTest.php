<?php

use App\Events\Costos\PresupuestoExcedido;
use App\Exceptions\Costos\SobregiroPresupuestalException;
use App\Listeners\Costos\NotificarAprobadoresPresupuesto;
use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Permiso;
use App\Models\Costos\SolicitudPago;
use App\Models\Costos\SolicitudPagoDetalle;
use App\Models\Departamento;
use App\Models\Obra;
use App\Models\User;
use App\Notifications\Costos\PresupuestoExcedidoNotification;
use App\Services\Costos\ValidadorPresupuesto;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

describe('ObraRubro accessors de alerta', function () {
    test('porcentaje_consumido y estado_alerta normal', function () {
        $or = ObraRubro::factory()->create(['presupuestado' => 1000, 'acumulado' => 500]);
        expect($or->porcentaje_consumido)->toBe(50.0);
        expect($or->estado_alerta)->toBe('normal');
        expect($or->disponible)->toBe(500.0);
    });

    test('estado_alerta critico cuando supera el umbral configurado', function () {
        config(['costos.umbral_alerta_porcentaje' => 80]);
        $or = ObraRubro::factory()->create(['presupuestado' => 1000, 'acumulado' => 850]);
        expect($or->porcentaje_consumido)->toBe(85.0);
        expect($or->estado_alerta)->toBe('critico');
    });

    test('estado_alerta sobregiro cuando excede 100%', function () {
        $or = ObraRubro::factory()->create(['presupuestado' => 1000, 'acumulado' => 1200]);
        expect($or->porcentaje_consumido)->toBe(120.0);
        expect($or->estado_alerta)->toBe('sobregiro');
        expect($or->disponible)->toBe(-200.0);
    });

    test('rubro sin presupuesto devuelve 100% si tiene acumulado', function () {
        $or = ObraRubro::factory()->create(['presupuestado' => 0, 'acumulado' => 500]);
        expect($or->porcentaje_consumido)->toBe(100.0);
        expect($or->estado_alerta)->toBe('sobregiro');
    });
});

describe('ValidadorPresupuesto', function () {
    test('lanza excepcion si bloquear_sobregiro=true y excede disponible', function () {
        config(['costos.bloquear_sobregiro' => true]);
        $or = ObraRubro::factory()->create(['presupuestado' => 1000, 'acumulado' => 500]);
        $entrada = OrdenCompra::factory()->create();

        expect(fn () => app(ValidadorPresupuesto::class)->validar($or, 600, $entrada))
            ->toThrow(SobregiroPresupuestalException::class);
    });

    test('no lanza excepcion si bloquear_sobregiro=false; dispara evento sobregiro', function () {
        Event::fake([PresupuestoExcedido::class]);
        config(['costos.bloquear_sobregiro' => false]);

        $or = ObraRubro::factory()->create(['presupuestado' => 1000, 'acumulado' => 500]);
        $entrada = OrdenCompra::factory()->create();

        app(ValidadorPresupuesto::class)->validar($or, 700, $entrada);

        Event::assertDispatched(PresupuestoExcedido::class, fn ($e) => $e->nivel === 'sobregiro');
    });

    test('dispara evento critico al cruzar el umbral sin sobregiro', function () {
        Event::fake([PresupuestoExcedido::class]);
        config(['costos.umbral_alerta_porcentaje' => 90, 'costos.bloquear_sobregiro' => false]);

        $or = ObraRubro::factory()->create(['presupuestado' => 1000, 'acumulado' => 500]);
        $entrada = OrdenCompra::factory()->create();

        app(ValidadorPresupuesto::class)->validar($or, 450, $entrada); // 950/1000 = 95%

        Event::assertDispatched(PresupuestoExcedido::class, fn ($e) => $e->nivel === 'critico');
    });

    test('no dispara critico si ya estaba arriba del umbral antes', function () {
        Event::fake([PresupuestoExcedido::class]);
        config(['costos.umbral_alerta_porcentaje' => 90, 'costos.bloquear_sobregiro' => false]);

        $or = ObraRubro::factory()->create(['presupuestado' => 1000, 'acumulado' => 920]);
        $entrada = OrdenCompra::factory()->create();

        app(ValidadorPresupuesto::class)->validar($or, 50, $entrada); // sigue por debajo de 100%

        Event::assertNotDispatched(PresupuestoExcedido::class);
    });

    test('caso normal sin alertas', function () {
        Event::fake([PresupuestoExcedido::class]);
        config(['costos.umbral_alerta_porcentaje' => 90, 'costos.bloquear_sobregiro' => false]);

        $or = ObraRubro::factory()->create(['presupuestado' => 1000, 'acumulado' => 100]);
        $entrada = OrdenCompra::factory()->create();

        app(ValidadorPresupuesto::class)->validar($or, 200, $entrada); // 30%

        Event::assertNotDispatched(PresupuestoExcedido::class);
    });
});

describe('Integracion con aplicarImpactoPresupuestal', function () {
    test('OrdenCompra::aplicarImpactoPresupuestal aborta si bloquear_sobregiro=true', function () {
        config(['costos.bloquear_sobregiro' => true]);
        $or = ObraRubro::factory()->create(['presupuestado' => 1000, 'acumulado' => 0]);
        $oc = OrdenCompra::factory()->create();
        OrdenCompraDetalle::factory()->create([
            'orden_compra_id' => $oc->id,
            'obra_rubro_id' => $or->id,
            'subtotal' => 1500,
        ]);

        expect(fn () => $oc->load('detalles')->aplicarImpactoPresupuestal())
            ->toThrow(SobregiroPresupuestalException::class);

        // El acumulado NO se incrementa
        expect((float) $or->fresh()->acumulado)->toBe(0.0);
    });

    test('SolicitudPago::aplicarImpactoPresupuestal dispara evento si excede sin bloqueo', function () {
        Event::fake([PresupuestoExcedido::class]);
        config(['costos.bloquear_sobregiro' => false]);

        $or = ObraRubro::factory()->create(['presupuestado' => 1000, 'acumulado' => 0]);
        $sp = SolicitudPago::factory()->create();
        SolicitudPagoDetalle::factory()->create([
            'solicitud_id' => $sp->id,
            'obra_rubro_id' => $or->id,
            'subtotal' => 1500,
        ]);

        $sp->load('detalles')->aplicarImpactoPresupuestal();

        Event::assertDispatched(PresupuestoExcedido::class);
        // El acumulado SI se incrementa (solo se persiste sobre_giro=true)
        expect((float) $or->fresh()->acumulado)->toBe(1500.0);
    });
});

describe('Notification a aprobadores del departamento', function () {
    test('listener envia PresupuestoExcedidoNotification a aprobadores configurados', function () {
        Notification::fake();

        $depto = Departamento::factory()->create();
        // Usuario es el modelo real (no el alias User para Fortify) — el listener
        // resuelve via Usuario::whereIn(), asi que el test debe usar la misma clase.
        $aprobador1 = \App\Models\Usuario::factory()->create();
        $aprobador2 = \App\Models\Usuario::factory()->create();
        $permiso = Permiso::create([
            'descripcion' => 'Costos',
            'nivel' => 1,
            'tipo_aprobacion' => 'solicitud_pago',
        ]);
        AprobacionDepartamento::create([
            'departamento_id' => $depto->id,
            'permiso_id' => $permiso->id,
            'aprobador_id' => $aprobador1->id,
        ]);
        AprobacionDepartamento::create([
            'departamento_id' => $depto->id,
            'permiso_id' => $permiso->id,
            'aprobador_id' => $aprobador2->id,
        ]);

        $or = ObraRubro::factory()->create(['presupuestado' => 1000, 'acumulado' => 500]);
        $oc = OrdenCompra::factory()->create(['departamento_id' => $depto->id]);

        $listener = app(NotificarAprobadoresPresupuesto::class);
        $listener->handle(new PresupuestoExcedido($or, 700, $oc, 'sobregiro'));

        Notification::assertSentTo([$aprobador1, $aprobador2], PresupuestoExcedidoNotification::class);
    });

    test('listener no envia nada si la entrada no tiene departamento', function () {
        Notification::fake();

        $or = ObraRubro::factory()->create(['presupuestado' => 1000, 'acumulado' => 500]);
        // Anticipo no tiene departamento_id en su schema
        $entradaSinDepto = \App\Models\Costos\Anticipo::factory()->create();

        $listener = app(NotificarAprobadoresPresupuesto::class);
        $listener->handle(new PresupuestoExcedido($or, 700, $entradaSinDepto, 'sobregiro'));

        Notification::assertNothingSent();
    });
});

describe('PresupuestoController dashboard stats', function () {
    test('index retorna stats con sobregiros y criticos contados', function () {
        config(['costos.umbral_alerta_porcentaje' => 90]);

        // 1 normal, 1 critico, 1 sobregiro
        $obra = Obra::factory()->create();
        ObraRubro::factory()->create(['obra_id' => $obra->id, 'presupuestado' => 1000, 'acumulado' => 500]); // 50%
        ObraRubro::factory()->create(['obra_id' => $obra->id, 'presupuestado' => 1000, 'acumulado' => 950]); // 95%
        ObraRubro::factory()->create(['obra_id' => $obra->id, 'presupuestado' => 1000, 'acumulado' => 1200]); // 120%

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'costos.obra-rubros.ver', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('costos.obra-rubros.ver');

        $this->actingAs($user)
            ->get('/admin/costos/presupuestos')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/costos/presupuestos/index')
                ->where('stats.criticos', 1)
                ->where('stats.sobregiros', 1)
                ->where('stats.umbral_alerta', 90)
                ->where('stats.bloquear_sobregiro', config('costos.bloquear_sobregiro'))
            );
    });
});
