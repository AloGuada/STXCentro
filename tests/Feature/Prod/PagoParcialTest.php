<?php

use App\Models\Concepto;
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
use App\Services\Prod\PendientesDeLiquidar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->grupo = GrupoTrabajo::factory()->create(['descripcion' => 'Cuadrilla A']);
    $this->catalogo = Catalogo::factory()->create();
    $this->pieza = Concepto::factory()->create([
        'obra_id' => $this->catalogo->obra_id,
        'catalogo_id' => $this->catalogo->id,
        'marca' => 'V-01',
        'cantidad' => 10,
        'peso_unitario' => 100,
    ]);
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

/** @param array<string, mixed> $extra */
function parcial(array $extra = []): array
{
    return array_merge([
        'fecha' => '2026-02-04',
        'grupo_trabajo_id' => test()->grupo->id,
        'concepto_id' => test()->pieza->id,
        'cantidad' => 10,
        'porcentaje' => 60,
    ], $extra);
}

describe('captura con porcentaje', function () {
    test('se puede pagar un avance parcial del lote', function () {
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->semana1), parcial())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('prod_registros', [
            'concepto_id' => $this->pieza->id,
            'cantidad' => 10,
            'porcentaje' => 60,
        ]);
    });

    test('el parcial solo consume su equivalente del catalogo', function () {
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->semana1), parcial());

        // 10 al 60% gastan 6 de 10; quedan 4.
        expect(app(AvanceDePiezas::class)->disponible($this->pieza->fresh()))->toBe(4.0);
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

        expect(app(AvanceDePiezas::class)->disponible($this->pieza->fresh()))->toBe(0.0);
    });

    test('no deja pasarse del catalogo sumando parcialidades', function () {
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->semana1), parcial());

        // Quedan 4 equivalentes: 10 al 50% (=5) se pasa.
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->semana2), parcial([
                'fecha' => '2026-02-10',
                'porcentaje' => 50,
            ]))
            ->assertSessionHasErrors('cantidad');
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

        $this->assertDatabaseHas('prod_registros', ['concepto_id' => $this->pieza->id, 'porcentaje' => 100]);
    });
});

describe('importacion CSV con porcentaje', function () {
    test('respeta la columna Porcentaje', function () {
        $csv = "GRUPO,MARCA,CANTIDAD,PORCENTAJE\n";
        $csv .= "Cuadrilla A,V-01,10,60\n";

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.import-csv', $this->semana1), [
                'fecha' => '2026-02-04',
                'csv_file' => UploadedFile::fake()->createWithContent('prod.csv', $csv),
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('prod_registros', ['concepto_id' => $this->pieza->id, 'porcentaje' => 60]);
    });

    test('el tope del archivo cuenta equivalentes, no piezas', function () {
        $csv = "GRUPO,MARCA,CANTIDAD,PORCENTAJE\n";
        $csv .= "Cuadrilla A,V-01,10,60\n";
        $csv .= "Cuadrilla A,V-01,10,40\n";

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.import-csv', $this->semana1), [
                'fecha' => '2026-02-04',
                'csv_file' => UploadedFile::fake()->createWithContent('prod.csv', $csv),
            ])
            ->assertSessionHasNoErrors();

        expect(Registro::where('concepto_id', $this->pieza->id)->count())->toBe(2)
            ->and(app(AvanceDePiezas::class)->disponible($this->pieza->fresh()))->toBe(0.0);
    });

    test('rechaza la linea con porcentaje invalido', function () {
        $csv = "GRUPO,MARCA,CANTIDAD,PORCENTAJE\n";
        $csv .= "Cuadrilla A,V-01,5,150\n";

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.import-csv', $this->semana1), [
                'fecha' => '2026-02-04',
                'csv_file' => UploadedFile::fake()->createWithContent('prod.csv', $csv),
            ])
            ->assertSessionHasErrors('csv_file');

        $this->assertDatabaseCount('prod_registros', 0);
    });
});

describe('liquidacion con parcialidades', function () {
    beforeEach(function () {
        $grupoPrecio = GrupoPrecio::factory()->create([
            'obra_id' => $this->catalogo->obra_id,
            'precio_kilo' => 10,
        ]);
        GrupoPrecioConcepto::create([
            'grupo_precio_id' => $grupoPrecio->id,
            'concepto_id' => $this->pieza->id,
        ]);
        GrupoEmpleado::factory()->create([
            'grupo_trabajo_id' => $this->grupo->id,

        ]);
        Auth::login($this->user);
    });

    test('paga solo el porcentaje capturado', function () {
        Registro::create([
            'fecha' => '2026-02-04',
            'concepto_id' => $this->pieza->id,
            'grupo_trabajo_id' => $this->grupo->id,
            'cantidad' => 10,
            'porcentaje' => 60,
        ]);

        app(GeneradorLiquidaciones::class)->generar($this->semana1);

        // 10 pz x 100 kg x 60% = 600 kg x $10 = $6,000
        $liquidacion = $this->semana1->liquidaciones()->firstOrFail();

        expect((float) $liquidacion->total_kilos)->toBe(600.0)
            ->and((float) $liquidacion->total_produccion)->toBe(6000.0)
            ->and((float) $liquidacion->detalles()->firstOrFail()->porcentaje)->toBe(60.0);
    });

    test('separa renglones del mismo lote con porcentajes distintos', function () {
        foreach ([['2026-02-04', 40], ['2026-02-05', 30]] as [$fecha, $pct]) {
            Registro::create([
                'fecha' => $fecha,
                'concepto_id' => $this->pieza->id,
                'grupo_trabajo_id' => $this->grupo->id,
                'cantidad' => 5,
                'porcentaje' => $pct,
            ]);
        }

        app(GeneradorLiquidaciones::class)->generar($this->semana1);

        $detalles = $this->semana1->liquidaciones()->firstOrFail()->detalles;

        expect($detalles)->toHaveCount(2)
            ->and($detalles->pluck('porcentaje')->map(fn ($p) => (float) $p)->sort()->values()->all())
            ->toBe([30.0, 40.0]);
    });

    test('las dos parcialidades juntas pagan lo mismo que el lote completo', function () {
        Registro::create([
            'fecha' => '2026-02-04',
            'concepto_id' => $this->pieza->id,
            'grupo_trabajo_id' => $this->grupo->id,
            'cantidad' => 10,
            'porcentaje' => 60,
        ]);
        Registro::create([
            'fecha' => '2026-02-10',
            'concepto_id' => $this->pieza->id,
            'grupo_trabajo_id' => $this->grupo->id,
            'cantidad' => 10,
            'porcentaje' => 40,
        ]);

        app(GeneradorLiquidaciones::class)->generar($this->semana1);
        app(GeneradorLiquidaciones::class)->generar($this->semana2);

        $pagado = (float) $this->semana1->liquidaciones()->sum('total_produccion')
            + (float) $this->semana2->liquidaciones()->sum('total_produccion');

        // 10 pz x 100 kg x $10 = $10,000 en total, repartido 60/40.
        expect($pagado)->toBe(10000.0);
    });
});

describe('pendientes por liquidar', function () {
    test('lista lo que quedo a medias en semanas anteriores', function () {
        Registro::create([
            'fecha' => '2026-02-04',
            'concepto_id' => $this->pieza->id,
            'grupo_trabajo_id' => $this->grupo->id,
            'cantidad' => 10,
            'porcentaje' => 60,
        ]);

        $pendientes = app(PendientesDeLiquidar::class)->paraDestajo($this->semana2);

        expect($pendientes)->toHaveCount(1)
            ->and($pendientes[0]['marca'])->toBe('V-01')
            ->and($pendientes[0]['pagado'])->toBe(6.0)
            ->and($pendientes[0]['saldo'])->toBe(4.0)
            ->and($pendientes[0]['grupo_trabajo_id'])->toBe($this->grupo->id)
            ->and($pendientes[0]['cantidad_sugerida'])->toBe(10)
            ->and($pendientes[0]['porcentaje_sugerido'])->toBe(40.0);
    });

    test('desaparece cuando se salda', function () {
        Registro::create([
            'fecha' => '2026-02-04',
            'concepto_id' => $this->pieza->id,
            'grupo_trabajo_id' => $this->grupo->id,
            'cantidad' => 10,
            'porcentaje' => 60,
        ]);
        Registro::create([
            'fecha' => '2026-02-05',
            'concepto_id' => $this->pieza->id,
            'grupo_trabajo_id' => $this->grupo->id,
            'cantidad' => 10,
            'porcentaje' => 40,
        ]);

        expect(app(PendientesDeLiquidar::class)->paraDestajo($this->semana2))->toBeEmpty();
    });

    test('no incluye parciales de la misma semana', function () {
        Registro::create([
            'fecha' => '2026-02-10',
            'concepto_id' => $this->pieza->id,
            'grupo_trabajo_id' => $this->grupo->id,
            'cantidad' => 10,
            'porcentaje' => 60,
        ]);

        expect(app(PendientesDeLiquidar::class)->paraDestajo($this->semana2))->toBeEmpty();
    });

    test('no incluye piezas pagadas siempre al 100', function () {
        Registro::create([
            'fecha' => '2026-02-04',
            'concepto_id' => $this->pieza->id,
            'grupo_trabajo_id' => $this->grupo->id,
            'cantidad' => 5,
            'porcentaje' => 100,
        ]);

        expect(app(PendientesDeLiquidar::class)->paraDestajo($this->semana2))->toBeEmpty();
    });

    test('el destajo abierto los comparte a la vista', function () {
        Registro::create([
            'fecha' => '2026-02-04',
            'concepto_id' => $this->pieza->id,
            'grupo_trabajo_id' => $this->grupo->id,
            'cantidad' => 10,
            'porcentaje' => 60,
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.prod.destajos.show', $this->semana2))
            ->assertInertia(fn ($page) => $page
                ->has('pendientes', 1)
                ->where('pendientes.0.saldo', 4)
            );
    });
});
