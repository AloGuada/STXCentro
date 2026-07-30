<?php

use App\Models\Costos\Documento;
use App\Models\Costos\SolicitudArchivo;
use App\Models\Costos\SolicitudPago;
use App\Models\User;
use Smalot\PdfParser\Parser;

beforeEach(function () {
    $this->user = User::factory()->create();
    darPermisosSolicitudesPago($this->user);
});

/** Texto plano del PDF de la solicitud, para poder buscar dentro. */
function textoDelPdf(SolicitudPago $solicitud): string
{
    $respuesta = test()->actingAs(test()->user)
        ->get(route('admin.costos.solicitudes-pago.pdf', $solicitud));

    $respuesta->assertOk();

    return (new Parser)->parseContent($respuesta->getContent())->getText();
}

test('el pdf imprime las notas de la solicitud', function () {
    $solicitud = SolicitudPago::factory()->create([
        'solicitante_id' => $this->user->id,
        'comentarios' => 'Pago urgente autorizado por direccion',
    ]);

    expect(textoDelPdf($solicitud))->toContain('Notas')
        ->toContain('Pago urgente autorizado por direccion');
});

test('el pdf imprime el texto capturado en los documentos adjuntos', function () {
    $solicitud = SolicitudPago::factory()->create(['solicitante_id' => $this->user->id]);

    $documento = Documento::create([
        'tipo_solicitud_id' => $solicitud->tipo_solicitud_id,
        'titulo' => 'Factura',
        'texto' => 'Folio fiscal',
        'texto_adicional' => true,
    ]);

    SolicitudArchivo::create([
        'solicitud_id' => $solicitud->id,
        'archivo_id' => $documento->id,
        'texto_adicional' => 'ABC-123-XYZ',
    ]);

    expect(textoDelPdf($solicitud))->toContain('Folio fiscal')
        ->toContain('ABC-123-XYZ');
});

test('sin notas ni textos no se dibuja el bloque', function () {
    $solicitud = SolicitudPago::factory()->create([
        'solicitante_id' => $this->user->id,
        'comentarios' => null,
    ]);

    expect(textoDelPdf($solicitud))->not->toContain('Notas');
});

test('un adjunto sin texto capturado no ensucia el bloque', function () {
    $solicitud = SolicitudPago::factory()->create([
        'solicitante_id' => $this->user->id,
        'comentarios' => 'Con nota',
    ]);

    $documento = Documento::create([
        'tipo_solicitud_id' => $solicitud->tipo_solicitud_id,
        'titulo' => 'Cotizacion',
        'texto' => 'Referencia',
        'texto_adicional' => true,
    ]);

    SolicitudArchivo::create([
        'solicitud_id' => $solicitud->id,
        'archivo_id' => $documento->id,
        'texto_adicional' => null,
    ]);

    $texto = textoDelPdf($solicitud);

    expect($texto)->toContain('Con nota')
        ->and($texto)->not->toContain('Referencia');
});
