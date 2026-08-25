<?php

use App\Models\Prod\Destajo;
use App\Models\Prod\Pieza;
use App\Models\Prod\Registro;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Cuando el archivo no trae QR, el QS ya no alcanza para identificar la pieza:
 * desde que el layout maneja lotes, el mismo QS se repite dentro del catálogo.
 *
 * En vez de rendirse, el import reparte esos movimientos entre las piezas que
 * comparten el QS, del QR más chico al más grande, tomando sólo las que todavía
 * tienen cupo. Aquí se prueba ese reparto.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->destajo = Destajo::factory()->create([
        'fecha_inicio' => '2026-02-03',
        'fecha_fin' => '2026-02-09',
    ]);

    $this->soldadura = proceso();
    $this->marca = marcaConPiezas(0, ['marca' => 'TG-CM5-1']);
    obraPagaProcesos($this->marca->obra_id, $this->soldadura);

    $this->grupo = \App\Models\Prod\GrupoTrabajo::factory()->create(['descripcion' => 'Cuadrilla A']);
    $this->grupo->ubicaciones()->attach(
        \App\Models\Prod\Ubicacion::factory()->create(['nombre' => 'M3.6 Fabricacion'])
    );
});

/** El export de planta con un movimiento por renglón; sólo se lee QS y evento. */
function exportDeQs(string ...$qss): string
{
    $csv = "Proceso,Contracto,Movido el,Ubicacion,QS,Marca,Peso,Cantidad,Trabajador\n";

    foreach ($qss as $qs) {
        $csv .= "75 Soldadura,S26-05-05 REJAS,46195,M3.6 Fabricacion,{$qs},TG-CM5-1,1746,1,SAUL DZUL\n";
    }

    return $csv;
}

function subirAvance(string $csv)
{
    return test()->actingAs(test()->user)
        ->post(route('admin.prod.destajos.registros.import-csv', test()->destajo), [
            'csv_file' => UploadedFile::fake()->createWithContent('avance.csv', $csv),
            'fecha' => '2026-02-05',
        ]);
}

/**
 * Piezas del mismo modelo que comparten QS y sólo se distinguen por su QR. Es
 * justo el caso que el layout con lotes produce.
 *
 * @param  list<string>  $qrs
 * @return \Illuminate\Support\Collection<int, Pieza>
 */
function piezasConMismoQs(string $qs, array $qrs, ?\App\Models\Concepto $marca = null)
{
    $marca ??= test()->marca;

    return collect($qrs)->map(fn (string $qr): Pieza => Pieza::factory()->create([
        'concepto_id' => $marca->id,
        'catalogo_id' => $marca->catalogo_id,
        'qr' => $qr,
        'qs' => $qs,
    ]));
}

test('el QS ambiguo se asigna a la pieza con el QR mas chico', function () {
    // Alfabéticamente "QR-10" va antes que "QR-9": el orden tiene que ser natural.
    $piezas = piezasConMismoQs('12345', ['QR-10', 'QR-9', 'QR-100']);

    subirAvance(exportDeQs('12345'))
        ->assertSessionHasNoErrors();

    expect(Registro::sole()->pieza_id)->toBe($piezas->firstWhere('qr', 'QR-9')->id);
});

test('el segundo renglon del mismo QS toma el siguiente QR', function () {
    $piezas = piezasConMismoQs('12345', ['QR-9', 'QR-10', 'QR-100']);

    subirAvance(exportDeQs('12345', '12345'))->assertSessionHasNoErrors();

    expect(Registro::pluck('pieza_id')->sort()->values()->all())->toBe(
        $piezas->whereIn('qr', ['QR-9', 'QR-10'])->pluck('id')->sort()->values()->all()
    );
});

test('el renglon que ya no tiene pieza libre se omite como repetido, no como error', function () {
    piezasConMismoQs('12345', ['QR-9', 'QR-10']);

    subirAvance(exportDeQs('12345', '12345', '12345'))->assertSessionHasNoErrors();

    expect(Registro::count())->toBe(2);
});

test('sin ninguna candidata con cupo se reporta el tope', function () {
    $piezas = piezasConMismoQs('12345', ['QR-9', 'QR-10']);
    capturarPiezas($piezas->all(), $this->grupo, '2026-02-04');

    subirAvance(exportDeQs('12345'))
        ->assertSessionHasErrors('csv_file');

    expect(Registro::count())->toBe(2);
});

test('respeta lo ya pagado en parcialidades al elegir', function () {
    $piezas = piezasConMismoQs('12345', ['QR-9', 'QR-10']);
    capturarPiezas([$piezas->firstWhere('qr', 'QR-9')], $this->grupo, '2026-02-04', porcentaje: 60);

    // El 100% no cabe en la de QR-9 (le quedan 40), así que se va a la siguiente.
    subirAvance(exportDeQs('12345'))
        ->assertSessionHasNoErrors();

    expect(Registro::where('pieza_id', $piezas->firstWhere('qr', 'QR-10')->id)->count())->toBe(1)
        ->and(Registro::where('pieza_id', $piezas->firstWhere('qr', 'QR-9')->id)->count())->toBe(1);
});

test('el mismo QS en dos obras sigue siendo ambiguo', function () {
    $otra = marcaConPiezas(0, ['marca' => 'OTRA-OBRA']);
    obraPagaProcesos($otra->obra_id, $this->soldadura);

    piezasConMismoQs('12345', ['QR-9']);
    piezasConMismoQs('12345', ['QR-1'], $otra);

    subirAvance(exportDeQs('12345'))
        ->assertSessionHasErrors('csv_file');

    expect(Registro::count())->toBe(0);
});

test('el QR manda sobre el QS cuando el archivo lo trae', function () {
    $piezas = piezasConMismoQs('12345', ['QR-9', 'QR-10']);

    $csv = "Proceso,Ubicacion,QS,QR\n"
        ."75 Soldadura,M3.6 Fabricacion,12345,QR-10\n";

    subirAvance($csv)->assertSessionHasNoErrors();

    expect(Registro::sole()->pieza_id)->toBe($piezas->firstWhere('qr', 'QR-10')->id);
});
