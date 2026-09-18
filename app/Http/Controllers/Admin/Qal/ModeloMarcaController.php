<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Models\Qal\ModeloCordon;
use App\Models\Qal\ModeloMarca;
use App\Services\Qal\EstadoDeCordones;
use Illuminate\Http\JsonResponse;

/**
 * Lo que necesita el visor de una marca: dónde está su geometría y sus
 * cordones, cada uno con cómo va según sus juntas.
 *
 * La pide la captura de soldado, que reporta cada junta sobre un cordón, la
 * pantalla del modelo 3D en Producción y la hoja del mapeo de Registros. Por
 * eso vive en Calidad y basta con poder capturar para leerla.
 */
class ModeloMarcaController extends Controller
{
    public function show(ModeloMarca $modeloMarca, EstadoDeCordones $estados): JsonResponse
    {
        $cordones = $modeloMarca->cordones()->get();
        $porCordon = $estados->de($cordones->pluck('id')->all());

        return response()->json([
            'id' => $modeloMarca->id,
            'modelo_id' => $modeloMarca->modelo_id,
            'marca' => $modeloMarca->marca,
            // El encabezado de la hoja imprimible.
            'nombre' => $modeloMarca->nombre,
            'piezas' => $modeloMarca->piezas,
            'peso_kg' => $modeloMarca->peso_kg,
            'ensambles' => $modeloMarca->ensambles,
            'bbox_mm' => $modeloMarca->bbox_mm,
            'glb_url' => $modeloMarca->glbUrl(),
            'ficha_url' => $modeloMarca->fichaUrl(),
            'cordones' => $cordones->map(fn (ModeloCordon $cordon): array => [
                'id' => $cordon->id,
                'numero' => $cordon->numero,
                'identificador' => $cordon->identificador(),
                'junta_id' => $cordon->junta_id,
                'remate' => $cordon->remate,
                'tipo' => $cordon->tipo->value,
                'junta' => $cordon->junta,
                'piezas' => $cordon->piezas,
                'largo_mm' => $cordon->largo_mm,
                'angulo' => $cordon->angulo,
                't1_mm' => $cordon->t1_mm,
                't2_mm' => $cordon->t2_mm,
                'cateto_min_mm' => $cordon->cateto_min_mm,
                'cateto_max_mm' => $cordon->cateto_max_mm,
                'garganta_min_mm' => $cordon->garganta_min_mm,
                'preparacion' => $cordon->preparacion,
                'avisos' => $cordon->avisos ?? [],
                'puntos' => $cordon->puntos,
                ...$porCordon[$cordon->id],
            ])->values(),
        ]);
    }
}
