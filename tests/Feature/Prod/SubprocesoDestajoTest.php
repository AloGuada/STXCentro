<?php

use App\Enums\Prod\TipoPago;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\GrupoPrecioSubproceso;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\LiquidacionDetalle;
use App\Models\Prod\Registro;
use App\Models\User;
use App\Services\Prod\AvanceDePiezas;
use App\Services\Prod\GeneradorLiquidaciones;
use App\Services\Prod\PendientesDeLiquidar;

/**
 * El destajo entero, visto desde un grupo de precios que paga por subproceso.
 *
 * La modalidad cambia tres cosas y este archivo las recorre completas: el paso
 * abre un tope propio dentro del proceso, el importe deja de mirar el peso, y
 * los kilos del renglon se van a cero para no triplicar el total de la semana.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->grupo = GrupoTrabajo::factory()->create();
    $this->destajo = Destajo::factory()->create([
        'cerrado' => false,
        'fecha_inicio' => '2026-03-02',
        'fecha_fin' => '2026-03-08',
    ]);

    // 10 piezas de 100 kg: el peso es alto a proposito, para que un importe que
    // se colara por la rama de kilos salte a la vista.
    $this->marca = marcaConPiezas(10, ['peso_unitario' => 100.000, 'marca' => 'TR-01']);
    obraPagaProcesos($this->marca->obra_id, proceso(), proceso('Pintura'));

    $this->grupoPrecio = grupoPorSubprocesos($this->marca, [
        'Armado' => 50.00,
        'Punteado' => 30.00,
        'Soldado final' => 120.00,
    ]);

    $this->armado = subproceso($this->grupoPrecio, 'Armado');
    $this->punteado = subproceso($this->grupoPrecio, 'Punteado');
});

/** Captura por HTTP, que es el unico camino que valida la modalidad. */
function capturarPaso(array $datos = []): Illuminate\Testing\TestResponse
{
    return test()->post(route('admin.prod.destajos.registros.store', test()->destajo), [
        'fecha' => '2026-03-04',
        'piezas' => [test()->marca->piezas->first()->id],
        'proceso_id' => proceso()->id,
        'grupo_trabajo_id' => test()->grupo->id,
        'porcentaje' => 100,
        ...$datos,
    ]);
}

describe('captura', function () {
    test('la pieza de un grupo por subproceso exige que se diga cual paso se hizo', function () {
        capturarPaso(['subproceso_id' => null]);

        expect(Registro::count())->toBe(0);
        expect(session('errors')->first('piezas'))->toContain('paga por subproceso');
    });

    test('la pieza de un grupo por kilo rechaza el subproceso', function () {
        $porKilo = marcaConPiezas(1, [
            'obra_id' => $this->marca->obra_id,
            'catalogo_id' => $this->marca->catalogo_id,
            'marca' => 'KG-01',
        ]);
        tarifaDeMarca($porKilo, 5.0);

        capturarPaso([
            'piezas' => [$porKilo->piezas->first()->id],
            'subproceso_id' => $this->armado->id,
        ]);

        expect(Registro::count())->toBe(0);
        expect(session('errors')->first('piezas'))->toContain('se paga por kilo');
    });

    test('el paso de otro grupo de precios no se puede cobrar en esta pieza', function () {
        $otraMarca = marcaConPiezas(1, [
            'obra_id' => $this->marca->obra_id,
            'catalogo_id' => $this->marca->catalogo_id,
            'marca' => 'TR-99',
        ]);
        $otroGrupo = grupoPorSubprocesos($otraMarca, ['Armado' => 9999.00]);

        capturarPaso(['subproceso_id' => subproceso($otroGrupo, 'Armado')->id]);

        expect(Registro::count())->toBe(0);
        expect(session('errors')->first('piezas'))->toContain('no es del grupo de precios');
    });

    test('el paso de otro proceso no se puede cobrar en este proceso', function () {
        capturarPaso([
            'proceso_id' => proceso('Pintura')->id,
            'subproceso_id' => $this->armado->id,
        ]);

        expect(Registro::count())->toBe(0);
        expect(session('errors')->first('piezas'))->toContain('no pertenece al proceso seleccionado');
    });

    test('el paso desactivado ya no admite captura nueva', function () {
        $this->armado->update(['activo' => false]);

        capturarPaso(['subproceso_id' => $this->armado->id]);

        expect(Registro::count())->toBe(0);
        expect(session('errors')->first('piezas'))->toContain('está desactivado');
    });

    test('el paso valido se guarda con su subproceso', function () {
        capturarPaso(['subproceso_id' => $this->armado->id]);

        expect(Registro::count())->toBe(1)
            ->and(Registro::first()->subproceso_id)->toBe($this->armado->id);
    });
});

describe('tope por paso', function () {
    test('armar al 100% no consume nada del punteado', function () {
        capturarPaso(['subproceso_id' => $this->armado->id]);
        capturarPaso(['subproceso_id' => $this->punteado->id]);

        expect(Registro::count())->toBe(2);
    });

    test('el mismo paso no se puede pagar dos veces al 100%', function () {
        capturarPaso(['subproceso_id' => $this->armado->id]);
        capturarPaso(['subproceso_id' => $this->armado->id]);

        expect(Registro::count())->toBe(1);
        expect(session('errors')->first('piezas'))
            ->toContain('ya está pagada al 100% en Soldadura / Armado');
    });

    test('el saldo del paso se puede cerrar en dos parcialidades', function () {
        capturarPaso(['subproceso_id' => $this->armado->id, 'porcentaje' => 60]);
        capturarPaso(['subproceso_id' => $this->armado->id, 'porcentaje' => 40]);

        expect(Registro::count())->toBe(2)
            ->and((float) Registro::sum('porcentaje'))->toBe(100.0);
    });

    test('pasarse del saldo del paso se rechaza', function () {
        capturarPaso(['subproceso_id' => $this->armado->id, 'porcentaje' => 60]);
        capturarPaso(['subproceso_id' => $this->armado->id, 'porcentaje' => 50]);

        expect(Registro::count())->toBe(1);
        expect(session('errors')->first('piezas'))->toContain('sólo tiene 40% por pagar');
    });

    test('el avance del paso es independiente del avance del proceso', function () {
        $pieza = $this->marca->piezas->first();

        capturarPaso(['subproceso_id' => $this->armado->id]);

        $avance = app(AvanceDePiezas::class);

        expect($avance->capturado($pieza, proceso()->id, $this->armado->id))->toBe(1.0)
            ->and($avance->capturado($pieza, proceso()->id, $this->punteado->id))->toBe(0.0)
            // Sin paso, la llave es la del proceso a secas: otro cajon distinto.
            ->and($avance->capturado($pieza, proceso()->id))->toBe(0.0);
    });
});

describe('liquidacion', function () {
    test('el importe es el precio fijo del paso, no el peso por la tarifa', function () {
        capturarPiezas($this->marca->piezas, $this->grupo, '2026-03-04', subproceso: $this->armado);

        app(GeneradorLiquidaciones::class)->generar($this->destajo);

        // 10 piezas x $50 de armado. Por kilo habrian sido 1000 kg x algo.
        expect((float) $this->destajo->liquidaciones()->first()->total_produccion)->toBe(500.0);
    });

    test('el porcentaje prorratea el precio fijo del paso', function () {
        capturarPiezas($this->marca->piezas->take(1), $this->grupo, '2026-03-04', 60, subproceso: $this->armado);

        app(GeneradorLiquidaciones::class)->generar($this->destajo);

        expect((float) $this->destajo->liquidaciones()->first()->total_produccion)->toBe(30.0);
    });

    test('tres pasos de la misma pieza son tres renglones, no uno al 300%', function () {
        $pieza = $this->marca->piezas->take(1);
        $soldado = subproceso($this->grupoPrecio, 'Soldado final');

        capturarPiezas($pieza, $this->grupo, '2026-03-04', subproceso: $this->armado);
        capturarPiezas($pieza, $this->grupo, '2026-03-05', subproceso: $this->punteado);
        capturarPiezas($pieza, $this->grupo, '2026-03-06', subproceso: $soldado);

        app(GeneradorLiquidaciones::class)->generar($this->destajo);

        $liquidacion = $this->destajo->liquidaciones()->with('detalles')->first();

        expect($liquidacion->detalles)->toHaveCount(3)
            ->and((float) $liquidacion->total_produccion)->toBe(200.0);
    });

    test('los kilos del renglon por subproceso van en cero para no inflar la semana', function () {
        $pieza = $this->marca->piezas->take(1);

        capturarPiezas($pieza, $this->grupo, '2026-03-04', subproceso: $this->armado);
        capturarPiezas($pieza, $this->grupo, '2026-03-05', subproceso: $this->punteado);

        app(GeneradorLiquidaciones::class)->generar($this->destajo);

        $liquidacion = $this->destajo->liquidaciones()->with('detalles')->first();

        // La pieza pesa 100 kg; contarla en los dos pasos habria dado 200.
        expect((float) $liquidacion->total_kilos)->toBe(0.0)
            ->and($liquidacion->detalles->every(fn ($d) => (float) $d->kilos === 0.0))->toBeTrue()
            // El peso sigue en el snapshot, solo no se acumula.
            ->and((float) $liquidacion->detalles->first()->peso_unitario)->toBe(100.0);
    });

    test('un grupo por kilo y uno por subproceso conviven en el mismo destajo', function () {
        $porKilo = marcaConPiezas(2, [
            'obra_id' => $this->marca->obra_id,
            'catalogo_id' => $this->marca->catalogo_id,
            'marca' => 'KG-01',
            'peso_unitario' => 10.000,
        ]);
        tarifaDeMarca($porKilo, 5.0);

        capturarPiezas($this->marca->piezas->take(2), $this->grupo, '2026-03-04', subproceso: $this->armado);
        capturarPiezas($porKilo->piezas, $this->grupo, '2026-03-04');

        app(GeneradorLiquidaciones::class)->generar($this->destajo);

        $liquidacion = $this->destajo->liquidaciones()->first();

        // 2 x $50 de armado + 2 piezas de 10 kg a $5 = 100 + 100.
        expect((float) $liquidacion->total_produccion)->toBe(200.0)
            // Solo pesan los renglones por kilo.
            ->and((float) $liquidacion->total_kilos)->toBe(20.0);
    });
});

describe('snapshot', function () {
    test('el renglon liquidado guarda el paso y su precio', function () {
        capturarPiezas($this->marca->piezas->take(1), $this->grupo, '2026-03-04', subproceso: $this->armado);

        app(GeneradorLiquidaciones::class)->generar($this->destajo);

        $detalle = LiquidacionDetalle::first();

        expect($detalle->subproceso_id)->toBe($this->armado->id)
            ->and($detalle->subproceso_nombre)->toBe('Armado')
            ->and((float) $detalle->precio_subproceso_aplicado)->toBe(50.0)
            ->and($detalle->precio_kilo_aplicado)->toBeNull();
    });

    test('renombrar o encarecer el paso no mueve lo ya pagado', function () {
        capturarPiezas($this->marca->piezas->take(1), $this->grupo, '2026-03-04', subproceso: $this->armado);

        app(GeneradorLiquidaciones::class)->generar($this->destajo);

        $this->armado->update(['nombre' => 'Armado v2', 'precio' => 9999.00]);

        $grupos = app(GeneradorLiquidaciones::class)->ordenDePago($this->destajo->fresh());

        expect($grupos[0]['piezas'][0]['subproceso'])->toBe('Armado')
            ->and($grupos[0]['piezas'][0]['precio_unitario'])->toBe(50.0)
            ->and($grupos[0]['piezas'][0]['unidad'])->toBe('pza')
            ->and($grupos[0]['total_produccion'])->toBe(50.0);
    });

    test('la orden de pago abierta anticipa lo mismo que la cerrada', function () {
        capturarPiezas($this->marca->piezas->take(3), $this->grupo, '2026-03-04', subproceso: $this->armado);

        $antes = app(GeneradorLiquidaciones::class)->ordenDePago($this->destajo);

        app(GeneradorLiquidaciones::class)->generar($this->destajo);

        $despues = app(GeneradorLiquidaciones::class)->ordenDePago($this->destajo->fresh());

        expect($antes[0]['total_produccion'])->toBe(150.0)
            ->and($despues[0]['total_produccion'])->toBe($antes[0]['total_produccion'])
            ->and($despues[0]['piezas'][0]['pzs'])->toBe(3);
    });

    test('la orden de pago separa los pasos aunque compartan marca y porcentaje', function () {
        $pieza = $this->marca->piezas->take(1);

        capturarPiezas($pieza, $this->grupo, '2026-03-04', subproceso: $this->armado);
        capturarPiezas($pieza, $this->grupo, '2026-03-05', subproceso: $this->punteado);

        $grupos = app(GeneradorLiquidaciones::class)->ordenDePago($this->destajo);

        // Juntarlos sumaria $50 y $30 bajo un solo $/pza que no existe.
        expect($grupos[0]['piezas'])->toHaveCount(2)
            ->and(collect($grupos[0]['piezas'])->pluck('subproceso')->sort()->values()->all())
            ->toBe(['Armado', 'Punteado']);
    });
});

describe('avisos antes de cerrar', function () {
    test('piezasSinPrecio senala el paso sin precio, no el proceso entero', function () {
        GrupoPrecioSubproceso::create([
            'grupo_precio_id' => $this->grupoPrecio->id,
            'proceso_id' => proceso()->id,
            'nombre' => 'Sin tarifa',
            'orden' => 9,
            'precio' => 0,
        ]);

        $sinTarifa = subproceso($this->grupoPrecio, 'Sin tarifa');
        $piezas = $this->marca->piezas;

        capturarPiezas($piezas->take(1), $this->grupo, '2026-03-04', subproceso: $this->armado);
        capturarPiezas($piezas->skip(1)->take(1), $this->grupo, '2026-03-04', subproceso: $sinTarifa);

        $avisos = app(GeneradorLiquidaciones::class)->piezasSinPrecio($this->destajo);

        expect($avisos)->toHaveCount(1)
            ->and($avisos->first()['subproceso'])->toBe('Sin tarifa')
            ->and($avisos->first()['proceso'])->toBe('Soldadura');
    });

    test('el saldo pendiente se arrastra por paso, no por proceso', function () {
        $pieza = $this->marca->piezas->take(1);

        capturarPiezas($pieza, $this->grupo, '2026-02-25', 60, subproceso: $this->armado);
        capturarPiezas($pieza, $this->grupo, '2026-02-25', 25, subproceso: $this->punteado);

        $pendientes = app(PendientesDeLiquidar::class)->paraDestajo($this->destajo);

        expect($pendientes)->toHaveCount(2);

        $porPaso = $pendientes->keyBy('subproceso');

        expect($porPaso['Armado']['saldo'])->toBe(0.4)
            ->and($porPaso['Punteado']['saldo'])->toBe(0.75)
            ->and($porPaso['Armado']['subproceso_id'])->toBe($this->armado->id);
    });
});

describe('modalidad del grupo', function () {
    test('no se puede cambiar la forma de pago de un grupo ya liquidado', function () {
        capturarPiezas($this->marca->piezas->take(1), $this->grupo, '2026-03-04', subproceso: $this->armado);

        app(GeneradorLiquidaciones::class)->generar($this->destajo);

        $this->put(route('admin.prod.grupo-precios.update', $this->grupoPrecio), [
            'obra_id' => $this->grupoPrecio->obra_id,
            'descripcion' => $this->grupoPrecio->descripcion,
            'tipo_pago' => 'kilo',
        ])->assertSessionHasErrors('tipo_pago');

        expect($this->grupoPrecio->fresh()->tipo_pago)->toBe(TipoPago::Subproceso);
    });

    test('un grupo sin produccion liquidada si puede cambiar de modalidad', function () {
        $limpio = GrupoPrecio::factory()->create([
            'obra_id' => $this->marca->obra_id,
            'tipo_pago' => TipoPago::Subproceso,
        ]);

        $this->put(route('admin.prod.grupo-precios.update', $limpio), [
            'obra_id' => $limpio->obra_id,
            'descripcion' => $limpio->descripcion,
            'tipo_pago' => 'kilo',
        ])->assertSessionHasNoErrors();

        expect($limpio->fresh()->tipo_pago)->toBe(TipoPago::Kilo);
    });

    test('quitar un paso con produccion lo desactiva en vez de borrarlo', function () {
        capturarPiezas($this->marca->piezas->take(1), $this->grupo, '2026-03-04', subproceso: $this->armado);

        $this->put(route('admin.prod.grupo-precios.update', $this->grupoPrecio), [
            'obra_id' => $this->grupoPrecio->obra_id,
            'descripcion' => $this->grupoPrecio->descripcion,
            'tipo_pago' => 'subproceso',
            // Se manda solo el punteado: armado y soldado final quedan fuera.
            'subprocesos' => [
                ['proceso_id' => proceso()->id, 'nombre' => 'Punteado', 'precio' => 35],
            ],
        ])->assertSessionHasNoErrors();

        expect($this->armado->fresh()->activo)->toBeFalse()
            // El soldado final nunca se uso, asi que ese si desaparece.
            ->and(GrupoPrecioSubproceso::where('nombre', 'Soldado final')->exists())->toBeFalse()
            ->and((float) subproceso($this->grupoPrecio->fresh(), 'Punteado')->precio)->toBe(35.0);
    });
});

describe('pantalla del destajo', function () {
    test('el renglon de produccion viaja con su paso para no juntar pagos distintos', function () {
        // La vista junta los QR de la misma marca en un solo renglon. Armado y
        // punteado se pagan distinto, asi que no pueden caer en el mismo.
        $piezas = $this->marca->piezas->take(2);
        capturarPiezas($piezas, $this->grupo, '2026-03-04', subproceso: $this->armado);
        capturarPiezas($piezas, $this->grupo, '2026-03-04', subproceso: $this->punteado);

        $this->get(route('admin.prod.destajos.show', $this->destajo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has("registrosPreview.{$this->grupo->id}", 4)
                ->where(
                    "registrosPreview.{$this->grupo->id}.0.subproceso.nombre",
                    fn (string $nombre) => in_array($nombre, ['Armado', 'Punteado'], true),
                ));
    });
});
