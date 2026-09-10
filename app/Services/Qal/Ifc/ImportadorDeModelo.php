<?php

namespace App\Services\Qal\Ifc;

use App\Enums\Qal\EstatusModelo;
use App\Models\Qal\Modelo;
use App\Services\Qal\ResolutorDeMarcas;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Lee lo que dejó el servicio de IFC (index.json y la ficha de cada marca) y
 * lo guarda como marcas y cordones del modelo.
 *
 * Va en una transacción: un modelo a medio importar mostraría marcas sin sus
 * cordones, y sobre esos cordones se capturan juntas. Volver a importar
 * reemplaza lo anterior del mismo modelo.
 */
class ImportadorDeModelo
{
    public function __construct(private readonly ResolutorDeMarcas $resolutor) {}

    public function importar(Modelo $modelo, string $directorio): void
    {
        $indice = $this->leer("{$directorio}/index.json");
        $conceptos = $this->resolutor->deLaObra($modelo->obra_id);

        DB::transaction(function () use ($modelo, $directorio, $indice, $conceptos): void {
            $modelo->marcas()->delete();

            $cordones = 0;
            $sinCatalogo = 0;

            foreach ($indice['marcas'] ?? [] as $marca => $datos) {
                $ficha = $this->leer("{$directorio}/marks/{$datos['file']}.json");
                $concepto = $this->resolutor->conceptoDe((string) $marca, $conceptos);

                $fila = $modelo->marcas()->create([
                    'marca' => (string) $marca,
                    'archivo' => $datos['file'],
                    'concepto_id' => $concepto,
                    'nombre' => $datos['nombre'] ?? null,
                    'piezas' => $datos['piezas'] ?? 0,
                    'peso_kg' => $datos['peso_kg'] ?? 0,
                    'ensambles' => $datos['ensambles'] ?? 0,
                    'soldaduras' => $datos['soldaduras'] ?? 0,
                    'bbox_mm' => $datos['bbox_mm'] ?? null,
                ]);

                foreach (array_chunk($ficha['soldaduras'] ?? [], 500) as $bloque) {
                    $fila->cordones()->createMany(array_map(fn (array $cordon): array => [
                        'numero' => $cordon['id'],
                        'tipo' => $cordon['tipo'],
                        'junta' => $cordon['junta'] ?? null,
                        'piezas' => $cordon['piezas'] ?? [],
                        'largo_mm' => $cordon['largo_mm'],
                        'ancho_mm' => $cordon['ancho_mm'] ?? null,
                        'angulo' => $cordon['angulo'] ?? null,
                        't1_mm' => $cordon['t1_mm'] ?? null,
                        't2_mm' => $cordon['t2_mm'] ?? null,
                        'cateto_min_mm' => $cordon['cateto_min_mm'] ?? null,
                        'cateto_max_mm' => $cordon['cateto_max_mm'] ?? null,
                        'garganta_min_mm' => $cordon['garganta_min_mm'] ?? null,
                        'preparacion' => $cordon['preparacion'] ?? null,
                        'avisos' => $cordon['avisos'] ?? [],
                        'centro' => $cordon['centro'] ?? null,
                        'puntos' => $cordon['puntos'],
                    ], $bloque));
                }

                $cordones += count($ficha['soldaduras'] ?? []);
                $sinCatalogo += $concepto === null ? 1 : 0;
            }

            $modelo->update([
                'estatus' => EstatusModelo::Listo,
                'welds_version' => $indice['welds_version'] ?? null,
                'resumen' => [
                    'marcas' => count($indice['marcas'] ?? []),
                    'cordones' => $cordones,
                    'soldadura_mm' => $indice['totales']['soldadura_mm'] ?? null,
                    'marcas_sin_catalogo' => $sinCatalogo,
                ],
                'error' => null,
                'procesado_at' => now(),
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function leer(string $ruta): array
    {
        if (! is_file($ruta)) {
            throw new RuntimeException('El resultado del servicio no trae '.basename($ruta).'.');
        }

        $datos = json_decode((string) file_get_contents($ruta), true);

        if (! is_array($datos)) {
            throw new RuntimeException(basename($ruta).' no es JSON válido.');
        }

        return $datos;
    }
}
