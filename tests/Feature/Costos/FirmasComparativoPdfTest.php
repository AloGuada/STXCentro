<?php

use App\Models\Costos\Aprobacion;
use App\Models\Costos\Permiso;
use App\Models\Costos\Requisicion;
use App\Models\Departamento;
use App\Models\User;
use App\Services\Costos\ComparativoTotalesBuilder;
use App\Services\Costos\FirmasPdfBuilder;
use Illuminate\Support\Facades\Storage;

/**
 * Las firmas del comparativo de la requisicion.
 *
 * El bloque dibujaba siempre un recuadro vacio: aunque el aprobador tuviera su
 * firma guardada, el PDF no la ligaba —a diferencia de la solicitud de pago, que
 * si la imprime. Y el nivel sin firmar decia "Pendiente" a secas, sin decir
 * quien puede firmarlo.
 */
beforeEach(function () {
    $this->depto = Departamento::factory()->create();

    $this->requisicion = Requisicion::factory()->create([
        'departamento_id' => $this->depto->id,
    ]);

    $this->permiso = Permiso::factory()->create([
        'tipo_aprobacion' => Requisicion::TIPO_APROBACION,
        'nivel' => 1,
        'descripcion' => 'Jefe de compras',
    ]);
});

/** Firma de verdad en disco: la vista comprueba el archivo antes de imprimirla. */
function firmaEnDisco(string $ruta): void
{
    Storage::disk('public')->put($ruta, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    ));
}

function aprobacionDeRequisicion(Requisicion $requisicion, User $aprobador, string $estatus, int $nivel = 1): Aprobacion
{
    return Aprobacion::create([
        'aprobable_type' => Requisicion::class,
        'aprobable_id' => $requisicion->id,
        'nivel' => $nivel,
        'aprobador_id' => $aprobador->id,
        'estatus' => $estatus,
        'fecha_respuesta' => $estatus === 'aprobada' ? now() : null,
    ]);
}

function comparativoRenderizado(Requisicion $requisicion): string
{
    $requisicion->load(['solicitante', 'departamento', 'detalles.obraRubro.obra', 'detalles.obraRubro.rubro']);

    return view('pdf.costos.formato-requisicion-comparativo', [
        'requisicion' => $requisicion,
        'firmas' => app(FirmasPdfBuilder::class)->build(
            $requisicion->tipoAprobacion(),
            $requisicion->departamento_id,
            $requisicion->aprobaciones()->with('aprobador')->get(),
        ),
        'totales' => app(ComparativoTotalesBuilder::class)->build($requisicion),
        'esBorrador' => $requisicion->control_at === null,
    ])->render();
}

test('el nivel firmado imprime la firma guardada del aprobador', function () {
    firmaEnDisco('firmas/jefe-compras.png');

    $aprobador = User::factory()->create([
        'name' => 'MARIANA POOT',
        'firma_path' => 'firmas/jefe-compras.png',
    ]);

    aprobacionDeRequisicion($this->requisicion, $aprobador, 'aprobada');

    $html = comparativoRenderizado($this->requisicion);

    expect($html)->toContain('class="sig-img"')
        ->toContain('firmas/jefe-compras.png')
        ->toContain('MARIANA POOT')
        ->toContain('Jefe de compras');
});

test('el aprobador sin firma guardada deja el espacio para firmar a mano', function () {
    $aprobador = User::factory()->create(['name' => 'MARIANA POOT', 'firma_path' => null]);

    aprobacionDeRequisicion($this->requisicion, $aprobador, 'aprobada');

    $html = comparativoRenderizado($this->requisicion);

    expect($html)->not->toContain('class="sig-img"')
        ->toContain('sig-placeholder')
        ->toContain('MARIANA POOT');
});

test('la firma apuntada a un archivo que ya no esta no revienta el PDF', function () {
    $aprobador = User::factory()->create([
        'name' => 'MARIANA POOT',
        'firma_path' => 'firmas/borrada.png',
    ]);

    aprobacionDeRequisicion($this->requisicion, $aprobador, 'aprobada');

    $html = comparativoRenderizado($this->requisicion);

    expect($html)->not->toContain('class="sig-img"')
        ->toContain('sig-placeholder');
});

test('el nivel pendiente dice quien puede firmarlo, no solo "Pendiente"', function () {
    firmaEnDisco('firmas/otra.png');

    $uno = User::factory()->create(['name' => 'LUIS CHI', 'firma_path' => 'firmas/otra.png']);
    $dos = User::factory()->create(['name' => 'ANA CEN']);

    aprobacionDeRequisicion($this->requisicion, $uno, 'pendiente');
    aprobacionDeRequisicion($this->requisicion, $dos, 'pendiente');

    $html = comparativoRenderizado($this->requisicion);

    // Sin firmar: ni la imagen del candidato ni su nombre como firmante.
    expect($html)->not->toContain('class="sig-img"')
        ->toContain('LUIS CHI / ANA CEN');
});
