<?php

use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Requisicion;
use App\Models\Costos\SolicitudPago;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'costos.requisiciones.ver',
        'costos.requisiciones.ver-todas',
        'costos.ordenes-compra.ver-todas',
        'costos.solicitudes-pago.ver',
        'costos.solicitudes-pago.ver-todas',
    ] as $permName) {
        Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
    }
});

// ---------------------------------------------------------------- Orden de compra

test('el solicitante de la requisición ve la OC generada', function () {
    $solicitante = User::factory()->create();
    $req = Requisicion::factory()->create(['solicitante_id' => $solicitante->id]);
    $oc = OrdenCompra::factory()->create(['requisicion_id' => $req->id]);

    $this->actingAs($solicitante)
        ->get("/admin/costos/ordenes-compra/{$oc->id}")
        ->assertOk();
});

test('el creador ve la OC', function () {
    $creador = User::factory()->create();
    $oc = OrdenCompra::factory()->create(['creado_por' => $creador->id]);

    $this->actingAs($creador)
        ->get("/admin/costos/ordenes-compra/{$oc->id}")
        ->assertOk();
});

test('un usuario ajeno no ve la OC', function () {
    $ajeno = User::factory()->create();
    $oc = OrdenCompra::factory()->create();

    $this->actingAs($ajeno)
        ->get("/admin/costos/ordenes-compra/{$oc->id}")
        ->assertForbidden();
});

test('quien puede ver todas ve cualquier OC', function () {
    $operador = User::factory()->create();
    $operador->givePermissionTo('costos.ordenes-compra.ver-todas');
    $oc = OrdenCompra::factory()->create();

    $this->actingAs($operador)
        ->get("/admin/costos/ordenes-compra/{$oc->id}")
        ->assertOk();
});

// ------------------------------------------------------------- Solicitud de pago

test('el solicitante de la requisición ve la solicitud de pago de contado de su OC', function () {
    $solicitante = User::factory()->create();
    $solicitante->givePermissionTo('costos.solicitudes-pago.ver');
    $req = Requisicion::factory()->create(['solicitante_id' => $solicitante->id]);
    $oc = OrdenCompra::factory()->create(['requisicion_id' => $req->id]);
    $sp = SolicitudPago::factory()->create(['orden_compra_id' => $oc->id]);

    $this->actingAs($solicitante)
        ->get("/admin/costos/solicitudes-pago/{$sp->id}")
        ->assertOk();
});

test('el propio solicitante ve su solicitud de pago', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('costos.solicitudes-pago.ver');
    $sp = SolicitudPago::factory()->create(['solicitante_id' => $user->id]);

    $this->actingAs($user)
        ->get("/admin/costos/solicitudes-pago/{$sp->id}")
        ->assertOk();
});

test('un usuario con permiso ver pero sin relación no ve la solicitud de pago', function () {
    $ajeno = User::factory()->create();
    $ajeno->givePermissionTo('costos.solicitudes-pago.ver');
    $sp = SolicitudPago::factory()->create();

    $this->actingAs($ajeno)
        ->get("/admin/costos/solicitudes-pago/{$sp->id}")
        ->assertForbidden();
});

test('un aprobador asignado ve la solicitud de pago', function () {
    $aprobador = User::factory()->create();
    $aprobador->givePermissionTo('costos.solicitudes-pago.ver');
    $sp = SolicitudPago::factory()->create();
    $sp->aprobaciones()->create([
        'nivel' => 1,
        'aprobador_id' => $aprobador->id,
        'estatus' => 'pendiente',
    ]);

    $this->actingAs($aprobador)
        ->get("/admin/costos/solicitudes-pago/{$sp->id}")
        ->assertOk();
});

// ------------------------------------------------------------------- Requisición

test('el solicitante ve su requisición con las OC y solicitudes de pago derivadas', function () {
    $solicitante = User::factory()->create();
    $solicitante->givePermissionTo('costos.requisiciones.ver');
    $req = Requisicion::factory()->create(['solicitante_id' => $solicitante->id]);
    $oc = OrdenCompra::factory()->create(['requisicion_id' => $req->id]);
    $sp = SolicitudPago::factory()->create(['orden_compra_id' => $oc->id]);

    $this->actingAs($solicitante)
        ->get("/admin/costos/requisiciones/{$req->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('requisicion.ordenes_generadas', 1)
            ->where('requisicion.ordenes_generadas.0.solicitudes_pago.0.id', $sp->id)
        );
});

test('un aprobador de la requisición la puede ver', function () {
    $aprobador = User::factory()->create();
    $aprobador->givePermissionTo('costos.requisiciones.ver');
    $req = Requisicion::factory()->create();
    $req->aprobaciones()->create([
        'nivel' => 1,
        'aprobador_id' => $aprobador->id,
        'estatus' => 'pendiente',
    ]);

    $this->actingAs($aprobador)
        ->get("/admin/costos/requisiciones/{$req->id}")
        ->assertOk();
});

test('un usuario ajeno con permiso ver no ve una requisición que no es suya', function () {
    $ajeno = User::factory()->create();
    $ajeno->givePermissionTo('costos.requisiciones.ver');
    $req = Requisicion::factory()->create();

    $this->actingAs($ajeno)
        ->get("/admin/costos/requisiciones/{$req->id}")
        ->assertForbidden();
});
