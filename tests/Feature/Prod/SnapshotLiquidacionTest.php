<?php

use App\Models\Concepto;
use App\Models\Prod\Asistencia;
use App\Models\Prod\Catalogo;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoEmpleado;
use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\GrupoPrecioConcepto;
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

    $this->catalogo = Catalogo::factory()->create();
    $this->pieza = Concepto::factory()->create([
        'obra_id' => $this->catalogo->obra_id,
        'catalogo_id' => $this->catalogo->id,
        'marca' => 'V-01',
        'descripcion' => 'Viga original',
        'cantidad' => 10,
        'peso_unitario' => 100,
        'longitud' => 6000,
    ]);

    $grupoPrecio = GrupoPrecio::factory()->create([
        'obra_id' => $this->catalogo->obra_id,
        'precio_kilo' => 10,
    ]);
    GrupoPrecioConcepto::create([
        'grupo_precio_id' => $grupoPrecio->id,
        'concepto_id' => $this->pieza->id,
    ]);

    $this->grupo = GrupoTrabajo::factory()->create();
    GrupoEmpleado::factory()->create(['grupo_trabajo_id' => $this->grupo->id, 'porcentaje' => 100]);

    $this->destajo = Destajo::factory()->create([
        'anio' => 2026,
        'semana' => 6,
        'fecha_inicio' => '2026-02-02',
        'fecha_fin' => '2026-02-08',
    ]);
});

/** Cierra la semana con 4 piezas capturadas. */
function cerrarConProduccion(int $cantidad = 4, float $porcentaje = 100): void
{
    Registro::create([
        'fecha' => '2026-02-04',
        'concepto_id' => test()->pieza->id,
        'grupo_trabajo_id' => test()->grupo->id,
        'cantidad' => $cantidad,
        'porcentaje' => $porcentaje,
    ]);

    app(GeneradorLiquidaciones::class)->generar(test()->destajo);
}

describe('snapshot del renglon liquidado', function () {
    test('guarda marca, descripcion, peso, longitud y obra', function () {
        cerrarConProduccion();

        $detalle = $this->destajo->liquidaciones()->firstOrFail()->detalles()->firstOrFail();

        expect($detalle->marca)->toBe('V-01')
            ->and($detalle->descripcion)->toBe('Viga original')
            ->and((float) $detalle->peso_unitario)->toBe(100.0)
            ->and($detalle->longitud)->toBe(6000)
            ->and($detalle->obra_id)->toBe($this->catalogo->obra_id);
    });

    test('editar la pieza no cambia la orden de pago ya cerrada', function () {
        cerrarConProduccion();

        $this->pieza->update([
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
            ->and($pieza['largo'])->toBe(6000)
            // 4 pz x 100 kg x $10 = $4,000
            ->and($pieza['importe'])->toBe(4000.0);
    });

    test('borrar la pieza no rompe la orden de pago', function () {
        cerrarConProduccion();

        $this->pieza->grupoPrecioConceptos()->delete();
        Registro::query()->delete();
        $this->pieza->delete();

        $grupos = app(GeneradorLiquidaciones::class)->ordenDePago($this->destajo->fresh());
        $pieza = $grupos[0]['piezas'][0];

        expect($pieza['marca'])->toBe('V-01')
            ->and($pieza['importe'])->toBe(4000.0);
    });

    test('cambiar el precio del grupo no re-precia la semana cerrada', function () {
        cerrarConProduccion();

        GrupoPrecio::query()->update(['precio_kilo' => 99]);

        $grupos = app(GeneradorLiquidaciones::class)->ordenDePago($this->destajo->fresh());

        expect($grupos[0]['piezas'][0]['precio_kilo'])->toBe(10.0)
            ->and($grupos[0]['total_produccion'])->toBe(4000.0);
    });
});

describe('conteo de lo pagado', function () {
    test('cerrar la semana no duplica el acumulado', function () {
        cerrarConProduccion();

        // 4 pagadas de 10: quedan 6, contadas una sola vez.
        expect(app(AvanceDePiezas::class)->disponible($this->pieza->fresh()))->toBe(6.0);
    });

    test('lo pagado sobrevive al borrado de los registros', function () {
        cerrarConProduccion();
        Registro::query()->delete();

        expect(app(AvanceDePiezas::class)->disponible($this->pieza->fresh()))->toBe(6.0);
    });

    test('suma lo liquidado con lo capturado en la semana abierta', function () {
        cerrarConProduccion();

        $abierta = Destajo::factory()->create([
            'anio' => 2026,
            'semana' => 7,
            'fecha_inicio' => '2026-02-09',
            'fecha_fin' => '2026-02-15',
        ]);

        Registro::create([
            'fecha' => '2026-02-10',
            'concepto_id' => $this->pieza->id,
            'grupo_trabajo_id' => $this->grupo->id,
            'cantidad' => 3,
            'porcentaje' => 100,
        ]);

        expect(app(AvanceDePiezas::class)->disponible($this->pieza->fresh()))->toBe(3.0)
            ->and($abierta->cerrado)->toBeFalse();
    });

    test('respeta las parcialidades al liquidar', function () {
        cerrarConProduccion(10, 60);

        // 10 al 60% = 6 equivalentes pagados; quedan 4.
        expect(app(AvanceDePiezas::class)->disponible($this->pieza->fresh()))->toBe(4.0);
    });

    test('el tope sigue vivo tras versionar el catalogo', function () {
        cerrarConProduccion();

        $v2 = app(VersionadorCatalogo::class)->nuevaVersion($this->catalogo);
        $copia = $v2->conceptos()->where('marca', 'V-01')->firstOrFail();

        // La copia no tiene registros propios, pero la marca ya lleva 4 pagadas.
        expect(app(AvanceDePiezas::class)->disponible($copia))->toBe(6.0);
    });
});

test('la asistencia sigue siendo requisito para cerrar', function () {
    Registro::create([
        'fecha' => '2026-02-04',
        'concepto_id' => $this->pieza->id,
        'grupo_trabajo_id' => $this->grupo->id,
        'cantidad' => 2,
        'porcentaje' => 100,
    ]);

    $this->actingAs($this->user)
        ->post(route('admin.prod.destajos.cerrar', $this->destajo))
        ->assertSessionHasErrors('error');

    foreach ($this->grupo->empleados as $empleado) {
        for ($dia = 2; $dia <= 8; $dia++) {
            Asistencia::factory()->create([
                'destajo_id' => $this->destajo->id,
                'grupo_empleado_id' => $empleado->id,
                'fecha' => sprintf('2026-02-%02d', $dia),
            ]);
        }
    }

    $this->actingAs($this->user)
        ->post(route('admin.prod.destajos.cerrar', $this->destajo))
        ->assertSessionHasNoErrors();

    expect($this->destajo->fresh()->cerrado)->toBeTrue();
});

test('renombrar la marca al versionar no reinicia el acumulado', function () {
    cerrarConProduccion();

    $v2 = app(VersionadorCatalogo::class)->nuevaVersion($this->catalogo);
    $copia = $v2->conceptos()->where('marca', 'V-01')->firstOrFail();
    $copia->update(['marca' => 'V-99']);

    // El linaje sostiene el conteo: ya se pagaron 4 de 10, quedan 6.
    expect($copia->fresh()->concepto_origen_id)->toBe($this->pieza->id)
        ->and(app(AvanceDePiezas::class)->disponible($copia->fresh()))->toBe(6.0);
});

test('el linaje encadena varias versiones', function () {
    cerrarConProduccion();

    $v2 = app(VersionadorCatalogo::class)->nuevaVersion($this->catalogo);
    $v2->conceptos()->firstOrFail()->update(['marca' => 'V-50']);

    $v3 = app(VersionadorCatalogo::class)->nuevaVersion($v2);
    $nieta = $v3->conceptos()->firstOrFail();
    $nieta->update(['marca' => 'V-99']);

    // Tres nombres distintos, un solo linaje: el pagado sigue contando.
    expect(app(AvanceDePiezas::class)->disponible($nieta->fresh()))->toBe(6.0);
});
