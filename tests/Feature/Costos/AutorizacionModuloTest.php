<?php

use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Presupuesto;
use App\Models\Costos\Requisicion;
use App\Models\Obra;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'costos.obra-rubros.ver',
        'costos.obra-rubros.crear',
        'costos.obra-rubros.editar',
        'costos.ordenes-compra.crear',
        'costos.ordenes-compra.eliminar',
        'costos.ordenes-compra.ver-todas',
        'costos.facturas.ver',
        'costos.facturas.aprobar',
    ] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $this->sinPermisos = User::factory()->create();
});

describe('presupuestos', function () {
    test('sin permiso no accede a ninguna acción de presupuestos', function (string $method, string $ruta) {
        $obra = Obra::factory()->create();
        $presupuesto = Presupuesto::create(['presupuestable_type' => Obra::class, 'presupuestable_id' => $obra->id]);
        $url = str_replace('{id}', (string) $presupuesto->id, $ruta);

        $this->actingAs($this->sinPermisos)->{$method}($url)->assertForbidden();
    })->with([
        ['get', '/admin/costos/presupuestos'],
        ['get', '/admin/costos/obras-activas'],
        ['get', '/admin/costos/presupuestos/reporte-pdf'],
        ['post', '/admin/costos/presupuestos'],
        ['post', '/admin/costos/presupuestos/planta'],
        ['get', '/admin/costos/presupuestos/{id}/edit'],
        ['put', '/admin/costos/presupuestos/{id}'],
        ['post', '/admin/costos/presupuestos/{id}/estado'],
    ]);

    test('con costos.obra-rubros.ver puede ver el índice de presupuestos', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('costos.obra-rubros.ver');

        $this->actingAs($user)->get('/admin/costos/presupuestos')->assertOk();
    });

    test('con costos.obra-rubros.editar puede cerrar y reabrir un presupuesto', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('costos.obra-rubros.editar');
        $obra = Obra::factory()->create();
        $presupuesto = Presupuesto::create(['presupuestable_type' => Obra::class, 'presupuestable_id' => $obra->id]);

        $this->actingAs($user)
            ->post("/admin/costos/presupuestos/{$presupuesto->id}/estado")
            ->assertRedirect();

        expect($presupuesto->fresh()->estaCerrado())->toBeTrue();
    });
});

describe('órdenes de compra', function () {
    test('sin permiso no puede crear, eliminar ni exportar OC', function (string $method, string $ruta) {
        $oc = OrdenCompra::factory()->create();
        $url = str_replace('{id}', (string) $oc->id, $ruta);

        $this->actingAs($this->sinPermisos)->{$method}($url)->assertForbidden();
    })->with([
        ['get', '/admin/costos/ordenes-compra/create'],
        ['post', '/admin/costos/ordenes-compra'],
        ['delete', '/admin/costos/ordenes-compra/{id}'],
        ['get', '/admin/costos/ordenes-compra/exportar'],
    ]);

    test('los PDF de una OC ajena están prohibidos sin ver-todas', function () {
        $oc = OrdenCompra::factory()->create();

        $this->actingAs($this->sinPermisos)
            ->get("/admin/costos/ordenes-compra/{$oc->id}/pdf-oc")
            ->assertForbidden();
        $this->actingAs($this->sinPermisos)
            ->get("/admin/costos/ordenes-compra/{$oc->id}/pdf-requisicion")
            ->assertForbidden();
    });

    test('el solicitante de la requisición de origen sí puede ver el PDF de su OC', function () {
        $solicitante = User::factory()->create();
        $requisicion = Requisicion::factory()->create(['solicitante_id' => $solicitante->id]);
        $oc = OrdenCompra::factory()->create(['requisicion_id' => $requisicion->id]);

        $this->actingAs($solicitante)
            ->get("/admin/costos/ordenes-compra/{$oc->id}/pdf-oc")
            ->assertOk();
    });
});

describe('facturas', function () {
    test('sin permiso no puede ver facturas ni aprobarlas por costos', function () {
        $factura = Factura::factory()->create();

        $this->actingAs($this->sinPermisos)->get('/admin/costos/facturas')->assertForbidden();
        $this->actingAs($this->sinPermisos)->get("/admin/costos/facturas/{$factura->id}")->assertForbidden();
        $this->actingAs($this->sinPermisos)
            ->post("/admin/costos/facturas/{$factura->id}/aprobar-costos")
            ->assertForbidden();
    });

    test('con costos.facturas.ver puede ver el índice de facturas', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('costos.facturas.ver');

        $this->actingAs($user)->get('/admin/costos/facturas')->assertOk();
    });
});
