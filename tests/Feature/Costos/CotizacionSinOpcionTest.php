<?php

use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionOpcion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Proveedor;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['costos.requisiciones.ver', 'costos.requisiciones.cotizar'] as $permName) {
        Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['costos.requisiciones.ver', 'costos.requisiciones.cotizar']);

    $this->requisicion = Requisicion::factory()->create(['estatus' => 'borrador']);
    $this->detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $this->requisicion->id,
        'cantidad' => 10,
        'solo_cotizacion' => false,
    ]);
    $this->proveedor = Proveedor::factory()->create();
});

test('duplicar copia las opciones y las cotizaciones conservan su columna', function () {
    $opcion = RequisicionCotizacionOpcion::create([
        'requisicion_id' => $this->requisicion->id,
        'proveedor_id' => $this->proveedor->id,
        'etiqueta' => 'Marca A',
        'orden' => 1,
    ]);
    RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $this->detalle->id,
        'proveedor_id' => $this->proveedor->id,
        'opcion_id' => $opcion->id,
        'precio_unitario' => 1.43,
    ]);

    $this->actingAs($this->user)
        ->post(route('admin.costos.requisiciones.duplicar', $this->requisicion))
        ->assertRedirect();

    $nueva = Requisicion::where('id', '!=', $this->requisicion->id)->sole();

    // La opción se copió con su etiqueta y las cotizaciones apuntan a ella.
    $opcionNueva = $nueva->cotizacionOpciones()->sole();
    expect($opcionNueva->etiqueta)->toBe('Marca A')
        ->and($opcionNueva->proveedor_id)->toBe($this->proveedor->id)
        ->and($opcionNueva->id)->not->toBe($opcion->id);

    $cotizacionNueva = RequisicionCotizacionPrecio::whereIn(
        'requisicion_detalle_id',
        $nueva->detalles()->pluck('id'),
    )->sole();

    expect($cotizacionNueva->opcion_id)->toBe($opcionNueva->id);
});

test('no se puede seleccionar una cotizacion sin opcion', function () {
    $huerfana = RequisicionCotizacionPrecio::factory()->sinOpcion()->create([
        'requisicion_detalle_id' => $this->detalle->id,
        'proveedor_id' => $this->proveedor->id,
        'precio_unitario' => 1.43,
    ]);

    $this->actingAs($this->user)
        ->post(route('admin.costos.requisiciones.selecciones.store'), [
            'cotizacion_precio_id' => $huerfana->id,
            'cantidad' => 10,
            'numero_oc' => 1,
        ])
        ->assertSessionHasErrors('cotizacion_precio_id');

    expect(RequisicionSeleccion::count())->toBe(0);
});

describe('comando de reparacion', function () {
    test('fusiona la huerfana con la celda visible y le mueve la seleccion', function () {
        $opcion = RequisicionCotizacionOpcion::create([
            'requisicion_id' => $this->requisicion->id,
            'proveedor_id' => $this->proveedor->id,
            'orden' => 1,
        ]);

        // La copia muerta que dejó duplicar() y la celda que sí se dibuja.
        $huerfana = RequisicionCotizacionPrecio::factory()->sinOpcion()->create([
            'requisicion_detalle_id' => $this->detalle->id,
            'proveedor_id' => $this->proveedor->id,
            'precio_unitario' => 1.43,
        ]);
        $visible = RequisicionCotizacionPrecio::factory()->create([
            'requisicion_detalle_id' => $this->detalle->id,
            'proveedor_id' => $this->proveedor->id,
            'opcion_id' => $opcion->id,
            'precio_unitario' => 1.43,
        ]);

        $seleccion = RequisicionSeleccion::factory()->create([
            'requisicion_detalle_id' => $this->detalle->id,
            'cotizacion_precio_id' => $huerfana->id,
            'proveedor_id' => $this->proveedor->id,
            'cantidad' => 10,
        ]);

        $this->artisan('costos:reparar-cotizaciones-sin-opcion', ['--force' => true])
            ->assertSuccessful();

        expect($seleccion->fresh()->cotizacion_precio_id)->toBe($visible->id)
            ->and(RequisicionCotizacionPrecio::find($huerfana->id))->toBeNull();
    });

    test('si no hay celda visible le crea su opcion', function () {
        $huerfana = RequisicionCotizacionPrecio::factory()->sinOpcion()->create([
            'requisicion_detalle_id' => $this->detalle->id,
            'proveedor_id' => $this->proveedor->id,
            'precio_unitario' => 1.43,
        ]);

        $this->artisan('costos:reparar-cotizaciones-sin-opcion', ['--force' => true])
            ->assertSuccessful();

        $opcion = $this->requisicion->cotizacionOpciones()->sole();

        expect($huerfana->fresh()->opcion_id)->toBe($opcion->id)
            ->and($opcion->proveedor_id)->toBe($this->proveedor->id);
    });

    test('el dry-run no toca nada', function () {
        $huerfana = RequisicionCotizacionPrecio::factory()->sinOpcion()->create([
            'requisicion_detalle_id' => $this->detalle->id,
            'proveedor_id' => $this->proveedor->id,
        ]);

        $this->artisan('costos:reparar-cotizaciones-sin-opcion')
            ->expectsOutputToContain('DRY-RUN')
            ->assertSuccessful();

        expect($huerfana->fresh()->opcion_id)->toBeNull()
            ->and(RequisicionCotizacionOpcion::count())->toBe(0);
    });
});
