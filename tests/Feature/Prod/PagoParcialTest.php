<?php

use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoEmpleado;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Registro;
use App\Models\User;
use App\Services\Prod\AvanceDePiezas;
use App\Services\Prod\GeneradorLiquidaciones;
use App\Services\Prod\PendientesDeLiquidar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->grupo = GrupoTrabajo::factory()->create(['descripcion' => 'Cuadrilla A']);

    $this->marca = marcaConPiezas(2, ['marca' => 'V-01', 'peso_unitario' => 100]);
    $this->catalogo = $this->marca->catalogo;
    $this->pieza = $this->marca->piezas[0];
    $this->soldadura = proceso();
    obraPagaProcesos($this->marca->obra_id, $this->soldadura);

    $this->semana1 = Destajo::factory()->create([
        'anio' => 2026,
        'semana' => 6,
        'fecha_inicio' => '2026-02-02',
        'fecha_fin' => '2026-02-08',
    ]);
    $this->semana2 = Destajo::factory()->create([
        'anio' => 2026,
        'semana' => 7,
        'fecha_inicio' => '2026-02-09',
        'fecha_fin' => '2026-02-15',
    ]);
});

/**
 * Captura de una pieza al 60%: el caso base de las parcialidades.
 *
 * @param  array<string, mixed>  $extra
 */
function parcial(array $extra = []): array
{
    return array_merge([
        'fecha' => '2026-02-04',
        'grupo_trabajo_id' => test()->grupo->id,
        'proceso_id' => test()->soldadura->id,
        'piezas' => [test()->pieza->id],
        'porcentaje' => 60,
    ], $extra);
}

/** El CSV manual: grupo, QS, proceso y porcentaje. */
function subirCsvParcial(string $filas)
{
    return test()->actingAs(test()->user)
        ->post(route('admin.prod.destajos.registros.import-csv', test()->semana1), [
            'fecha' => '2026-02-04',
            'csv_file' => UploadedFile::fake()->createWithContent(
                'prod.csv',
                "GRUPO,QS,PROCESO,PORCENTAJE\n".$filas,
            ),
        ]);
}

describe('captura con porcentaje', function () {
    test('se puede pagar un avance parcial de la pieza', function () {
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->semana1), parcial())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('prod_registros', [
            'pieza_id' => $this->pieza->id,
            'proceso_id' => $this->soldadura->id,
            'porcentaje' => 60,
        ]);
    });

    test('el parcial solo consume su fraccion de la pieza', function () {
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->semana1), parcial());

        expect(app(AvanceDePiezas::class)->disponible($this->pieza->fresh(), $this->soldadura->id))->toBe(0.4);
    });

    test('el resto se liquida despues hasta cerrar el 100%', function () {
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->semana1), parcial());

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->semana2), parcial([
                'fecha' => '2026-02-10',
                'porcentaje' => 40,
            ]))
            ->assertSessionHasNoErrors();

        expect(app(AvanceDePiezas::class)->disponible($this->pieza->fresh(), $this->soldadura->id))->toBe(0.0);
    });

    test('no deja pasarse sumando parcialidades', function () {
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->semana1), parcial());

        // Le queda 40%: pedir 50 se pasa.
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->semana2), parcial([
                'fecha' => '2026-02-10',
                'porcentaje' => 50,
            ]))
            ->assertSessionHasErrors('piezas');
    });

    test('rechaza porcentajes fuera de rango', function () {
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->semana1), parcial(['porcentaje' => 0]))
            ->assertSessionHasErrors('porcentaje');

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->semana1), parcial(['porcentaje' => 120]))
            ->assertSessionHasErrors('porcentaje');
    });

    test('sin porcentaje se paga al 100 por ciento', function () {
        $datos = parcial();
        unset($datos['porcentaje']);

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->semana1), $datos);

        $this->assertDatabaseHas('prod_registros', ['pieza_id' => $this->pieza->id, 'porcentaje' => 100]);
    });
});

describe('importacion CSV con porcentaje', function () {
    test('respeta la columna Porcentaje', function () {
        subirCsvParcial("Cuadrilla A,{$this->pieza->qs},Soldadura,60\n")
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('prod_registros', ['pieza_id' => $this->pieza->id, 'porcentaje' => 60]);
    });

    test('dos parcialidades de la misma pieza cierran su 100%', function () {
        subirCsvParcial(
            "Cuadrilla A,{$this->pieza->qs},Soldadura,60\n".
            "Cuadrilla A,{$this->pieza->qs},Soldadura,40\n"
        )->assertSessionHasNoErrors();

        expect(Registro::where('pieza_id', $this->pieza->id)->count())->toBe(2)
            ->and(app(AvanceDePiezas::class)->disponible($this->pieza->fresh(), $this->soldadura->id))->toBe(0.0);
    });

    test('rechaza la linea con porcentaje invalido', function () {
        subirCsvParcial("Cuadrilla A,{$this->pieza->qs},Soldadura,150\n")
            ->assertSessionHasErrors('csv_file');

        $this->assertDatabaseCount('prod_registros', 0);
    });
});

describe('liquidacion con parcialidades', function () {
    beforeEach(function () {
        $this->grupoPrecio = tarifaDeMarca($this->marca, 10);
        GrupoEmpleado::factory()->create(['grupo_trabajo_id' => $this->grupo->id]);
        Auth::login($this->user);
    });

    test('paga solo el porcentaje capturado', function () {
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04', porcentaje: 60);

        app(GeneradorLiquidaciones::class)->generar($this->semana1);

        // 1 pz x 100 kg x 60% = 60 kg x $10 = $600
        $liquidacion = $this->semana1->liquidaciones()->firstOrFail();

        expect((float) $liquidacion->total_kilos)->toBe(60.0)
            ->and((float) $liquidacion->total_produccion)->toBe(600.0)
            ->and((float) $liquidacion->detalles()->firstOrFail()->porcentaje)->toBe(60.0);
    });

    test('dos parcialidades de la misma pieza en la semana se suman en un renglon', function () {
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04', porcentaje: 40);
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-05', porcentaje: 30);

        app(GeneradorLiquidaciones::class)->generar($this->semana1);

        $detalles = $this->semana1->liquidaciones()->firstOrFail()->detalles;

        // Un renglon por pieza y proceso: los porcentajes se acumulan.
        expect($detalles)->toHaveCount(1)
            ->and((float) $detalles->first()->porcentaje)->toBe(70.0);
    });

    test('las dos parcialidades juntas pagan lo mismo que la pieza completa', function () {
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04', porcentaje: 60);
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-10', porcentaje: 40);

        app(GeneradorLiquidaciones::class)->generar($this->semana1);
        app(GeneradorLiquidaciones::class)->generar($this->semana2);

        $pagado = (float) $this->semana1->liquidaciones()->sum('total_produccion')
            + (float) $this->semana2->liquidaciones()->sum('total_produccion');

        // 1 pz x 100 kg x $10 = $1,000 en total, repartido 60/40.
        expect($pagado)->toBe(1000.0);
    });

    test('cada proceso se paga con su propia tarifa', function () {
        $pintura = proceso('Pintura');
        obraPagaProcesos($this->marca->obra_id, $pintura);
        tarifaDeMarca($this->marca, 4, $pintura, $this->grupoPrecio);

        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04');
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04', proceso: $pintura);

        app(GeneradorLiquidaciones::class)->generar($this->semana1);

        // 100 kg x $10 soldando + 100 kg x $4 pintando.
        expect((float) $this->semana1->liquidaciones()->firstOrFail()->total_produccion)->toBe(1400.0);
    });
});

describe('pendientes por liquidar', function () {
    test('lista lo que quedo a medias en semanas anteriores', function () {
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04', porcentaje: 60);

        $pendientes = app(PendientesDeLiquidar::class)->paraDestajo($this->semana2);

        expect($pendientes)->toHaveCount(1)
            ->and($pendientes[0]['marca'])->toBe('V-01')
            ->and($pendientes[0]['qs'])->toBe($this->pieza->qs)
            ->and($pendientes[0]['proceso'])->toBe('Soldadura')
            ->and($pendientes[0]['pagado'])->toBe(0.6)
            ->and($pendientes[0]['saldo'])->toBe(0.4)
            ->and($pendientes[0]['grupo_trabajo_id'])->toBe($this->grupo->id)
            ->and($pendientes[0]['porcentaje_sugerido'])->toBe(40.0)
            ->and($pendientes[0]['numero_avance'])->toBe(2);
    });

    test('trae el QR para poder nombrar la pieza sin QS', function () {
        $this->pieza->update(['qs' => null]);
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04', porcentaje: 60);

        $pendientes = app(PendientesDeLiquidar::class)->paraDestajo($this->semana2);

        expect($pendientes[0]['qr'])->toBe($this->pieza->qr)
            ->and($pendientes[0]['qs'])->toBeNull();
    });

    test('la misma pieza a medias en dos procesos son dos pendientes', function () {
        $pintura = proceso('Pintura');
        obraPagaProcesos($this->marca->obra_id, $pintura);

        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04', porcentaje: 60);
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04', porcentaje: 30, proceso: $pintura);

        expect(app(PendientesDeLiquidar::class)->paraDestajo($this->semana2))->toHaveCount(2);
    });

    test('desaparece cuando se salda', function () {
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04', porcentaje: 60);
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-05', porcentaje: 40);

        expect(app(PendientesDeLiquidar::class)->paraDestajo($this->semana2))->toBeEmpty();
    });

    test('no incluye parciales de la misma semana', function () {
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-10', porcentaje: 60);

        expect(app(PendientesDeLiquidar::class)->paraDestajo($this->semana2))->toBeEmpty();
    });

    test('no incluye piezas pagadas siempre al 100', function () {
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04');

        expect(app(PendientesDeLiquidar::class)->paraDestajo($this->semana2))->toBeEmpty();
    });

    test('el destajo abierto los comparte a la vista', function () {
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04', porcentaje: 60);

        $this->actingAs($this->user)
            ->get(route('admin.prod.destajos.show', $this->semana2))
            ->assertInertia(fn ($page) => $page
                ->has('pendientes', 1)
                ->where('pendientes.0.saldo', 0.4)
            );
    });
});

describe('numero de avance y acumulado en la orden de pago', function () {
    beforeEach(function () {
        tarifaDeMarca($this->marca, 10);
        GrupoEmpleado::factory()->create(['grupo_trabajo_id' => $this->grupo->id]);
        Auth::login($this->user);

        $this->semana3 = Destajo::factory()->create([
            'anio' => 2026,
            'semana' => 8,
            'fecha_inicio' => '2026-02-16',
            'fecha_fin' => '2026-02-22',
        ]);
    });

    /** @return array<int, array<string, mixed>> */
    function renglonesDe(Destajo $destajo): array
    {
        return app(GeneradorLiquidaciones::class)->ordenDePago($destajo->fresh())->first()['piezas'];
    }

    test('cada semana que se paga la pieza es un avance mas y el acumulado la incluye', function () {
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04', porcentaje: 60);
        app(GeneradorLiquidaciones::class)->generar($this->semana1);

        capturarPiezas([$this->pieza], $this->grupo, '2026-02-10', porcentaje: 20);

        // Semana 2 abierta: sale del preview.
        expect(renglonesDe($this->semana2)[0])
            ->avance->toBe(2)
            ->porcentaje->toBe(20.0)
            ->acumulado->toBe(80.0);

        app(GeneradorLiquidaciones::class)->generar($this->semana2);
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-17', porcentaje: 20);

        expect(renglonesDe($this->semana3)[0])
            ->avance->toBe(3)
            ->acumulado->toBe(100.0)
            // Ya cerrada, la semana 2 sigue diciendo lo mismo.
            ->and(renglonesDe($this->semana2)[0])
            ->avance->toBe(2)
            ->acumulado->toBe(80.0)
            // Lo pagado despues no se cuela al reimprimir la primera.
            ->and(renglonesDe($this->semana1)[0])
            ->avance->toBe(1)
            ->acumulado->toBe(60.0);
    });

    test('dos parcialidades en la misma semana son un solo avance', function () {
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04', porcentaje: 40);
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-05', porcentaje: 30);

        expect(renglonesDe($this->semana1))->toHaveCount(1)
            ->and(renglonesDe($this->semana1)[0])
            ->avance->toBe(1)
            ->porcentaje->toBe(70.0)
            ->acumulado->toBe(70.0);
    });

    test('la misma marca en distinto avance sale en renglones aparte', function () {
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04', porcentaje: 60);
        app(GeneradorLiquidaciones::class)->generar($this->semana1);

        capturarPiezas([$this->pieza], $this->grupo, '2026-02-10', porcentaje: 40);
        capturarPiezas([$this->marca->piezas[1]], $this->grupo, '2026-02-10');

        $renglones = renglonesDe($this->semana2);

        expect($renglones)->toHaveCount(2)
            ->and(collect($renglones)->map(fn (array $r) => [$r['avance'], $r['porcentaje'], $r['acumulado'], $r['pzs']])->all())
            ->toBe([[1, 100.0, 100.0, 1], [2, 40.0, 100.0, 1]]);
    });

    test('el pdf imprime el avance y el acumulado', function () {
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04', porcentaje: 60);
        app(GeneradorLiquidaciones::class)->generar($this->semana1);
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-10', porcentaje: 25);

        $html = view('pdf.prod.orden-pago', [
            'destajo' => $this->semana2->fresh(),
            'grupos' => app(GeneradorLiquidaciones::class)->ordenDePago($this->semana2->fresh()),
        ])->render();

        expect($html)->toContain('Avance')
            ->toContain('2º')
            ->toContain('25%')
            ->toContain('85%');
    });
});
