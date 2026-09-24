<?php

namespace App\Services\Qal;

use App\Services\Ocr\OcrClient;
use Illuminate\Http\UploadedFile;

/**
 * Las lecturas de espesor que se ven en la pantalla del calibre (PosiTector
 * 6000), sacadas de una foto.
 *
 * La pantalla lista las lecturas en tres columnas —número, espesor con
 * decimales y hora— de diez en diez. De las tres sólo interesa la del medio,
 * y se reconoce por la forma: el número de renglón es entero y la hora lleva
 * dos puntos, así que lo único que queda con decimales es el espesor.
 *
 * Es deliberadamente tonto: no intenta entender la rejilla ni casar columnas,
 * porque el OCR devuelve las celdas sueltas y en orden de lectura. Lo que sale
 * de aquí lo revisa el inspector en la rejilla antes de guardar — un espesor
 * decide si la pieza pasa, y no puede fijarlo un OCR sin que nadie lo mire.
 */
class LecturasDelCalibre
{
    /** Un espesor: dígitos, separador decimal y más dígitos. Nada más. */
    private const ESPESOR = '/^\d{1,3}[.,]\d{1,2}$/';

    /**
     * Ningún espesor de pintura real llega aquí: un valor así es que el OCR
     * leyó mal, o que se coló un dato que no era una lectura.
     */
    private const MAXIMO = 500.0;

    public function __construct(private readonly OcrClient $ocr) {}

    /**
     * Los espesores de la foto, en el orden en que salen en la pantalla.
     *
     * @return list<float>
     */
    public function deFoto(UploadedFile $foto): array
    {
        return $this->deLineas($this->ocr->lineas($foto));
    }

    /**
     * @param  list<string>  $lineas
     * @return list<float>
     */
    public function deLineas(array $lineas): array
    {
        $espesores = [];

        foreach ($lineas as $linea) {
            $texto = trim($linea);

            // La hora («10:31») trae dígitos y separador: se descarta por los
            // dos puntos antes de mirar si parece un número.
            if (str_contains($texto, ':')) {
                continue;
            }

            if (preg_match(self::ESPESOR, $texto) !== 1) {
                continue;
            }

            $valor = (float) str_replace(',', '.', $texto);

            if ($valor > 0 && $valor <= self::MAXIMO) {
                $espesores[] = $valor;
            }
        }

        return $espesores;
    }
}
