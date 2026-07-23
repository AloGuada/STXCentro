<?php

use App\Enums\Costos\RubroAfectadoEstatus;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Pago;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Costos\RubroAfectado;
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

it('el apartado de iniciar-aprobacion usa el tipo de cambio de la requisición, no el de referencia', function () {
    Permission::firstOrCreate(['name' => 'costos.requisiciones.cotizar', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->givePermissionTo('costos.requisiciones.cotizar');

    $rubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0, 'apartado' => 0]);
    $req = Requisicion::factory()->create(['estatus' => 'aprobada_interna', 'tipo_cambio' => 18.9]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'obra_rubro_id' => $rubro->id,
        'cantidad' => 5,
    ]);
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'precio_unitario' => 100,
        'moneda' => 'usd',
    ]);
    RequisicionCotizacionPrecio::factory()->count(2)->create(['requisicion_detalle_id' => $detalle->id]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'proveedor_id' => $precio->proveedor_id,
        'cantidad' => 5,
    ]);
    $req->ocs()->create(['proveedor_id' => $precio->proveedor_id, 'numero_oc' => 1]);

    $this->actingAs($user)
        ->post("/admin/costos/requisiciones/{$req->id}/iniciar-aprobacion")
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $afectado = RubroAfectado::query()
        ->where('obra_rubro_id', $rubro->id)
        ->where('estatus', RubroAfectadoEstatus::Apartado->value)
        ->first();

    expect($afectado)->not->toBeNull();
    expect($afectado->moneda)->toBe('usd');
    expect((float) $afectado->tipo_cambio)->toBe(18.9);
    expect((float) $afectado->monto_origen)->toBe(500.0); // 5 × 100 USD
    expect((float) $afectado->monto)->toBe(9450.0); // 500 × 18.9 MXN
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
