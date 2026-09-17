<?php

namespace App\Services\Qal\Ifc;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * El cliente del servicio `ifc-service`, que convierte el IFC en una marca por
 * archivo con sus cordones.
 *
 * El servicio corre aparte (ver `ifc-service/README.md`). La conversión es
 * asíncrona: se sube el archivo, se pregunta por el estado y las marcas se van
 * trayendo conforme el servicio las escribe; al final, el modelo entero.
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
        return $this->json("{$this->url}/estado/{$trabajo}", 'al preguntar por el trabajo');
    }

    /**
     * El index.json tal como va: las marcas ya escritas, y `completo` cuando
     * están todas.
     *
     * @return array{completo: bool, modelo?: string|null, welds_version?: string|null, marcas: array<string, array<string, mixed>>, totales?: array<string, mixed>}
     */
    public function marcas(string $trabajo): array
    {
        return $this->json("{$this->url}/resultado/{$trabajo}/marcas", 'al pedir las marcas');
    }

    /** Descarga el .glb o .json de una marca directo a disco. */
    public function descargarMarca(string $trabajo, string $archivo, string $destino): void
    {
        $this->descargar("{$this->url}/resultado/{$trabajo}/marcas/{$archivo}", $destino, "la marca {$archivo}");
    }

    /** Descarga el modelo entero (`modelo.glb`) directo a disco. */
    public function descargarModelo(string $trabajo, string $destino): void
    {
        $this->descargar("{$this->url}/resultado/{$trabajo}/modelo", $destino, 'el modelo completo');
    }

    /** Libera la carpeta temporal del servicio. Si falla no importa: el resultado ya está aquí. */
    public function borrar(string $trabajo): void
    {
        rescue(fn () => Http::timeout($this->timeout)->delete("{$this->url}/trabajos/{$trabajo}"), report: false);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string $url, string $que): array
    {
        $respuesta = Http::timeout($this->timeout)->get($url);

        // Si el servicio perdió la carpeta del trabajo, hay que volver a subirlo.
        if ($respuesta->status() === 404) {
            throw new RuntimeException('El servicio de IFC ya no tiene el trabajo. Reprocesa el modelo.');
        }

        if (! $respuesta->successful()) {
            throw new RuntimeException("El servicio de IFC respondió HTTP {$respuesta->status()} {$que}.");
        }

        return $respuesta->json();
    }

    private function descargar(string $url, string $destino, string $que): void
    {
        $respuesta = Http::timeout($this->timeout)->sink($destino)->get($url);

        if (! $respuesta->successful()) {
            throw new RuntimeException("El servicio de IFC no entregó {$que} (HTTP {$respuesta->status()}).");
        }
    }
}
