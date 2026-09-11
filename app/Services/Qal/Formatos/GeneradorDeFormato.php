<?php

namespace App\Services\Qal\Formatos;

use App\Models\Usuario;
use App\Services\Qal\FirmasDeFormato;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DocumentoPdf;

/**
 * Arma el PDF de un formato: sus datos, las firmas en el orden del catálogo y
 * «Hoja n de N» en la caja del código de cada hoja.
 *
 * El total de hojas sólo se sabe al terminar de acomodar el documento, y
 * dompdf no lo resuelve desde el CSS (`counter(pages)` sale en cero). Por eso
 * se escribe sobre el lienzo después de renderizar, en el hueco que el
 * encabezado deja en la tercera línea de la caja.
 */
class GeneradorDeFormato
{
    /** Coordenadas del hueco, en puntos desde la esquina superior izquierda de la carta horizontal. */
    public const HOJA_X = 632;

    public const HOJA_Y = 56;

    public function __construct(private FirmasDeFormato $firmas) {}

    public function pdf(Formato $formato, FiltrosDeReporte $filtros): DocumentoPdf
    {
        $datos = $formato->datos($filtros);
        $creador = $datos['creador_id'] ? Usuario::query()->find($datos['creador_id']) : null;

        $pdf = Pdf::loadView('pdf.qal.formatos.'.$formato->vista(), [
            'formato' => $formato,
            'datos' => $datos,
            'firmas' => $this->firmas->para($creador),
        ])->setPaper('letter', 'landscape');

        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $dompdf->getCanvas()->page_text(
            self::HOJA_X,
            self::HOJA_Y,
            'Hoja {PAGE_NUM} de {PAGE_COUNT}',
            $dompdf->getFontMetrics()->getFont('helvetica'),
            7.5,
        );

        return $pdf;
    }
}
