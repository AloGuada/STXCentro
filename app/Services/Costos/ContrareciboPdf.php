<?php

namespace App\Services\Costos;

use App\Models\Costos\Factura;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;

/**
 * Formato de contrarecibo de una factura. Vive fuera del controlador porque lo
 * emiten dos guards distintos: el admin desde la orden de compra y el proveedor
 * desde el portal. El PDF se genera al vuelo, no se persiste.
 */
class ContrareciboPdf
{
    public function render(Factura $factura): PdfDocument
    {
        $factura->loadMissing('ordenCompra.proveedor');

        return Pdf::loadView('pdf.costos.formato-contrarecibo', [
            'oc' => $factura->ordenCompra,
            'factura' => $factura,
            'fechaPago' => $factura->pagoRaiz?->fecha_pago_programada,
        ])->setPaper('letter', 'portrait');
    }
}
