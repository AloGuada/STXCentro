<?php

use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Liquidacion;
use App\Models\Prod\PagoExtra;
use App\Models\Prod\TipoPagoExtra;
use App\Models\User;
use App\Services\Prod\GeneradorLiquidaciones;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function tipoDescuento(string $descripcion = 'Prestamo'): TipoPagoExtra
{
    return TipoPagoExtra::create([
        'descripcion' => $descripcion,
        'orden' => 1,
        'desgloce' => false,
        'es_descuento' => true,
    ]);
}

function pagoExtraDe(TipoPagoExtra $tipo, Destajo $destajo, GrupoTrabajo $grupo, float $precio): PagoExtra
{
    return PagoExtra::create([
        'descripcion' => $tipo->descripcion,
        'tipo_id' => $tipo->id,
        'destajo_id' => $destajo->id,
        'grupo_trabajo_id' => $grupo->id,
        'precio' => $precio,
        'dias' => 1,
        'personas' => 1,
    ]);
}

test('el monto de un tipo descuento sale en negativo aunque el precio se capture positivo', function () {
    $destajo = Destajo::factory()->create();
    $grupo = GrupoTrabajo::factory()->create();

    $pago = pagoExtraDe(tipoDescuento(), $destajo, $grupo, 300.00);

    expect($pago->fresh()->monto)->toBe(-300.0);
});

test('el tipo que no es descuento sigue sumando', function () {
    $destajo = Destajo::factory()->create();
    $grupo = GrupoTrabajo::factory()->create();

    $tipo = TipoPagoExtra::create([
        'descripcion' => 'Bono',
        'orden' => 1,
        'desgloce' => false,
        'es_descuento' => false,
    ]);

    expect(pagoExtraDe($tipo, $destajo, $grupo, 300.00)->fresh()->monto)->toBe(300.0);
});

test('cerrar resta el descuento del total del grupo', function () {
    $marca = marcaConPiezas(20, ['peso_unitario' => 10.000]);
    tarifaDeMarca($marca, 5.0000);
    obraPagaProcesos($marca->obra_id);

    $grupo = GrupoTrabajo::factory()->create();
    $destajo = Destajo::factory()->create([
        'fecha_inicio' => '2026-02-03',
        'fecha_fin' => '2026-02-09',
    ]);

    capturarPiezas($marca->piezas, $grupo, '2026-02-05');

    // Un bono de 200 y un descuento de 300: los extras quedan en -100.
    $bono = TipoPagoExtra::create(['descripcion' => 'Bono', 'orden' => 1, 'desgloce' => false, 'es_descuento' => false]);
    pagoExtraDe($bono, $destajo, $grupo, 200.00);
    pagoExtraDe(tipoDescuento(), $destajo, $grupo, 300.00);

    $this->post(route('admin.prod.destajos.cerrar', $destajo))->assertRedirect();

    $liquidacion = Liquidacion::where('destajo_id', $destajo->id)->first();

    expect((float) $liquidacion->total_produccion)->toBe(1000.0)
        ->and((float) $liquidacion->total_extras)->toBe(-100.0)
        ->and((float) $liquidacion->total_final)->toBe(900.0);
});

test('la orden de pago imprime el descuento en negativo y marca la seccion', function () {
    $grupo = GrupoTrabajo::factory()->create();
    $destajo = Destajo::factory()->create([
        'fecha_inicio' => '2026-02-03',
        'fecha_fin' => '2026-02-09',
    ]);

    pagoExtraDe(tipoDescuento(), $destajo, $grupo, 250.00);

    $seccion = collect(app(GeneradorLiquidaciones::class)->ordenDePago($destajo)->first()['secciones'])
        ->firstWhere('tipo', 'Prestamo');

    expect($seccion['es_descuento'])->toBeTrue()
        ->and($seccion['subtotal'])->toBe(-250.0)
        ->and($seccion['pagos'][0]['importe'])->toBe(-250.0);
});

test('el catalogo guarda y edita la marca de descuento', function () {
    $this->post(route('admin.prod.tipos-pago-extra.store'), [
        'descripcion' => 'Herramienta perdida',
        'orden' => 3,
        'desgloce' => false,
        'es_descuento' => true,
    ])->assertRedirect(route('admin.prod.tipos-pago-extra.index'));

    $tipo = TipoPagoExtra::firstWhere('descripcion', 'Herramienta perdida');
    expect($tipo->es_descuento)->toBeTrue();

    $this->put(route('admin.prod.tipos-pago-extra.update', $tipo), [
        'descripcion' => 'Herramienta perdida',
        'orden' => 3,
        'desgloce' => false,
        'es_descuento' => false,
    ])->assertRedirect(route('admin.prod.tipos-pago-extra.index'));

    expect($tipo->fresh()->es_descuento)->toBeFalse();
});
