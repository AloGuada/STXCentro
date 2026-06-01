<?php

use App\Models\Costos\Cancelacion;
use App\Models\Costos\Factura;
use App\Models\Costos\Pago;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'costos.pagos.cancelar', 'guard_name' => 'web']);
    $this->user->givePermissionTo('costos.pagos.cancelar');
});

test('cancela pago programado y registra motivo en costos_cancelaciones', function () {
    $pago = Pago::factory()->programado()->create();

    $this->actingAs($this->user)
        ->post(route('admin.costos.pagos.cancelar', $pago), [
            'motivo' => 'Cambio de proveedor autorizado por gerencia',
        ])
        ->assertRedirect();

    $pago->refresh();
    expect($pago->estatus->value)->toBe('cancelado');

    $cancelacion = Cancelacion::where('cancelable_type', Pago::class)
        ->where('cancelable_id', $pago->id)
        ->first();
    expect($cancelacion)->not->toBeNull();
    expect($cancelacion->motivo)->toBe('Cambio de proveedor autorizado por gerencia');
    expect($cancelacion->usuario_id)->toBe($this->user->id);
});

test('no cancela pago sin motivo', function () {
    $pago = Pago::factory()->programado()->create();

    $this->actingAs($this->user)
        ->post(route('admin.costos.pagos.cancelar', $pago), [])
        ->assertSessionHasErrors(['motivo']);

    expect($pago->fresh()->estatus->value)->toBe('programado');
});

test('no cancela pago con motivo demasiado corto', function () {
    $pago = Pago::factory()->programado()->create();

    $this->actingAs($this->user)
        ->post(route('admin.costos.pagos.cancelar', $pago), ['motivo' => 'no'])
        ->assertSessionHasErrors(['motivo']);
});

test('no cancela pago ya pagado', function () {
    $pago = Pago::factory()->create(['estatus' => 'pagado']);

    $this->actingAs($this->user)
        ->post(route('admin.costos.pagos.cancelar', $pago), ['motivo' => 'Intento inválido de cancelar'])
        ->assertSessionHasErrors(['estatus']);
});

test('no cancela pago con comprobante subido', function () {
    Storage::fake('public');
    $pago = Pago::factory()->programado()->create();
    $pago->media()->create([
        'descripcion' => 'comprobante',
        'nombre_original' => 'comp.pdf',
        'path' => 'costos/pagos/comprobantes/fake.pdf',
        'mime' => 'application/pdf',
        'size' => 100,
    ]);

    $this->actingAs($this->user)
        ->post(route('admin.costos.pagos.cancelar', $pago), ['motivo' => 'Motivo suficientemente largo'])
        ->assertSessionHasErrors(['estatus']);
});

test('sin permiso no puede cancelar pago', function () {
    $otro = User::factory()->create();
    $pago = Pago::factory()->programado()->create();

    $this->actingAs($otro)
        ->post(route('admin.costos.pagos.cancelar', $pago), ['motivo' => 'Motivo suficientemente largo'])
        ->assertForbidden();
});

test('cancelar factura registra entrada en costos_cancelaciones', function () {
    Permission::firstOrCreate(['name' => 'costos.facturas.cancelar', 'guard_name' => 'web']);
    $this->user->givePermissionTo('costos.facturas.cancelar');

    $factura = Factura::factory()->create(['estatus' => 'pendiente_aprobacion']);

    $this->actingAs($this->user)
        ->post(route('admin.costos.facturas.cancelar', $factura), [
            'motivo' => 'Factura duplicada detectada por contabilidad',
        ])
        ->assertRedirect();

    expect(
        Cancelacion::where('cancelable_type', Factura::class)
            ->where('cancelable_id', $factura->id)
            ->where('motivo', 'Factura duplicada detectada por contabilidad')
            ->exists()
    )->toBeTrue();
});
