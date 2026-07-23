<?php

use App\Enums\Costos\RubroAfectadoEstatus;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Pago;
use App\Models\Costos\Requisicion;
use App\Models\Costos\SolicitudPago;
use App\Models\User;
use App\Services\Costos\ApartadoPresupuestal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

it('guarda el tipo de cambio de la requisición a nivel documento', function () {
    Permission::firstOrCreate(['name' => 'costos.requisiciones.cotizar', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->givePermissionTo('costos.requisiciones.cotizar');
    $req = Requisicion::factory()->create(['estatus' => 'borrador']);

    $this->actingAs($user)
        ->post("/admin/costos/requisiciones/{$req->id}/tipo-cambio", ['tipo_cambio' => 18.9])
        ->assertRedirect();

    expect((float) $req->fresh()->tipo_cambio)->toBe(18.9);
});

it('el comprobante de un pago en divisa captura el monto real y reconcilia el presupuesto', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());

    $rubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0, 'apartado' => 0]);
    $sp = SolicitudPago::factory()->aprobada()->create(['orden_compra_id' => null, 'tipo_moneda' => 'usd', 'tipo_cambio' => 18.5]);
    app(ApartadoPresupuestal::class)->aplicarCargo(
        entrada: $sp,
        obraRubroId: $rubro->id,
        monto: 100,
        estatus: RubroAfectadoEstatus::Aplicado,
        moneda: 'usd',
    ); // 100 USD × 18.5 = 1850 MXN (referencia)

    $pago = Pago::factory()->programado()->create([
        'pagable_type' => SolicitudPago::class,
        'pagable_id' => $sp->id,
        'monto_pago' => 100,
        'moneda' => 'usd',
        'tipo_cambio' => 18.5,
    ]);

    $this->post("/admin/costos/pagos/{$pago->id}/upload-comprobante", [
        'comprobante' => UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'),
        'monto_real_mxn' => 1900,
    ])->assertRedirect();

    expect($pago->fresh()->estatus->value)->toBe('pagado');
    expect((float) $pago->fresh()->monto_mxn)->toBe(1900.0);
    expect((float) $rubro->fresh()->acumulado)->toBe(1900.0); // reconciliado al MXN real
});

it('rechaza el comprobante en divisa sin monto real en MXN', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());

    $sp = SolicitudPago::factory()->create(['tipo_moneda' => 'usd']);
    $pago = Pago::factory()->programado()->create([
        'pagable_type' => SolicitudPago::class,
        'pagable_id' => $sp->id,
        'monto_pago' => 100,
        'moneda' => 'usd',
    ]);

    $this->post("/admin/costos/pagos/{$pago->id}/upload-comprobante", [
        'comprobante' => UploadedFile::fake()->create('c.pdf', 100, 'application/pdf'),
    ])->assertSessionHasErrors('monto_real_mxn');
});
