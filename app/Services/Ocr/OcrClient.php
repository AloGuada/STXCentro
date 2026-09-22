<?php

namespace App\Services\Ocr;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * El sidecar de OCR (`ocr-service/`), que reconoce el texto de una imagen o un
 * PDF con PaddleOCR.
 *
 * Es un proceso aparte porque el modelo tarda en cargar y come memoria:
 * levantarlo por petición haría que cada lectura costara medio minuto. Si no
 * está corriendo, quien llame recibe un error claro en vez de un fallo de red
 * a secas — es lo primero que hay que mirar cuando «el OCR no hace nada».
 */
class OcrClient
{
    /**
     * Las líneas de texto reconocidas, en el orden en que se leyeron.
     *
     * @return list<string>
     */
    public function lineas(UploadedFile $archivo): array
    {
        $url = rtrim((string) config('services.paddle_ocr.url'), '/').'/ocr';

        try {
            $respuesta = Http::timeout((int) config('services.paddle_ocr.timeout'))
                ->attach('file', $archivo->get(), $archivo->getClientOriginalName() ?: 'imagen')
                ->post($url);
        } catch (ConnectionException $error) {
            throw new RuntimeException(
                'El servicio de OCR no responde. Arráncalo con: uvicorn main:app --port 8800 (en ocr-service/).',
                previous: $error,
            );
        }

        if ($respuesta->failed()) {
            throw new RuntimeException('El servicio de OCR no pudo leer la imagen: '.$respuesta->json('detail', 'error desconocido'));
        }

        /** @var list<string> $lineas */
        $lineas = $respuesta->json('lines', []);

        return $lineas;
    }

    /** Si el sidecar está levantado. Para diagnosticar, no para cada lectura. */
    public function disponible(): bool
    {
        try {
            return Http::timeout(5)->get(rtrim((string) config('services.paddle_ocr.url'), '/').'/health')->successful();
        } catch (ConnectionException) {
            return false;
        }
    }
}
