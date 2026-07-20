<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\UsoCfdi;
use App\Models\Departamento;
use App\Models\Obra;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['costos.requisiciones.crear', 'costos.requisiciones.cotizar'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['costos.requisiciones.crear', 'costos.requisiciones.cotizar']);
    $this->depto = Departamento::factory()->create();
});

test('crear partida sin tipo_fiscal ni codigo_producto usa defaults', function () {
    $obra = Obra::factory()->create();
    $rubro = ObraRubro::factory()->create(['obra_id' => $obra->id]);
    $uso = UsoCfdi::factory()->create();

    $this->actingAs($this->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => $this->depto->id,
            'obra_id' => $obra->id,
            'detalles' => [
                ['descripcion' => 'Tornillos', 'unidad' => 'pza', 'cantidad' => 10, 'obra_rubro_id' => $rubro->id, 'uso_cfdi_id' => $uso->id],
            ],
        ])
        ->assertRedirect();

    $detalle = RequisicionDetalle::first();
    expect($detalle->tipo_fiscal->value)->toBe('mercancia');
    expect($detalle->codigo_producto)->toBeNull();
});

test('compras clasifica el tipo fiscal de la partida desde cotizacion', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id]);

    $this->actingAs($this->user)
        ->post("/admin/costos/requisiciones/detalles/{$detalle->id}/clasificacion", [
            'tipo_fiscal' => 'flete',
        ])
        ->assertRedirect();

    expect($detalle->refresh()->tipo_fiscal->value)->toBe('flete');
});

test('el codigo de producto se guarda por linea y por opcion en la cotizacion', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id]);
    $provA = \App\Models\Proveedor::factory()->create();
    $provB = \App\Models\Proveedor::factory()->create();
    $opA = \App\Models\Costos\RequisicionCotizacionOpcion::create(['requisicion_id' => $req->id, 'proveedor_id' => $provA->id, 'orden' => 1]);
    $opB = \App\Models\Costos\RequisicionCotizacionOpcion::create(['requisicion_id' => $req->id, 'proveedor_id' => $provB->id, 'orden' => 1]);

    foreach ([[$opA, 'COD-A', 100], [$opB, 'COD-B', 120]] as [$op, $cod, $precio]) {
        $this->actingAs($this->user)
            ->post('/admin/costos/requisiciones/cotizaciones', [
                'requisicion_detalle_id' => $detalle->id,
                'opcion_id' => $op->id,
                'precio_unitario' => $precio,
                'codigo_producto' => $cod,
                'moneda' => 'mxn',
            ])
            ->assertRedirect();
    }

    expect(\App\Models\Costos\RequisicionCotizacionPrecio::where('proveedor_id', $provA->id)->first()->codigo_producto)->toBe('COD-A');
    expect(\App\Models\Costos\RequisicionCotizacionPrecio::where('proveedor_id', $provB->id)->first()->codigo_producto)->toBe('COD-B');
});

test('duplicar copia partidas y cotizaciones en una nueva requisicion borrador', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'aprobada']);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'tipo_fiscal' => 'flete']);
    $prov = \App\Models\Proveedor::factory()->create();
    \App\Models\Costos\RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $prov->id,
        'precio_unitario' => 100,
        'codigo_producto' => 'COD-X',
    ]);

    $this->actingAs($this->user)
        ->post("/admin/costos/requisiciones/{$req->id}/duplicar")
        ->assertRedirect();

    $nueva = Requisicion::where('id', '!=', $req->id)->latest('id')->first();
    expect($nueva->estatus->value)->toBe('borrador');
    expect($nueva->folio)->not->toBe($req->folio);
    expect($nueva->detalles()->count())->toBe(1);

    $nuevoDetalle = $nueva->detalles()->first();
    expect($nuevoDetalle->tipo_fiscal->value)->toBe('flete');
    expect($nuevoDetalle->cotizaciones()->count())->toBe(1);
    expect($nuevoDetalle->cotizaciones()->first()->codigo_producto)->toBe('COD-X');

    // La original no se altera.
    expect($req->fresh()->estatus->value)->toBe('aprobada');
});

test('duplicar requiere permiso de cotizar (compras), no solo crear', function () {
    $solicitante = User::factory()->create();
    $solicitante->givePermissionTo('costos.requisiciones.crear');

    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'aprobada']);

    $this->actingAs($solicitante)
        ->post("/admin/costos/requisiciones/{$req->id}/duplicar")
        ->assertForbidden();

    expect(Requisicion::count())->toBe(1);
});

test('clasificar valida tipo_fiscal', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id]);

    $this->actingAs($this->user)
        ->post("/admin/costos/requisiciones/detalles/{$detalle->id}/clasificacion", [
            'tipo_fiscal' => 'invalido',
        ])
        ->assertSessionHasErrors('tipo_fiscal');
});

test('editar la requisicion no pisa la clasificacion capturada en cotizacion', function () {
    $obra = Obra::factory()->create();
    $rubro = ObraRubro::factory()->create(['obra_id' => $obra->id]);
    $uso = UsoCfdi::factory()->create();
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'obra_id' => $obra->id, 'estatus' => 'borrador']);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'obra_rubro_id' => $rubro->id,
        'uso_cfdi_id' => $uso->id,
        'tipo_fiscal' => 'flete',
    ]);

    $this->actingAs($this->user)
        ->put("/admin/costos/requisiciones/{$req->id}", [
            'departamento_id' => $this->depto->id,
            'obra_id' => $obra->id,
            'detalles' => [
                ['id' => $detalle->id, 'descripcion' => 'Editada', 'unidad' => 'pza', 'cantidad' => 5, 'obra_rubro_id' => $rubro->id, 'uso_cfdi_id' => $uso->id],
            ],
            '_version' => $req->updated_at->toIso8601String(),
        ])
        ->assertRedirect();

    $detalle->refresh();
    expect($detalle->descripcion)->toBe('Editada');
    expect($detalle->tipo_fiscal->value)->toBe('flete');
});
