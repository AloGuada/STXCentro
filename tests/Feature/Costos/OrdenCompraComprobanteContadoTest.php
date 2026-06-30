<?php

use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Pago;
use App\Models\Costos\SolicitudPago;
use App\Models\User;

test('el show de la OC expone el comprobante de pago del anticipo de contado (vía solicitud)', function () {
    $oc = OrdenCompra::factory()->pendienteEntrega()->create();
    $solicitud = SolicitudPago::factory()->create(['orden_compra_id' => $oc->id]);

    $pago = Pago::factory()->create([
        'pagable_type' => SolicitudPago::class,
        'pagable_id' => $solicitud->id,
        'estatus' => 'pagado',
    ]);
    $pago->media()->create([
        'descripcion' => 'comprobante_pago',
        'nombre_original' => 'comprobante.pdf',
        'path' => 'costos/pagos/comprobantes/x.pdf',
        'mime' => 'application/pdf',
        'size' => 100,
    ]);

    $this->actingAs(User::factory()->create())
        ->get("/admin/costos/ordenes-compra/{$oc->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('ordenCompra.solicitudes_pago.0.pago.media')
            ->where('ordenCompra.solicitudes_pago.0.pago.media.descripcion', 'comprobante_pago')
        );
});
