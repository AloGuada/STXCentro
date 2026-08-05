<?php

use App\Enums\Costos\DocumentoTipo;
use App\Models\Costos\Entrega;
use App\Models\Costos\Factura;
use App\Models\Costos\Pago;
use App\Models\Media;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->proveedor = Proveedor::factory()->create([
        'email' => 'proveedor@test.com',
        'password' => bcrypt('password'),
        'tiene_acceso_portal' => true,
        'activo' => true,
    ]);
});

/** Media con archivo real en el disco falso, para que la descarga no dé 404. */
function mediaConArchivo(Model $mediable, DocumentoTipo $tipo, string $mime = 'application/pdf', string $nombre = 'documento.pdf'): Media
{
    $path = 'portal-test/'.uniqid().'.'.pathinfo($nombre, PATHINFO_EXTENSION);
    Storage::disk('public')->put($path, 'contenido');

    return Media::create([
        'descripcion' => $tipo->value,
        'nombre_original' => $nombre,
        'path' => $path,
        'mime' => $mime,
        'size' => 9,
        'mediable_type' => $mediable::class,
        'mediable_id' => $mediable->id,
    ]);
}

test('sirve en línea el PDF de una factura propia', function () {
    $factura = Factura::factory()->create(['proveedor_id' => $this->proveedor->id]);
    $media = mediaConArchivo($factura, DocumentoTipo::PdfFactura);

    $response = $this->actingAs($this->proveedor, 'proveedor')->get("/portal/media/{$media->id}");

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('inline');
});

test('el XML siempre se descarga, nunca se muestra en línea', function () {
    $factura = Factura::factory()->create(['proveedor_id' => $this->proveedor->id]);
    $media = mediaConArchivo($factura, DocumentoTipo::XmlFactura, 'text/xml', 'cfdi.xml');

    $response = $this->actingAs($this->proveedor, 'proveedor')->get("/portal/media/{$media->id}");

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('attachment');
});

test('el parámetro download fuerza la descarga de un PDF', function () {
    $factura = Factura::factory()->create(['proveedor_id' => $this->proveedor->id]);
    $media = mediaConArchivo($factura, DocumentoTipo::PdfFactura);

    $response = $this->actingAs($this->proveedor, 'proveedor')->get("/portal/media/{$media->id}?download=1");

    expect($response->headers->get('content-disposition'))->toContain('attachment');
});

test('sirve el comprobante del pago de una factura propia', function () {
    $factura = Factura::factory()->create(['proveedor_id' => $this->proveedor->id]);
    $pago = Pago::factory()->pagado()->create([
        'pagable_type' => Factura::class,
        'pagable_id' => $factura->id,
    ]);
    $media = mediaConArchivo($pago, DocumentoTipo::ComprobantePago);

    $this->actingAs($this->proveedor, 'proveedor')
        ->get("/portal/media/{$media->id}")
        ->assertOk();
});

test('no entrega el archivo de una factura de otro proveedor', function () {
    $media = mediaConArchivo(Factura::factory()->create(), DocumentoTipo::PdfFactura);

    $this->actingAs($this->proveedor, 'proveedor')
        ->get("/portal/media/{$media->id}")
        ->assertForbidden();
});

test('no entrega el comprobante de un pago ajeno', function () {
    $pago = Pago::factory()->pagado()->create([
        'pagable_type' => Factura::class,
        'pagable_id' => Factura::factory()->create()->id,
    ]);
    $media = mediaConArchivo($pago, DocumentoTipo::ComprobantePago);

    $this->actingAs($this->proveedor, 'proveedor')
        ->get("/portal/media/{$media->id}")
        ->assertForbidden();
});

test('no entrega evidencias internas de almacén aunque sean de su orden', function () {
    $factura = Factura::factory()->create(['proveedor_id' => $this->proveedor->id]);
    $entrega = Entrega::factory()->create(['orden_compra_id' => $factura->orden_compra_id]);
    $media = mediaConArchivo($entrega, DocumentoTipo::EvidenciaRecepcion, 'image/jpeg', 'foto.jpg');

    $this->actingAs($this->proveedor, 'proveedor')
        ->get("/portal/media/{$media->id}")
        ->assertForbidden();
});

test('da 404 si el registro existe pero el archivo no está en disco', function () {
    $factura = Factura::factory()->create(['proveedor_id' => $this->proveedor->id]);
    $media = mediaConArchivo($factura, DocumentoTipo::PdfFactura);
    Storage::disk('public')->delete($media->path);

    $this->actingAs($this->proveedor, 'proveedor')
        ->get("/portal/media/{$media->id}")
        ->assertNotFound();
});

test('sin sesión de proveedor manda al login', function () {
    $factura = Factura::factory()->create(['proveedor_id' => $this->proveedor->id]);
    $media = mediaConArchivo($factura, DocumentoTipo::PdfFactura);

    $this->get("/portal/media/{$media->id}")->assertRedirect('/portal/login');
});
