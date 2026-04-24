<?php

use App\Mail\FacturaAceptadaMail;
use App\Mail\PagoProgramadoMail;
use App\Models\Costos\Entrega;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Pago;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'costos.facturas.aceptar-contabilidad', 'guard_name' => 'web']);
    $this->user->givePermissionTo('costos.facturas.aceptar-contabilidad');
});

function crearFacturaAprobadaCostos(?Proveedor $proveedor = null): Factura
{
    $proveedor = $proveedor ?? Proveedor::factory()->create(['email' => 'proveedor@test.com']);

    $oc = OrdenCompra::factory()->pendienteEntrega()->create([
        'proveedor_id' => $proveedor->id,
    ]);

    $factura = Factura::factory()->pendientePago()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $proveedor->id,
    ]);

    Entrega::factory()->create(['factura_id' => $factura->id]);

    return $factura;
}

test('contabilidad acepta factura y crea pago programado', function () {
    Mail::fake();
    $factura = crearFacturaAprobadaCostos();

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/aceptar-contabilidad")
        ->assertRedirect();

    $factura->refresh();
    expect($factura->aceptada_contabilidad)->toBeTrue();
    expect($factura->aceptada_contabilidad_por)->toBe($this->user->id);
    expect($factura->aceptada_contabilidad_at)->not->toBeNull();

    expect(Pago::where('pagable_type', Factura::class)->where('pagable_id', $factura->id)->count())->toBe(1);

    $pago = Pago::where('pagable_type', Factura::class)->where('pagable_id', $factura->id)->first();
    expect($pago->estatus->value)->toBe('programado');
    expect($pago->fecha_pago_programada)->not->toBeNull();
    expect($pago->fecha_pago_programada->dayOfWeek)->toBe(Carbon::FRIDAY);
    expect((float) $pago->monto_pago)->toBe((float) $factura->total);
});

test('pago programado con dias credito ajusta al viernes', function () {
    Mail::fake();
    Carbon::setTestNow(Carbon::parse('2026-02-17')); // martes

    $proveedor = Proveedor::factory()->create(['dias_credito_default' => 30, 'email' => null]);
    $factura = crearFacturaAprobadaCostos($proveedor);

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/aceptar-contabilidad")
        ->assertRedirect();

    $pago = Pago::where('pagable_type', Factura::class)->where('pagable_id', $factura->id)->first();
    expect($pago->estatus->value)->toBe('programado');
    expect($pago->fecha_pago_programada->dayOfWeek)->toBe(Carbon::FRIDAY);
    // 2026-02-17 + 30 days = 2026-03-19 (jueves), next friday = 2026-03-20
    expect($pago->fecha_pago_programada->format('Y-m-d'))->toBe('2026-03-20');

    Carbon::setTestNow();
});

test('no acepta factura sin aprobacion costos', function () {
    $proveedor = Proveedor::factory()->create();
    $oc = OrdenCompra::factory()->pendienteEntrega()->create(['proveedor_id' => $proveedor->id]);
    $factura = Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $proveedor->id,
        'estatus' => 'pendiente_pago',
        'aprobada_costos' => false,
    ]);

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/aceptar-contabilidad")
        ->assertSessionHasErrors('aprobada_costos');
});

test('no acepta factura ya aceptada', function () {
    $factura = crearFacturaAprobadaCostos();
    $factura->update([
        'aceptada_contabilidad' => true,
        'aceptada_contabilidad_por' => $this->user->id,
        'aceptada_contabilidad_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/aceptar-contabilidad")
        ->assertSessionHasErrors('aceptada_contabilidad');
});

test('no acepta factura en estatus pendiente_entrega', function () {
    $factura = Factura::factory()->pendienteEntrega()->create();

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/aceptar-contabilidad")
        ->assertSessionHasErrors('estatus');
});

test('envia ambos emails al proveedor al aceptar', function () {
    Mail::fake();

    $proveedor = Proveedor::factory()->create(['email' => 'vendor@test.com']);
    $factura = crearFacturaAprobadaCostos($proveedor);

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/aceptar-contabilidad")
        ->assertRedirect();

    Mail::assertSent(FacturaAceptadaMail::class, function ($mail) {
        return $mail->hasTo('vendor@test.com');
    });

    Mail::assertSent(PagoProgramadoMail::class, function ($mail) {
        return $mail->hasTo('vendor@test.com');
    });
});

test('no envia emails si proveedor sin email', function () {
    Mail::fake();

    $proveedor = Proveedor::factory()->create(['email' => null]);
    $factura = crearFacturaAprobadaCostos($proveedor);

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/aceptar-contabilidad")
        ->assertRedirect();

    Mail::assertNotSent(FacturaAceptadaMail::class);
    Mail::assertNotSent(PagoProgramadoMail::class);
});

test('tipo pago credito cuando proveedor maneja credito', function () {
    Mail::fake();

    $proveedor = Proveedor::factory()->create(['maneja_credito' => true, 'email' => null]);
    $factura = crearFacturaAprobadaCostos($proveedor);

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/aceptar-contabilidad")
        ->assertRedirect();

    $pago = Pago::where('pagable_type', Factura::class)->where('pagable_id', $factura->id)->first();
    expect($pago->tipo_pago)->toBe('credito');
});

test('tipo pago contado cuando proveedor no maneja credito', function () {
    Mail::fake();

    $proveedor = Proveedor::factory()->create(['maneja_credito' => false, 'email' => null]);
    $factura = crearFacturaAprobadaCostos($proveedor);

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/aceptar-contabilidad")
        ->assertRedirect();

    $pago = Pago::where('pagable_type', Factura::class)->where('pagable_id', $factura->id)->first();
    expect($pago->tipo_pago)->toBe('contado');
});

test('usuario sin permiso no puede aceptar', function () {
    $userSinPermiso = User::factory()->create();
    $factura = crearFacturaAprobadaCostos();

    $this->actingAs($userSinPermiso)
        ->post("/admin/costos/facturas/{$factura->id}/aceptar-contabilidad")
        ->assertForbidden();
});

test('aceptacion recalcula OC estatus', function () {
    Mail::fake();
    $factura = crearFacturaAprobadaCostos();

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/aceptar-contabilidad")
        ->assertRedirect();

    $factura->refresh();
    $factura->ordenCompra->refresh();
    expect($factura->ordenCompra->estatus->value)->toBe('pendiente_pago');
});
