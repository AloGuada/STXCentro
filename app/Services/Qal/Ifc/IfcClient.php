<?php

namespace App\Services\Qal\Ifc;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * El cliente del servicio `ifc-service`, que convierte el IFC en una marca por
 * archivo con sus cordones.
 *
 * El servicio corre aparte (ver `ifc-service/README.md`). La conversión es
 * asíncrona: se sube el archivo, se pregunta por el estado y, cuando termina,
 * se descarga el resultado en un zip.
 */
class IfcClient
{
    public function __construct(
        private readonly string $url,
        private readonly int $timeout,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            url: rtrim((string) config('services.ifc.url'), '/'),
            timeout: (int) config('services.ifc.timeout'),
        );
    }

    /** Sube el IFC y devuelve el id del trabajo en el servicio. */
    public function enviar(string $rutaIfc, string $nombre): string
    {
        $archivo = fopen($rutaIfc, 'r');

        try {
            $respuesta = Http::timeout($this->timeout)
                ->attach('archivo', $archivo, $nombre)
                ->post("{$this->url}/procesar");
        } finally {
            if (is_resource($archivo)) {
                fclose($archivo);
            }
        }

        if (! $respuesta->successful() || blank($respuesta->json('id'))) {
            throw new RuntimeException("El servicio de IFC no aceptó el archivo (HTTP {$respuesta->status()}): {$respuesta->body()}");
        }

        return (string) $respuesta->json('id');
    }

    /**
     * @return array{estado: string, progreso?: array{marcas_hechas: int, marcas_total: int}, welds_version?: string|null, error?: string|null}
     */
    public function estado(string $trabajo): array
    {
        $respuesta = Http::timeout($this->timeout)->get("{$this->url}/estado/{$trabajo}");

        // El servicio guarda los trabajos en memoria: si se reinició a media
        // conversión, el trabajo ya no existe y hay que volver a subirlo.
        if ($respuesta->status() === 404) {
            throw new RuntimeException('El servicio de IFC se reinició a media conversión y perdió el trabajo. Reprocesa el modelo.');
        }

        if (! $respuesta->successful()) {
            throw new RuntimeException("El servicio de IFC respondió HTTP {$respuesta->status()} al preguntar por el trabajo.");
        }

        return $respuesta->json();
    }

    /** Descarga el zip del resultado directo a disco: con cientos de marcas pesa decenas de MB. */
    public function descargarResultado(string $trabajo, string $destinoZip): void
    {
        $respuesta = Http::timeout($this->timeout)->sink($destinoZip)->get("{$this->url}/resultado/{$trabajo}");

        if (! $respuesta->successful()) {
            throw new RuntimeException("El servicio de IFC no entregó el resultado (HTTP {$respuesta->status()}).");
        }
    }

    /** Libera la carpeta temporal del servicio. Si falla no importa: el resultado ya está aquí. */
    public function borrar(string $trabajo): void
    {
        rescue(fn () => Http::timeout($this->timeout)->delete("{$this->url}/trabajos/{$trabajo}"), report: false);
    }
}
