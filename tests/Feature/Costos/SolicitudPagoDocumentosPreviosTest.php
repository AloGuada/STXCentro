<?php

use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Requisicion;
use App\Models\Costos\SolicitudPago;
use App\Models\Departamento;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['costos.solicitudes-pago.ver', 'costos.solicitudes-pago.ver-todas'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['costos.solicitudes-pago.ver', 'costos.solicitudes-pago.ver-todas']);
    $this->depto = Departamento::factory()->create();
});

test('el show expone la OC y la requisición como documentos previos', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id]);
    $oc = OrdenCompra::factory()->create(['requisicion_id' => $req->id]);
    $solicitud = SolicitudPago::factory()->create([
        'departamento_id' => $this->depto->id,
        'orden_compra_id' => $oc->id,
    ]);

    $this->actingAs($this->user)
        ->get("/admin/costos/solicitudes-pago/{$solicitud->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('documentosPrevios', 2)
            ->where('documentosPrevios.0.url', route('admin.costos.ordenes-compra.pdf-oc', $oc))
            ->where('documentosPrevios.1.url', route('admin.costos.ordenes-compra.pdf-requisicion', $oc))
        );
});

test('una solicitud sin OC no tiene documentos previos', function () {
    $solicitud = SolicitudPago::factory()->create([
        'departamento_id' => $this->depto->id,
        'orden_compra_id' => null,
    ]);

    $this->actingAs($this->user)
        ->get("/admin/costos/solicitudes-pago/{$solicitud->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('documentosPrevios', 0));
});
