<?php

namespace App\Services\Qal\Dosier;

use setasign\Fpdi\Fpdi;
use Throwable;

/**
 * El motor provisional: FPDI gratuito, que ya está en el proyecto.
 *
 * Abre los PDF que genera el sistema y la mayoría de los sencillos, pero no
 * los 1.5+ con object streams. Por eso `paginas()` se prueba al subir cada
 * archivo: el que no abre se marca como no compatible y queda fuera de la
 * unión, en vez de reventar la descarga entera.
 */
class UnidorFpdi implements UnidorDePdf
{
    public function paginas(string $ruta): ?int
    {
        try {
            return (new Fpdi)->setSourceFile($ruta);
        } catch (Throwable) {
            return null;
        }
    }

    public function unir(array $rutas, string $destino): void
    {
        $pdf = new Fpdi;

        foreach ($rutas as $ruta) {
            $total = $pdf->setSourceFile($ruta);

            for ($hoja = 1; $hoja <= $total; $hoja++) {
                $plantilla = $pdf->importPage($hoja);
                $tamano = $pdf->getTemplateSize($plantilla);
                $pdf->AddPage($tamano['orientation'], [$tamano['width'], $tamano['height']]);
                $pdf->useTemplate($plantilla);
            }
        }

        $pdf->Output($destino, 'F');
    }
}
