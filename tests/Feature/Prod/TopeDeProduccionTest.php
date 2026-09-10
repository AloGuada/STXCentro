<?php

use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\LiquidacionDetalle;
use App\Models\Prod\Registro;
use App\Models\User;
use App\Services\Prod\AvanceDePiezas;
use App\Services\Prod\VersionadorCatalogo;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->grupo = GrupoTrabajo::factory()->create(['descripcion' => 'Cuadrilla A']);
    $this->destajo = Destajo::factory()->create([
        'fecha_inicio' => '2026-02-03',
        'fecha_fin' => '2026-02-09',
    ]);

    // Un modelo de 3 piezas: el tope es de una pieza por QS y por proceso.
    $this->marca = marcaConPiezas(3, ['marca' => 'V-01']);
    $this->catalogo = $this->marca->catalogo;
    $this->pieza = $this->marca->piezas[0];
    $this->soldadura = proceso();
    $this->pintura = proceso('Pintura');

    obraPagaProcesos($this->marca->obra_id, $this->soldadura, $this->pintura);
});

/**
 * Payload de captura manual. Por default una sola pieza al 100% en soldadura.
 *
 * @param  array<string, mixed>  $extra
 */
function capturar(array $extra = []): array
{
    return array_merge([
        'fecha' => '2026-02-05',
        'grupo_trabajo_id' => test()->grupo->id,
        'proceso_id' => test()->soldadura->id,
        'piezas' => [test()->pieza->id],
    ], $extra);
}

/** El CSV manual de captura: grupo, QS y proceso. */
function subirCsvDeQs(string $filas)
{
    return test()->actingAs(test()->user)
        ->post(route('admin.prod.destajos.registros.import-csv', test()->destajo), [
            'csv_file' => UploadedFile::fake()->createWithContent(
                'produccion.csv',
                "GRUPO,QS,PROCESO,PORCENTAJE\n".$filas,
            ),
            'fecha' => '2026-02-05',
        ]);
}

describe('tope de captura manual', function () {
    test('permite pagar la pieza completa una vez', function () {
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->destajo), capturar())
            ->assertRedirect(route('admin.prod.destajos.show', $this->destajo));

        $this->assertDatabaseHas('prod_registros', [
            'pieza_id' => $this->pieza->id,
            'proceso_id' => $this->soldadura->id,
            'porcentaje' => 100,
        ]);
    });

    test('bloquea volver a pagar una pieza ya pagada al 100%', function () {
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04');

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->destajo), capturar())
            ->assertSessionHasErrors('piezas');

        expect(Registro::count())->toBe(1);
    });

    test('descuenta lo ya pagado en parcialidades', function () {
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04', porcentaje: 60);

        // Le queda 40%: pedir 50 se pasa.
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->destajo), capturar(['porcentaje' => 50]))
            ->assertSessionHasErrors('piezas');

        // Y 40 exacto entra.
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->destajo), capturar(['porcentaje' => 40]))
            ->assertSessionHasNoErrors();

        expect(app(AvanceDePiezas::class)->disponible($this->pieza->fresh(), $this->soldadura->id))->toBe(0.0);
    });

    test('las piezas que no caben se reportan y las demas si se guardan', function () {
        // La primera ya esta pagada; las otras dos siguen libres.
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04');

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->destajo), capturar([
                'piezas' => $this->marca->piezas->pluck('id')->all(),
            ]))
            ->assertSessionHasErrors('piezas');

        expect(Registro::count())->toBe(3);
    });

    test('soldar una pieza no consume lo que le toca por pintarla', function () {
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04');

        $avance = app(AvanceDePiezas::class);

        expect($avance->disponible($this->pieza, $this->soldadura->id))->toBe(0.0)
            ->and($avance->disponible($this->pieza, $this->pintura->id))->toBe(1.0);

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->destajo), capturar([
                'proceso_id' => $this->pintura->id,
            ]))
            ->assertSessionHasNoErrors();
    });

    test('la obra no paga un proceso que no tiene configurado', function () {
        $this->marca->obra->procesos()->detach($this->pintura->id);

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->destajo), capturar([
                'proceso_id' => $this->pintura->id,
            ]))
            ->assertSessionHasErrors('proceso_id');

        expect(Registro::count())->toBe(0);
    });

    test('cuenta el historico de versiones anteriores del catalogo', function () {
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04');

        $nueva = app(VersionadorCatalogo::class)->nuevaVersion($this->catalogo);
        $copia = $nueva->piezas()->where('qs', $this->pieza->qs)->firstOrFail();

        // La copia es una fila nueva, pero su linaje sostiene lo ya pagado.
        expect(app(AvanceDePiezas::class)->disponible($copia, $this->soldadura->id))->toBe(0.0);
    });

    test('borrar un registro libera la pieza para volver a capturar', function () {
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04');
        $registro = Registro::sole();

        $this->actingAs($this->user)
            ->delete(route('admin.prod.destajos.registros.destroy', [$this->destajo, $registro]))
            ->assertRedirect();

        expect(app(AvanceDePiezas::class)->disponible($this->pieza->fresh(), $this->soldadura->id))->toBe(1.0);
    });

    test('el tope es por pieza: pagar una no afecta a sus hermanas', function () {
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04');

        $avance = app(AvanceDePiezas::class);

        expect($avance->disponible($this->marca->piezas[1], $this->soldadura->id))->toBe(1.0)
            ->and($avance->disponible($this->marca->piezas[2], $this->soldadura->id))->toBe(1.0);
    });
});

describe('tope en la importacion CSV', function () {
    test('rechaza la linea que excede y deja pasar las demas', function () {
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04');

        $libre = $this->marca->piezas[1];

        subirCsvDeQs(
            "Cuadrilla A,{$this->pieza->qs},Soldadura,100\n".
            "Cuadrilla A,{$libre->qs},Soldadura,100\n"
        )->assertSessionHasErrors('csv_file');

        // La pagada se reporta, la libre entra.
        expect(Registro::where('pieza_id', $libre->id)->count())->toBe(1)
            ->and(Registro::where('pieza_id', $this->pieza->id)->count())->toBe(1);
    });

    test('dos renglones de la misma pieza no rebasan juntos su tope', function () {
        subirCsvDeQs(
            "Cuadrilla A,{$this->pieza->qs},Soldadura,60\n".
            "Cuadrilla A,{$this->pieza->qs},Soldadura,60\n"
        )->assertSessionHasErrors('csv_file');

        // El segundo 60% no cabe sobre el primero.
        expect(Registro::where('pieza_id', $this->pieza->id)->count())->toBe(1);
    });

    test('reporta el QS que no esta en ningun catalogo vigente', function () {
        subirCsvDeQs("Cuadrilla A,NO-EXISTE,Soldadura,100\n")
            ->assertSessionHasErrors('csv_file');

        expect(Registro::count())->toBe(0);
    });
});

describe('visibilidad del avance', function () {
    test('el servicio reporta capturado y disponible por proceso', function () {
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04', porcentaje: 40);

        $avance = app(AvanceDePiezas::class);

        expect($avance->capturado($this->pieza, $this->soldadura->id))->toBe(0.4)
            ->and($avance->disponible($this->pieza, $this->soldadura->id))->toBe(0.6);
    });

    test('disponible nunca es negativo', function () {
        LiquidacionDetalle::factory()->create([
            'pieza_id' => $this->pieza->id,
            'obra_id' => $this->marca->obra_id,
            'qs' => $this->pieza->qs,
            'proceso_id' => $this->soldadura->id,
            'porcentaje' => 100,
        ]);

        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04');

        expect(app(AvanceDePiezas::class)->disponible($this->pieza, $this->soldadura->id))->toBe(0.0);
    });

    test('el catalogo muestra el avance de cada proceso', function () {
        // Todas: el catalogo ordena por QS, asi que cualquier renglon sirve.
        capturarPiezas($this->marca->piezas, $this->grupo, '2026-02-04');

        $this->actingAs($this->user)
            ->get(route('admin.prod.catalogos.show', $this->catalogo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('marcas.data', 1)
                ->has('procesos', 2)
                // El avance de la marca lo resume el servidor: 3 de sus piezas
                // pagadas en soldadura. Los QR se piden al desplegarla.
                ->where('avancePorMarca.'.$this->marca->id.'.'.$this->soldadura->id, 3)
            );
    });

    test('el destajo comparte el avance de cada pieza para capturar', function () {
        capturarPiezas([$this->pieza], $this->grupo, '2026-02-04');

        // El avance viaja con las piezas de la marca, que se piden al elegirla:
        // la pantalla del destajo ya no carga el catalogo entero.
        $this->actingAs($this->user)
            ->get(route('admin.prod.destajos.show', $this->destajo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('obras')
                ->has('procesos')
            );

        $respuesta = $this->actingAs($this->user)
            ->getJson(route('admin.prod.marcas.piezas', $this->marca))
            ->assertOk();

        $pieza = collect($respuesta->json('piezas'))->firstWhere('id', $this->pieza->id);

        expect((float) $pieza['avance'][$this->soldadura->id]['disponible'])->toBe(0.0);
    });
});

test('una obra sin catalogo no rompe el avance', function () {
    expect(app(AvanceDePiezas::class)->mapaDeObra(999999))->not->toBeNull();
});

describe('marcas repetidas en varias lotes', function () {
    test('cada lote de la misma marca tiene sus propias piezas y su propio tope', function () {
        $lote2 = marcaConPiezas(2, [
            'obra_id' => $this->marca->obra_id,
            'catalogo_id' => $this->catalogo->id,
            'marca' => 'V-01',
            'lote' => '2',
        ]);

        capturarPiezas($this->marca->piezas, $this->grupo, '2026-02-04');

        $avance = app(AvanceDePiezas::class);

        // La lote 2 sigue intacta: son piezas distintas aunque compartan marca.
        expect($avance->disponible($lote2->piezas[0], $this->soldadura->id))->toBe(1.0)
            ->and($avance->disponible($this->pieza->fresh(), $this->soldadura->id))->toBe(0.0);
    });

    test('el snapshot huerfano se recupera por QR, no por marca', function () {
        $otra = $this->marca->piezas[1];

        // Snapshot de una pieza que ya no existe en el catalogo.
        LiquidacionDetalle::factory()->create([
            'pieza_id' => 999999,
            'obra_id' => $this->marca->obra_id,
            'qr' => $this->pieza->qr,
            'qs' => $this->pieza->qs,
            'marca' => 'V-01',
            'proceso_id' => $this->soldadura->id,
            'porcentaje' => 100,
        ]);

        $avance = app(AvanceDePiezas::class);

        // Sólo el QR del snapshot queda tocado; su hermana no.
        expect($avance->disponible($this->pieza, $this->soldadura->id))->toBe(0.0)
            ->and($avance->disponible($otra, $this->soldadura->id))->toBe(1.0);
    });
});
