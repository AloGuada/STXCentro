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

    $this->entrega = Entrega::factory()->create([
        'orden_compra_id' => $this->oc->id,
        'recibido_por' => $this->user->id,
        'fecha_entrega' => '2026-02-17',
        'observaciones' => 'Capturada de volada',
    ]);
});

/** @param array<string, mixed> $extra */
function editar(Entrega $entrega, array $extra = []): array
{
    return array_merge([
        'fecha_entrega' => '2026-02-20',
        'recibido_por' => test()->otro->id,
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

test('la fecha y quien recibio son obligatorios', function () {
    $this->actingAs($this->user)
        ->post("/admin/costos/entregas/{$this->entrega->id}", ['observaciones' => 'Solo esto'])
        ->assertSessionHasErrors(['fecha_entrega', 'recibido_por']);
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
