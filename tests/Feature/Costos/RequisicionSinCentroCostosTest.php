<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Presupuesto;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Costos\RubroAfectado;
use App\Models\Costos\UsoCfdi;
use App\Models\Departamento;
use App\Models\Obra;
use App\Models\Proveedor;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['costos.requisiciones.crear', 'costos.requisiciones.liberar'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['costos.requisiciones.crear', 'costos.requisiciones.liberar']);

    $this->depto = Departamento::factory()->create();
    $this->obra = Obra::factory()->create();
    $this->presupuesto = Presupuesto::factory()->paraObra($this->obra)->create();
    $this->uso = UsoCfdi::factory()->create(['clave' => 'G01']);
});

test('crea una requisición sin obra: sin presupuesto y con partidas sin centro de costos', function () {
    $this->actingAs($this->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => $this->depto->id,
            'sin_centro_costos' => true,
            'detalles' => [
                ['descripcion' => 'Acero', 'unidad' => 'kg', 'cantidad' => 10, 'uso_cfdi_id' => $this->uso->id],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $requisicion = Requisicion::first();
    expect($requisicion->sin_centro_costos)->toBeTrue();
    expect($requisicion->presupuesto_id)->toBeNull();
    expect(RequisicionDetalle::first()->obra_rubro_id)->toBeNull();
});

test('acepta el payload del formulario, que manda los campos presupuestales vacíos', function () {
    $this->actingAs($this->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => $this->depto->id,
            'sin_centro_costos' => '1',
            'presupuesto_id' => '',
            'detalles' => [
                ['descripcion' => 'Acero', 'unidad' => 'kg', 'cantidad' => 10, 'obra_rubro_id' => '', 'uso_cfdi_id' => $this->uso->id],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Requisicion::first()->sin_centro_costos)->toBeTrue();
});

test('sin el flag, la partida sigue exigiendo centro de costos', function () {
    $this->actingAs($this->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => $this->depto->id,
            'detalles' => [
                ['descripcion' => 'Acero', 'unidad' => 'kg', 'cantidad' => 10, 'uso_cfdi_id' => $this->uso->id],
            ],
        ])
        ->assertSessionHasErrors(['detalles.0.obra_rubro_id']);
});

test('una requisición sin obra no admite presupuesto ni centro de costos', function () {
    $rubro = ObraRubro::factory()->create(['presupuesto_id' => $this->presupuesto->id]);

    $this->actingAs($this->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => $this->depto->id,
            'sin_centro_costos' => true,
            'presupuesto_id' => $this->presupuesto->id,
            'detalles' => [
                ['descripcion' => 'Acero', 'unidad' => 'kg', 'cantidad' => 10, 'obra_rubro_id' => $rubro->id, 'uso_cfdi_id' => $this->uso->id],
            ],
        ])
        ->assertSessionHasErrors(['presupuesto_id', 'detalles.0.obra_rubro_id']);
});

test('liberar una requisición sin obra genera la OC sin afectar ningún presupuesto', function (bool $manejaCredito) {
    $req = Requisicion::factory()->aprobada()->create([
        'departamento_id' => $this->depto->id,
        'obra_id' => null,
        'presupuesto_id' => null,
        'sin_centro_costos' => true,
    ]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'obra_rubro_id' => null,
        'uso_cfdi_id' => $this->uso->id,
        'cantidad' => 5,
    ]);
    // Contado dispara además la solicitud de pago, que copia las partidas.
    $proveedor = Proveedor::factory()->create(['maneja_credito' => $manejaCredito]);
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $proveedor->id,
        'precio_unitario' => 100,
    ]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'numero_oc' => 1,
        'proveedor_id' => $proveedor->id,
        'cantidad' => 5,
    ]);

    $this->actingAs($this->user)
        ->post("/admin/costos/requisiciones/{$req->id}/liberar", [
            'ocs' => [
                ['proveedor_id' => $proveedor->id, 'numero_oc' => 1, 'modo_pago' => $manejaCredito ? 'credito' : 'contado', 'moneda' => 'mxn'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $oc = OrdenCompra::first();
    expect($oc)->not->toBeNull();
    expect($oc->detalles()->first()->obra_rubro_id)->toBeNull();
    expect(RubroAfectado::count())->toBe(0);
})->with([
    'proveedor a crédito' => true,
    'proveedor de contado' => false,
]);
