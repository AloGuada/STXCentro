<?php

namespace App\Services\Qal\Ifc;

use App\Enums\Qal\EstatusModelo;
use App\Models\Qal\Modelo;
use App\Models\Qal\ModeloMarca;
use App\Services\Qal\ResolutorDeMarcas;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Guarda lo que deja el servicio de IFC: cada marca con su modelo (.glb), su
 * plantilla (.json) y sus cordones en el orden en que la plantilla los numera.
 *
 * Va marca por marca y no todo al final: el servicio las escribe conforme las
 * termina, y una marca guardada ya sirve para el visor aunque el modelo siga
 * convirtiéndose. Cada marca es una transacción —una marca a medias mostraría
 * cordones incompletos, y sobre esos cordones se capturan juntas— y volver a
 * importar la misma marca reemplaza la anterior.
 */
class ImportadorDeModelo
{
    public function __construct(private readonly ResolutorDeMarcas $resolutor) {}

    /**
     * @param  array<string, mixed>  $entrada  la línea de la marca en el index.json
     * @param  string  $ficha  ruta del .json de la marca ya en disco
     */
    public function importarMarca(Modelo $modelo, string $marca, array $entrada, string $ficha): ModeloMarca
    {
        $datos = $this->leer($ficha);
        $concepto = $this->resolutor->conceptoDe($marca, $this->resolutor->deLaObra($modelo->obra_id));

        return DB::transaction(function () use ($modelo, $marca, $entrada, $datos, $concepto): ModeloMarca {
            $modelo->marcas()->where('marca', $marca)->delete();

            $fila = $modelo->marcas()->create([
                'marca' => $marca,
                'archivo' => $entrada['file'],
                'concepto_id' => $concepto,
                'nombre' => $entrada['nombre'] ?? null,
                'piezas' => $entrada['piezas'] ?? 0,
                'peso_kg' => $entrada['peso_kg'] ?? 0,
                'ensambles' => $entrada['ensambles'] ?? 0,
                'soldaduras' => $entrada['soldaduras'] ?? 0,
                'bbox_mm' => $entrada['bbox_mm'] ?? null,
            ]);

            // En el orden de la plantilla: el número del cordón es el que ve el
            // inspector en el visor y en la hoja del mapeo.
            foreach (array_chunk($datos['soldaduras'] ?? [], 500) as $bloque) {
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

            return $fila;
        });
    }

    /**
     * El modelo queda listo con las cuentas de lo que se guardó.
     *
     * @param  array<string, mixed>  $indice  el index.json completo
     */
    public function cerrar(Modelo $modelo, array $indice): void
    {
        $modelo->update([
            'estatus' => EstatusModelo::Listo,
            'welds_version' => $indice['welds_version'] ?? null,
            'archivo_modelo' => $indice['modelo'] ?? null,
            'resumen' => [
                'marcas' => $modelo->marcas()->count(),
                'cordones' => $modelo->cordones()->count(),
                'soldadura_mm' => $indice['totales']['soldadura_mm'] ?? null,
                'marcas_sin_catalogo' => $modelo->marcas()->whereNull('concepto_id')->count(),
            ],
            'error' => null,
            'procesado_at' => now(),
        ]);
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
