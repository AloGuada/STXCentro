<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Costos\DocumentoTipo;
use App\Http\Controllers\Controller;
use App\Models\Costos\ComplementoPago;
use App\Models\Costos\Factura;
use App\Models\Costos\NotaCredito;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Pago;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega al proveedor los archivos de sus propios documentos. Los adjuntos de
 * costos viven en el disco `public` con rutas predecibles, así que enlazarlos
 * como `/storage/{path}` dejaría leer los CFDI de cualquier otro proveedor:
 * todo pasa por aquí, que verifica propiedad y tipo de documento.
 */
class PortalMediaController extends Controller
{
    /**
     * Documentos que le pertenecen al proveedor. Todo lo demás (evidencias de
     * almacén, anexos internos de la OC o de una solicitud de pago) queda fuera
     * aunque cuelgue de un documento suyo.
     *
     * @var list<DocumentoTipo>
     */
    private const TIPOS_PERMITIDOS = [
        DocumentoTipo::XmlFactura,
        DocumentoTipo::PdfFactura,
        DocumentoTipo::ComprobanteRecepcion,
        DocumentoTipo::ComprobantePago,
        DocumentoTipo::Contrarecibo,
        DocumentoTipo::XmlNotaCredito,
        DocumentoTipo::PdfNotaCredito,
        DocumentoTipo::XmlComplementoPago,
        DocumentoTipo::PdfComplementoPago,
        DocumentoTipo::OcPdfFirmado,
    ];

    /** Se pueden incrustar en el visor; el resto se descarga. */
    private const MIMES_EMBEBIBLES = ['application/pdf'];

    public function show(Request $request, Media $media): StreamedResponse
    {
        $tipo = DocumentoTipo::tryFrom((string) $media->descripcion);
        abort_unless($tipo !== null && in_array($tipo, self::TIPOS_PERMITIDOS, true), 403);

        abort_unless($this->proveedorIdDe($media) === Auth::guard('proveedor')->id(), 403);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($media->path), 404);

        return $disk->response(
            $media->path,
            $media->nombre_original,
            ['Content-Type' => $media->mime],
            $this->debeDescargarse($request, $media) ? 'attachment' : 'inline',
        );
    }

    /**
     * Proveedor dueño del archivo, según a qué documento está adjunto. Un tipo
     * de `mediable` no contemplado devuelve null y termina en 403.
     */
    private function proveedorIdDe(Media $media): ?int
    {
        $mediable = $media->mediable;

        return match (true) {
            $mediable instanceof Factura, $mediable instanceof OrdenCompra => $mediable->proveedor_id,
            // Cubre también las parcialidades: comparten el pagable de su padre.
            $mediable instanceof Pago => $mediable->pagable instanceof Factura
                ? $mediable->pagable->proveedor_id
                : null,
            $mediable instanceof NotaCredito => $mediable->factura?->proveedor_id,
            $mediable instanceof ComplementoPago => $mediable->proveedor_id,
            default => null,
        };
    }

    /**
     * Solo se muestran en línea los formatos que el visor sabe incrustar; un XML
     * (o cualquier mime inesperado) siempre se descarga, para no servir contenido
     * interpretable desde el mismo origen.
     */
    private function debeDescargarse(Request $request, Media $media): bool
    {
        if ($request->boolean('download')) {
            return true;
        }

        $mime = (string) $media->mime;

        return ! in_array($mime, self::MIMES_EMBEBIBLES, true) && ! str_starts_with($mime, 'image/');
    }
}
