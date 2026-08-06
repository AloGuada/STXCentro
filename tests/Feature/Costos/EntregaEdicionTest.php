<?php

use App\Models\Costos\Entrega;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Pago;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['costos.entregas.crear', 'costos.entregas.editar'] as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['costos.entregas.crear', 'costos.entregas.editar']);
    $this->otro = User::factory()->create(['name' => 'Quien recibio de verdad']);

    $this->oc = OrdenCompra::factory()->pendienteFactura()->create(['total' => 1000]);
    $this->partida = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $this->oc->id,
        'cantidad' => 10,
        'precio_unitario' => 100,
        'subtotal' => 1000,
    ]);

    $this->factura = Factura::factory()->create([
        'orden_compra_id' => $this->oc->id,
        'estatus' => 'pendiente_recepcion',
    ]);

    $this->entrega = Entrega::factory()->create([
        'orden_compra_id' => $this->oc->id,
        'factura_id' => $this->factura->id,
        'recibido_por' => $this->user->id,
        'fecha_entrega' => '2026-02-17',
        'observaciones' => 'Capturada de volada',
    ]);
});

/**
 * Payload de edición. La factura ligada es obligatoria, así que por omisión se
 * manda la que ya trae la recepción.
 *
 * @param  array<string, mixed>  $extra
 */
function editar(Entrega $entrega, array $extra = []): array
{
    return array_merge([
        'fecha_entrega' => '2026-02-20',
        'recibido_por' => test()->otro->id,
        'factura_id' => $entrega->factura_id,
        'observaciones' => 'Corregida',
    ], $extra);
}

test('corrige fecha, quien recibio y observaciones', function () {
    $this->actingAs($this->user)
        ->post("/admin/costos/entregas/{$this->entrega->id}", editar($this->entrega))
        ->assertRedirect();

    $fresca = $this->entrega->fresh();

    expect($fresca->fecha_entrega->toDateString())->toBe('2026-02-20')
        ->and($fresca->recibido_por)->toBe($this->otro->id)
        ->and($fresca->observaciones)->toBe('Corregida');
});

test('sin el permiso no puede editar', function () {
    $ajeno = User::factory()->create();

    $this->actingAs($ajeno)
        ->post("/admin/costos/entregas/{$this->entrega->id}", editar($this->entrega))
        ->assertForbidden();

    expect($this->entrega->fresh()->observaciones)->toBe('Capturada de volada');
});

test('la fecha, quien recibio y la factura son obligatorios', function () {
    $this->actingAs($this->user)
        ->post("/admin/costos/entregas/{$this->entrega->id}", ['observaciones' => 'Solo esto'])
        ->assertSessionHasErrors(['fecha_entrega', 'recibido_por', 'factura_id']);
});

test('no se puede editar una recepcion cancelada', function () {
    $this->entrega->update(['cancelada_at' => now(), 'cancelada_por' => $this->user->id]);

    $this->actingAs($this->user)
        ->post("/admin/costos/entregas/{$this->entrega->id}", editar($this->entrega))
        ->assertSessionHasErrors('error');

    expect($this->entrega->fresh()->observaciones)->toBe('Capturada de volada');
});

test('no se puede editar si la factura ligada ya esta pagada', function () {
    $factura = Factura::factory()->create(['orden_compra_id' => $this->oc->id]);
    // El pago es polimorfico: cuelga de la factura, no lleva factura_id.
    Pago::factory()->create([
        'pagable_type' => Factura::class,
        'pagable_id' => $factura->id,
    ]);
    $this->entrega->update(['factura_id' => $factura->id]);

    $this->actingAs($this->user)
        ->post("/admin/costos/entregas/{$this->entrega->id}", editar($this->entrega))
        ->assertSessionHasErrors('error');

    expect($this->entrega->fresh()->observaciones)->toBe('Capturada de volada');
});

test('con la factura aprobada pero sin pago si se puede corregir', function () {
    $factura = Factura::factory()->create([
        'orden_compra_id' => $this->oc->id,
        'aprobada_costos' => true,
    ]);
    $this->entrega->update(['factura_id' => $factura->id]);

    $this->actingAs($this->user)
        ->post("/admin/costos/entregas/{$this->entrega->id}", editar($this->entrega))
        ->assertSessionHasNoErrors();

    expect($this->entrega->fresh()->observaciones)->toBe('Corregida');
});

test('la evidencia nueva reemplaza a la anterior en vez de acumularse', function () {
    Storage::fake('public');

    $this->actingAs($this->user)->post("/admin/costos/entregas/{$this->entrega->id}", editar($this->entrega, [
        'archivo' => UploadedFile::fake()->create('remision.pdf', 100, 'application/pdf'),
    ]));

    $this->actingAs($this->user)->post("/admin/costos/entregas/{$this->entrega->id}", editar($this->entrega, [
        'archivo' => UploadedFile::fake()->create('remision-buena.pdf', 100, 'application/pdf'),
    ]));

    $media = $this->entrega->fresh()->media()->get();

    expect($media)->toHaveCount(1)
        ->and($media->first()->nombre_original)->toBe('remision-buena.pdf');
});

test('corrige la factura ligada por otra de la misma OC', function () {
    $otraFactura = Factura::factory()->create([
        'orden_compra_id' => $this->oc->id,
        'estatus' => 'pendiente_recepcion',
    ]);

    $this->actingAs($this->user)
        ->post("/admin/costos/entregas/{$this->entrega->id}", editar($this->entrega, [
            'factura_id' => $otraFactura->id,
        ]))
        ->assertSessionHasNoErrors();

    expect($this->entrega->fresh()->factura_id)->toBe($otraFactura->id);
});

test('no acepta una factura de otra orden de compra', function () {
    $ajena = Factura::factory()->create(['estatus' => 'pendiente_recepcion']);

    $this->actingAs($this->user)
        ->post("/admin/costos/entregas/{$this->entrega->id}", editar($this->entrega, [
            'factura_id' => $ajena->id,
        ]))
        ->assertSessionHasErrors('factura_id');

    expect($this->entrega->fresh()->factura_id)->toBe($this->factura->id);
});

test('no se puede re-ligar a una factura que ya avanzo', function () {
    $avanzada = Factura::factory()->create([
        'orden_compra_id' => $this->oc->id,
        'estatus' => 'pendiente_aprobacion',
        'aprobada_costos' => true,
    ]);

    $this->actingAs($this->user)
        ->post("/admin/costos/entregas/{$this->entrega->id}", editar($this->entrega, [
            'factura_id' => $avanzada->id,
        ]))
        ->assertSessionHasErrors('factura_id');

    expect($this->entrega->fresh()->factura_id)->toBe($this->factura->id);
});

test('no se puede mover la recepcion si la factura actual ya fue aprobada', function () {
    $this->factura->update(['aprobada_costos' => true]);
    $otra = Factura::factory()->create([
        'orden_compra_id' => $this->oc->id,
        'estatus' => 'pendiente_recepcion',
    ]);

    $this->actingAs($this->user)
        ->post("/admin/costos/entregas/{$this->entrega->id}", editar($this->entrega, [
            'factura_id' => $otra->id,
        ]))
        ->assertSessionHasErrors('factura_id');

    expect($this->entrega->fresh()->factura_id)->toBe($this->factura->id);
});

test('re-ligar mueve el completamente entregada de una factura a la otra', function () {
    $this->factura->update(['completamente_entregada' => true]);
    $this->entrega->update(['completa_factura' => true]);

    $otraFactura = Factura::factory()->create([
        'orden_compra_id' => $this->oc->id,
        'estatus' => 'pendiente_recepcion',
        'completamente_entregada' => false,
    ]);

    $this->actingAs($this->user)
        ->post("/admin/costos/entregas/{$this->entrega->id}", editar($this->entrega, [
            'factura_id' => $otraFactura->id,
            'completa_factura' => true,
        ]))
        ->assertSessionHasNoErrors();

    expect($this->factura->fresh()->completamente_entregada)->toBeFalse()
        ->and($otraFactura->fresh()->completamente_entregada)->toBeTrue();
});

test('desmarcar que completa la factura la regresa a pendiente de recepcion', function () {
    $this->factura->update([
        'completamente_entregada' => true,
        'estatus' => 'pendiente_aprobacion',
    ]);
    $this->entrega->update(['completa_factura' => true]);

    $this->actingAs($this->user)
        ->post("/admin/costos/entregas/{$this->entrega->id}", editar($this->entrega, [
            'completa_factura' => false,
        ]))
        ->assertSessionHasNoErrors();

    $fresca = $this->factura->fresh();

    expect($fresca->completamente_entregada)->toBeFalse()
        ->and($fresca->estatus->value)->toBe('pendiente_recepcion')
        ->and($this->entrega->fresh()->completa_factura)->toBeFalse();
});

test('otra recepcion vigente sostiene el completamente entregada de la factura', function () {
    $this->factura->update(['completamente_entregada' => true]);
    $this->entrega->update(['completa_factura' => true]);

    Entrega::factory()->create([
        'orden_compra_id' => $this->oc->id,
        'factura_id' => $this->factura->id,
        'recibido_por' => $this->user->id,
        'completa_factura' => true,
    ]);

    $this->actingAs($this->user)
        ->post("/admin/costos/entregas/{$this->entrega->id}", editar($this->entrega, [
            'completa_factura' => false,
        ]))
        ->assertSessionHasNoErrors();

    expect($this->factura->fresh()->completamente_entregada)->toBeTrue();
});

test('editar no toca cantidades ni el estatus de la orden', function () {
    $this->entrega->detalles()->create([
        'orden_compra_detalle_id' => $this->partida->id,
        'cantidad_recibida' => 10,
        'precio_unitario' => 100,
    ]);

    $estatusAntes = $this->oc->fresh()->estatus->value;

    $this->actingAs($this->user)
        ->post("/admin/costos/entregas/{$this->entrega->id}", editar($this->entrega))
        ->assertSessionHasNoErrors();

    expect($this->oc->fresh()->estatus->value)->toBe($estatusAntes)
        ->and((float) $this->entrega->fresh()->detalles()->sole()->cantidad_recibida)->toBe(10.0);
});
