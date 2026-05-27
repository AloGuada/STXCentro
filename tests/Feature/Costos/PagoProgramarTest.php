<?php

use App\Mail\PagoProgramadoMail;
use App\Models\Costos\Factura;
use App\Models\Costos\Pago;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    $permission = Permission::firstOrCreate(['name' => 'costos.pagos.programar', 'guard_name' => 'web']);
    $this->user->givePermissionTo($permission);
});

function crearPagoPendiente(int $diasCredito = 0, ?string $email = 'proveedor@example.com'): Pago
{
    $proveedor = Proveedor::factory()->create([
        'dias_credito_default' => $diasCredito,
        'email' => $email,
    ]);

    $factura = Factura::factory()->pendientePago()->create([
        'proveedor_id' => $proveedor->id,
        'aceptada_contabilidad' => true,
        'aceptada_contabilidad_at' => now(),
    ]);

    return Pago::create([
        'pagable_type' => Factura::class,
        'pagable_id' => $factura->id,
        'monto_pago' => $factura->total,
        'moneda' => $factura->moneda,
        'tipo_pago' => $proveedor->maneja_credito ? 'credito' : 'contado',
        'estatus' => 'pendiente',
    ]);
}

test('programa pago pendiente con fecha en viernes', function () {
    Carbon::setTestNow(Carbon::parse('2026-02-17')); // martes

    $pago = crearPagoPendiente(diasCredito: 0);
    Mail::fake();

    $this->actingAs($this->user)
        ->post("/admin/costos/pagos/{$pago->id}/programar")
        ->assertRedirect();

    $pago->refresh();
    expect($pago->estatus->value)->toBe('programado');
    expect($pago->fecha_pago_programada->dayOfWeek)->toBe(Carbon::FRIDAY);

    Carbon::setTestNow();
});

test('programa pago con dias credito ajusta al viernes', function () {
    Carbon::setTestNow(Carbon::parse('2026-02-17')); // martes

    $pago = crearPagoPendiente(diasCredito: 30);
    Mail::fake();

    $this->actingAs($this->user)
        ->post("/admin/costos/pagos/{$pago->id}/programar")
        ->assertRedirect();

    $pago->refresh();
    expect($pago->estatus->value)->toBe('programado');
    expect($pago->fecha_pago_programada->dayOfWeek)->toBe(Carbon::FRIDAY);
    // 2026-02-17 + 30 days = 2026-03-19 (jueves), next friday = 2026-03-20
    expect($pago->fecha_pago_programada->format('Y-m-d'))->toBe('2026-03-20');

    Carbon::setTestNow();
});

test('no programa pago ya programado', function () {
    $pago = crearPagoPendiente();
    $pago->update(['estatus' => 'programado', 'fecha_pago_programada' => now()]);

    $this->actingAs($this->user)
        ->post("/admin/costos/pagos/{$pago->id}/programar")
        ->assertSessionHasErrors('estatus');
});

test('envia email al proveedor al programar', function () {
    Mail::fake();

    $pago = crearPagoPendiente(email: 'vendor@test.com');

    $this->actingAs($this->user)
        ->post("/admin/costos/pagos/{$pago->id}/programar")
        ->assertRedirect();

    Mail::assertSent(PagoProgramadoMail::class, function ($mail) {
        return $mail->hasTo('vendor@test.com');
    });
});

test('no envia email si proveedor sin email', function () {
    Mail::fake();

    $pago = crearPagoPendiente(email: null);

    $this->actingAs($this->user)
        ->post("/admin/costos/pagos/{$pago->id}/programar")
        ->assertRedirect();

    Mail::assertNotSent(PagoProgramadoMail::class);
});

test('usuario sin permiso no puede programar pago', function () {
    $userSinPermiso = User::factory()->create();
    $pago = crearPagoPendiente();

    $this->actingAs($userSinPermiso)
        ->post("/admin/costos/pagos/{$pago->id}/programar")
        ->assertForbidden();
});

test('solo permite comprobante cuando programado', function () {
    $pago = crearPagoPendiente();
    $pago->update(['tipo_pago' => 'contado']);

    $file = \Illuminate\Http\UploadedFile::fake()->create('comprobante.pdf', 100);

    $this->actingAs($this->user)
        ->post("/admin/costos/pagos/{$pago->id}/upload-comprobante", [
            'comprobante' => $file,
        ])
        ->assertSessionHasErrors('estatus');
});

test('solo permite parcializar cuando programado', function () {
    $pago = crearPagoPendiente();
    $pago->update(['tipo_pago' => 'credito']);

    $this->actingAs($this->user)
        ->post("/admin/costos/pagos/{$pago->id}/parcializar", [
            'monto' => $pago->monto_pago / 2,
            'fecha_programada' => '2026-03-20',
        ])
        ->assertSessionHasErrors('estatus');
});
