<?php

use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoEmpleado;
use App\Models\Prod\GrupoPrecioProceso;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Registro;
use App\Models\User;
use App\Services\Prod\AvanceDePiezas;
use App\Services\Prod\GeneradorLiquidaciones;
use App\Services\Prod\VersionadorCatalogo;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->user = User::factory()->create();
    Auth::login($this->user);

    // Un modelo de 10 piezas de 100 kg a $10/kg: cada QS pagado vale $1,000.
    $this->marca = marcaConPiezas(10, [
        'marca' => 'V-01',
        'descripcion' => 'Viga original',
        'peso_unitario' => 100,
        'longitud' => 6000,
    ]);
    $this->catalogo = $this->marca->catalogo;
    $this->soldadura = proceso();

    obraPagaProcesos($this->marca->obra_id, $this->soldadura);
    $this->grupoPrecio = tarifaDeMarca($this->marca, 10);

    $this->grupo = GrupoTrabajo::factory()->create();
    GrupoEmpleado::factory()->create(['grupo_trabajo_id' => $this->grupo->id]);

    $this->destajo = Destajo::factory()->create([
        'anio' => 2026,
        'semana' => 6,
        'fecha_inicio' => '2026-02-02',
        'fecha_fin' => '2026-02-08',
    ]);
});

/** Cierra la semana con las primeras N piezas capturadas. */
function cerrarConProduccion(int $piezas = 4, float $porcentaje = 100): void
{
    capturarPiezas(
        test()->marca->piezas->take($piezas),
        test()->grupo,
        '2026-02-04',
        $porcentaje,
    );

    app(GeneradorLiquidaciones::class)->generar(test()->destajo);
}

/** Fraccion de pieza que le queda al primer QS del modelo. */
function disponibleDeLaPrimera(): float
{
    return app(AvanceDePiezas::class)->disponible(
        test()->marca->piezas->first()->fresh(),
        test()->soldadura->id,
    );
}

describe('snapshot del renglon liquidado', function () {
    test('guarda qs, marca, proceso, descripcion, peso, longitud y obra', function () {
        cerrarConProduccion();

        $detalle = $this->destajo->liquidaciones()->firstOrFail()->detalles()->firstOrFail();

        expect($detalle->marca)->toBe('V-01')
            ->and($detalle->qs)->toBe($this->marca->piezas->first()->qs)
            ->and($detalle->proceso_nombre)->toBe('Soldadura')
            ->and($detalle->descripcion)->toBe('Viga original')
            ->and((float) $detalle->peso_unitario)->toBe(100.0)
            ->and($detalle->longitud)->toBe(6000)
            ->and($detalle->obra_id)->toBe($this->marca->obra_id);
    });

    test('un renglon por pieza pagada', function () {
        cerrarConProduccion();

        expect($this->destajo->liquidaciones()->firstOrFail()->detalles()->count())->toBe(4);
    });

    test('editar la marca no cambia la orden de pago ya cerrada', function () {
        cerrarConProduccion();

        $this->marca->update([
            'marca' => 'V-99',
            'descripcion' => 'Viga corregida',
            'peso_unitario' => 250,
            'longitud' => 12000,
        ]);

        $grupos = app(GeneradorLiquidaciones::class)->ordenDePago($this->destajo->fresh());
        $pieza = $grupos[0]['piezas'][0];

        expect($pieza['marca'])->toBe('V-01')
            ->and($pieza['descripcion'])->toBe('Viga original')
            ->and($pieza['peso_unitario'])->toBe(100.0)
            ->and($pieza['largo'])->toBe(6000);
    });

    test('borrar el catalogo no rompe la orden de pago', function () {
        cerrarConProduccion();

        $this->marca->grupoPrecioConceptos()->delete();
        Registro::query()->delete();
        $this->marca->piezas()->delete();
        $this->marca->delete();

        $grupos = app(GeneradorLiquidaciones::class)->ordenDePago($this->destajo->fresh());
        $pieza = $grupos[0]['piezas'][0];

        expect($pieza['marca'])->toBe('V-01')
            ->and($pieza['importe'])->toBe(4000.0);
    });

    test('cambiar la tarifa no re-precia la semana cerrada', function () {
        cerrarConProduccion();

        GrupoPrecioProceso::query()->update(['precio_kilo' => 99]);

        $grupos = app(GeneradorLiquidaciones::class)->ordenDePago($this->destajo->fresh());

        expect($grupos[0]['piezas'][0]['precio_kilo'])->toBe(10.0)
            ->and($grupos[0]['total_produccion'])->toBe(4000.0);
    });

    test('la orden de pago agrupa por marca aunque el libro sea por pieza', function () {
        cerrarConProduccion();

        $grupos = app(GeneradorLiquidaciones::class)->ordenDePago($this->destajo->fresh());

        // Cuatro QS del mismo modelo y proceso salen en un solo renglon.
        expect($grupos[0]['piezas'])->toHaveCount(1)
            ->and($grupos[0]['piezas'][0]['pzs'])->toBe(4)
            ->and($grupos[0]['piezas'][0]['qs'])->toHaveCount(4);
    });
});

describe('conteo de lo pagado', function () {
    test('cerrar la semana no duplica el acumulado', function () {
        cerrarConProduccion();

        expect(disponibleDeLaPrimera())->toBe(0.0);
    });

    test('lo pagado sobrevive al borrado de los registros', function () {
        cerrarConProduccion();
        Registro::query()->delete();

        expect(disponibleDeLaPrimera())->toBe(0.0);
    });

    test('suma lo liquidado con lo capturado en la semana abierta', function () {
        cerrarConProduccion();

        Destajo::factory()->create([
            'anio' => 2026,
            'semana' => 7,
            'fecha_inicio' => '2026-02-09',
            'fecha_fin' => '2026-02-15',
        ]);

        // Una pieza que la semana cerrada no toco.
        $quinta = $this->marca->piezas[4];
        capturarPiezas([$quinta], $this->grupo, '2026-02-10');

        $avance = app(AvanceDePiezas::class);

        expect($avance->disponible($quinta->fresh(), $this->soldadura->id))->toBe(0.0)
            ->and($avance->disponible($this->marca->piezas[5]->fresh(), $this->soldadura->id))->toBe(1.0);
    });

    test('respeta las parcialidades al liquidar', function () {
        cerrarConProduccion(4, 60);

        expect(disponibleDeLaPrimera())->toBe(0.4);
    });

    test('el tope sigue vivo tras versionar el catalogo', function () {
        cerrarConProduccion();

        $v2 = app(VersionadorCatalogo::class)->nuevaVersion($this->catalogo);
        $copia = $v2->piezas()->where('qs', $this->marca->piezas->first()->qs)->firstOrFail();

        // La copia no tiene registros propios, pero su linaje ya esta pagado.
        expect(app(AvanceDePiezas::class)->disponible($copia, $this->soldadura->id))->toBe(0.0);
    });

    test('renombrar la marca al versionar no reinicia el acumulado', function () {
        cerrarConProduccion();

        $v2 = app(VersionadorCatalogo::class)->nuevaVersion($this->catalogo);
        $v2->conceptos()->update(['marca' => 'V-01-R']);

        $copia = $v2->piezas()->where('qs', $this->marca->piezas->first()->qs)->firstOrFail();

        expect(app(AvanceDePiezas::class)->disponible($copia, $this->soldadura->id))->toBe(0.0);
    });

    test('el linaje encadena varias versiones', function () {
        cerrarConProduccion();

        $v2 = app(VersionadorCatalogo::class)->nuevaVersion($this->catalogo);
        $v3 = app(VersionadorCatalogo::class)->nuevaVersion($v2);

        $copia = $v3->piezas()->where('qs', $this->marca->piezas->first()->qs)->firstOrFail();

        expect(app(AvanceDePiezas::class)->disponible($copia, $this->soldadura->id))->toBe(0.0);
    });
});

describe('snapshot con etapa', function () {
    test('el renglon congela la etapa junto con la marca', function () {
        $this->marca->update(['etapa' => 'FASE B']);

        cerrarConProduccion();

        $detalle = $this->destajo->liquidaciones()->firstOrFail()->detalles()->firstOrFail();

        expect($detalle->marca)->toBe('V-01')
            ->and($detalle->etapa)->toBe('FASE B');
    });

    test('cambiar la etapa no mueve la orden de pago ya cerrada', function () {
        $this->marca->update(['etapa' => '1']);

        cerrarConProduccion();

        $this->marca->update(['etapa' => '2']);

        $piezas = app(GeneradorLiquidaciones::class)->ordenDePago($this->destajo->fresh())->first()['piezas'];

        expect($piezas[0]['etapa'])->toBe('1');
    });
});
