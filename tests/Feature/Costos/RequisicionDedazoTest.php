<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionOc;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Departamento;
use App\Models\Proveedor;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['costos.requisiciones.liberar', 'costos.requisiciones.cotizar'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $this->compras = User::factory()->create();
    $this->compras->givePermissionTo(['costos.requisiciones.liberar', 'costos.requisiciones.cotizar']);

    $this->depto = Departamento::factory()->create();
});

/**
 * Requisición en modo dedazo: cotizada, verificación gerencial hecha, un solo
 * proveedor con precio, selección al 100% y su OC definida.
 *
 * @return array{0: Requisicion, 1: ObraRubro, 2: Proveedor}
 */
function setupRequisicionDedazo(Departamento $depto, bool $controlVerificado = true): array
{
    $rubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $req = Requisicion::factory()->cotizada()->create([
        'departamento_id' => $depto->id,
        'modo_dedazo' => true,
        'control_verificado' => $controlVerificado,
        'control_at' => $controlVerificado ? now() : null,
    ]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'obra_rubro_id' => $rubro->id,
        'cantidad' => 5,
        'descripcion' => 'Tornillos',
        'unidad' => 'pza',
    ]);

    $proveedor = Proveedor::factory()->create();
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $proveedor->id,
        'precio_unitario' => 100.00,
    ]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'numero_oc' => 1,
        'proveedor_id' => $proveedor->id,
        'cantidad' => 5,
    ]);
    RequisicionOc::create([
        'requisicion_id' => $req->id,
        'proveedor_id' => $proveedor->id,
        'numero_oc' => 1,
        'modo_pago' => 'credito',
        'fecha_entrega' => now()->addDays(7)->format('Y-m-d'),
    ]);

    return [$req, $rubro, $proveedor];
}

test('dedazo convierte a OC directo: genera OC, afecta presupuesto y NO crea cadena', function () {
    [$req, $rubro] = setupRequisicionDedazo($this->depto);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/convertir-oc")
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(OrdenCompra::count())->toBe(1);

    $req->refresh();
    expect($req->estatus->value)->toBe('liberada');
    // No pasó por la cadena de aprobación.
    expect($req->cadenaAprobacion()->count())->toBe(0);

    // Impacto presupuestal aplicado (subtotal 5 × 100 = 500).
    $rubro->refresh();
    expect((float) $rubro->acumulado)->toBe(500.0);
});

test('dedazo funciona con un solo proveedor (sin exigir el mínimo de 3)', function () {
    [$req, , $proveedor] = setupRequisicionDedazo($this->depto);

    // Solo hay un proveedor cotizando; sin dedazo esto fallaría por el mínimo.
    expect(RequisicionCotizacionPrecio::where('proveedor_id', $proveedor->id)->count())->toBe(1);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/convertir-oc")
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(OrdenCompra::count())->toBe(1);
});

test('dedazo exige la verificación gerencial antes de convertir a OC', function () {
    [$req] = setupRequisicionDedazo($this->depto, controlVerificado: false);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/convertir-oc")
        ->assertSessionHasErrors(['control']);

    expect(OrdenCompra::count())->toBe(0);
});

test('convertir-oc solo aplica a requisiciones en modo dedazo', function () {
    [$req] = setupRequisicionDedazo($this->depto);
    $req->update(['modo_dedazo' => false]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/convertir-oc")
        ->assertSessionHasErrors(['modo_dedazo']);

    expect(OrdenCompra::count())->toBe(0);
});

test('setDedazo activa el modo en una requisición cotizada', function () {
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id, 'modo_dedazo' => false]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/dedazo", ['modo_dedazo' => true])
        ->assertRedirect();

    expect($req->fresh()->modo_dedazo)->toBeTrue();
});
