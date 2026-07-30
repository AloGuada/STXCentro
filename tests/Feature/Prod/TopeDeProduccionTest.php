<?php

use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\Catalogo;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoTrabajo;
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
    $this->catalogo = Catalogo::factory()->create();
    $this->pieza = Concepto::factory()->create([
        'obra_id' => $this->catalogo->obra_id,
        'catalogo_id' => $this->catalogo->id,
        'marca' => 'V-01',
        'cantidad' => 10,
    ]);
});

/** @param array<string, mixed> $extra */
function capturar(array $extra = []): array
{
    return array_merge([
        'fecha' => '2026-02-05',
        'grupo_trabajo_id' => test()->grupo->id,
        'concepto_id' => test()->pieza->id,
    ], $extra);
}

describe('tope de captura manual', function () {
    test('permite capturar exactamente lo que manda el catalogo', function () {
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->destajo), capturar(['cantidad' => 10]))
            ->assertRedirect(route('admin.prod.destajos.show', $this->destajo));

        $this->assertDatabaseHas('prod_registros', ['concepto_id' => $this->pieza->id, 'cantidad' => 10]);
    });

    test('bloquea capturar mas de lo que manda el catalogo', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->destajo), capturar(['cantidad' => 11]));

        $response->assertSessionHasErrors('cantidad');
        $this->assertDatabaseCount('prod_registros', 0);
    });

    test('descuenta lo ya capturado en semanas anteriores', function () {
        Registro::factory()->create([
            'concepto_id' => $this->pieza->id,
            'grupo_trabajo_id' => $this->grupo->id,
            'fecha' => '2026-01-20',
            'cantidad' => 7,
        ]);

        // Quedan 3: 4 se rechaza, 3 pasa.
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->destajo), capturar(['cantidad' => 4]))
            ->assertSessionHasErrors('cantidad');

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->destajo), capturar(['cantidad' => 3]))
            ->assertSessionHasNoErrors();

        expect(Registro::where('concepto_id', $this->pieza->id)->sum('cantidad'))->toBe(10);
    });

    test('bloquea cuando la pieza ya esta completa', function () {
        Registro::factory()->create([
            'concepto_id' => $this->pieza->id,
            'grupo_trabajo_id' => $this->grupo->id,
            'cantidad' => 10,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->destajo), capturar(['cantidad' => 1]))
            ->assertSessionHasErrors('cantidad');
    });

    test('cuenta el historico de versiones anteriores del catalogo', function () {
        Registro::factory()->create([
            'concepto_id' => $this->pieza->id,
            'grupo_trabajo_id' => $this->grupo->id,
            'cantidad' => 8,
        ]);

        $v2 = app(VersionadorCatalogo::class)->nuevaVersion($this->catalogo);
        $copia = $v2->conceptos()->where('marca', 'V-01')->firstOrFail();

        // La copia no tiene registros propios, pero la marca ya lleva 8 de 10.
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->destajo), capturar([
                'concepto_id' => $copia->id,
                'cantidad' => 3,
            ]))
            ->assertSessionHasErrors('cantidad');

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->destajo), capturar([
                'concepto_id' => $copia->id,
                'cantidad' => 2,
            ]))
            ->assertSessionHasNoErrors();
    });

    test('borrar un registro libera piezas para volver a capturar', function () {
        $registro = Registro::factory()->create([
            'concepto_id' => $this->pieza->id,
            'grupo_trabajo_id' => $this->grupo->id,
            'cantidad' => 10,
        ]);

        $this->actingAs($this->user)
            ->delete(route('admin.prod.destajos.registros.destroy', [$this->destajo, $registro]));

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->destajo), capturar(['cantidad' => 10]))
            ->assertSessionHasNoErrors();
    });

    test('el tope es por obra, no afecta a la misma marca de otra obra', function () {
        $otra = Catalogo::factory()->create();
        $gemela = Concepto::factory()->create([
            'obra_id' => $otra->obra_id,
            'catalogo_id' => $otra->id,
            'marca' => 'V-01',
            'cantidad' => 5,
        ]);

        Registro::factory()->create([
            'concepto_id' => $this->pieza->id,
            'grupo_trabajo_id' => $this->grupo->id,
            'cantidad' => 10,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $this->destajo), capturar([
                'concepto_id' => $gemela->id,
                'cantidad' => 5,
            ]))
            ->assertSessionHasNoErrors();
    });
});

describe('tope en la importacion CSV', function () {
    test('rechaza la linea que excede y deja pasar las demas', function () {
        $otra = Concepto::factory()->create([
            'obra_id' => $this->catalogo->obra_id,
            'catalogo_id' => $this->catalogo->id,
            'marca' => 'C-01',
            'cantidad' => 50,
        ]);

        $csv = "GRUPO,MARCA,CANTIDAD\n";
        $csv .= "Cuadrilla A,V-01,25\n";
        $csv .= "Cuadrilla A,C-01,5\n";

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.import-csv', $this->destajo), [
                'fecha' => '2026-02-05',
                'csv_file' => UploadedFile::fake()->createWithContent('prod.csv', $csv),
            ]);

        $response->assertSessionHasErrors('csv_file');
        $this->assertDatabaseMissing('prod_registros', ['concepto_id' => $this->pieza->id]);
        $this->assertDatabaseHas('prod_registros', ['concepto_id' => $otra->id, 'cantidad' => 5]);
    });

    test('dos renglones de la misma marca no rebasan juntos el catalogo', function () {
        $csv = "GRUPO,MARCA,CANTIDAD\n";
        $csv .= "Cuadrilla A,V-01,6\n";
        $csv .= "Cuadrilla A,V-01,6\n";

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.import-csv', $this->destajo), [
                'fecha' => '2026-02-05',
                'csv_file' => UploadedFile::fake()->createWithContent('prod.csv', $csv),
            ]);

        $response->assertSessionHasErrors('csv_file');
        expect(Registro::where('concepto_id', $this->pieza->id)->sum('cantidad'))->toBe(6);
    });
});

describe('visibilidad del avance', function () {
    test('el servicio reporta capturado y disponible', function () {
        Registro::factory()->create([
            'concepto_id' => $this->pieza->id,
            'grupo_trabajo_id' => $this->grupo->id,
            'cantidad' => 4,
        ]);

        $avance = app(AvanceDePiezas::class);

        expect($avance->capturado($this->pieza))->toBe(4.0)
            ->and($avance->disponible($this->pieza))->toBe(6.0);
    });

    test('disponible nunca es negativo si el catalogo se recorta', function () {
        Registro::factory()->create([
            'concepto_id' => $this->pieza->id,
            'grupo_trabajo_id' => $this->grupo->id,
            'cantidad' => 10,
        ]);
        $this->pieza->update(['cantidad' => 4]);

        expect(app(AvanceDePiezas::class)->disponible($this->pieza->fresh()))->toBe(0.0);
    });

    test('el catalogo muestra pagadas y faltantes por pieza', function () {
        Registro::factory()->create([
            'concepto_id' => $this->pieza->id,
            'grupo_trabajo_id' => $this->grupo->id,
            'cantidad' => 4,
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.prod.catalogos.show', $this->catalogo))
            ->assertInertia(fn ($page) => $page
                ->where('conceptos.0.capturado', 4)
                ->where('conceptos.0.disponible', 6)
            );
    });

    test('el destajo comparte el avance de cada pieza para capturar', function () {
        Registro::factory()->create([
            'concepto_id' => $this->pieza->id,
            'grupo_trabajo_id' => $this->grupo->id,
            'cantidad' => 4,
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.prod.destajos.show', $this->destajo))
            ->assertInertia(fn ($page) => $page
                ->where('conceptos.0.capturado', 4)
                ->where('conceptos.0.disponible', 6)
            );
    });
});

test('una obra sin catalogo no rompe el avance', function () {
    $obra = Obra::factory()->create();

    $pieza = Concepto::factory()->create(['obra_id' => $obra->id, 'cantidad' => 5]);

    expect(app(AvanceDePiezas::class)->mapaDeObra($obra->id)->capturadoDe($pieza))->toBe(0.0);
});
