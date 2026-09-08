<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Costos\UsoCfdi;
use App\Models\Departamento;
use App\Models\Obra;
use App\Models\Proveedor;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'costos.requisiciones.crear', 'guard_name' => 'web']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('costos.requisiciones.crear');

    $this->depto = Departamento::factory()->create();
    $this->obra = Obra::factory()->create();
    $this->rubro = ObraRubro::factory()->create(['obra_id' => $this->obra->id]);
    $this->uso = UsoCfdi::factory()->create();
    $this->req = Requisicion::factory()->create([
        'departamento_id' => $this->depto->id, 'obra_id' => $this->obra->id, 'estatus' => 'borrador',
    ]);
    $this->detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $this->req->id, 'cantidad' => 10,
        'obra_rubro_id' => $this->rubro->id, 'uso_cfdi_id' => $this->uso->id,
    ]);
});

function seleccionDe(RequisicionDetalle $detalle, float $cantidad): RequisicionSeleccion
{
    $proveedor = Proveedor::factory()->create();
    $cot = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id, 'proveedor_id' => $proveedor->id,
        'precio_unitario' => 50, 'moneda' => 'mxn',
    ]);

    return RequisicionSeleccion::create([
        'requisicion_detalle_id' => $detalle->id, 'cotizacion_precio_id' => $cot->id,
        'numero_oc' => 1, 'proveedor_id' => $proveedor->id, 'cantidad' => $cantidad,
    ]);
}

function editarCantidad(Requisicion $req, RequisicionDetalle $detalle, float $cantidad): array
{
    return [
        'departamento_id' => $req->departamento_id,
        'obra_id' => $req->obra_id,
        'detalles' => [
            ['id' => $detalle->id, 'descripcion' => $detalle->descripcion, 'unidad' => 'pza', 'cantidad' => $cantidad,
                'obra_rubro_id' => $detalle->obra_rubro_id, 'uso_cfdi_id' => $detalle->uso_cfdi_id],
        ],
        '_version' => $req->updated_at->toIso8601String(),
    ];
}

test('cambiar la cantidad de la partida arrastra la selección única del comparativo', function () {
    $sel = seleccionDe($this->detalle, 10);

    $this->actingAs($this->user)
        ->put("/admin/costos/requisiciones/{$this->req->id}", editarCantidad($this->req, $this->detalle, 25))
        ->assertRedirect();

    expect((float) $sel->refresh()->cantidad)->toBe(25.0);
});

test('con split entre proveedores la cantidad nueva se reparte en la misma proporción', function () {
    $a = seleccionDe($this->detalle, 7);
    $b = seleccionDe($this->detalle, 3);

    $this->actingAs($this->user)
        ->put("/admin/costos/requisiciones/{$this->req->id}", editarCantidad($this->req, $this->detalle, 20))
        ->assertRedirect();

    expect((float) $a->refresh()->cantidad)->toBe(14.0)
        ->and((float) $b->refresh()->cantidad)->toBe(6.0);
});

test('si la cantidad no cambia las selecciones quedan intactas', function () {
    $sel = seleccionDe($this->detalle, 4);

    $this->actingAs($this->user)
        ->put("/admin/costos/requisiciones/{$this->req->id}", editarCantidad($this->req, $this->detalle, 10))
        ->assertRedirect();

    expect((float) $sel->refresh()->cantidad)->toBe(4.0);
});
