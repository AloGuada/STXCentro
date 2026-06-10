<?php

namespace Database\Seeders\Cotiz;

use App\Models\Cotiz\Cuadrilla;
use App\Models\Cotiz\FaseMontaje;
use App\Models\Cotiz\FleteViaticoCatalogo;
use Illuminate\Database\Seeder;

/**
 * Catálogos de montaje: cuadrillas, fases de montaje y el catálogo de fletes y
 * viáticos. Sus centro_costo_id ya vienen resueltos a id desde prepsim.
 * Idempotente: cuadrillas/fases por codigo; fletes por (grupo, orden).
 * Requiere CatalogosBaseSeeder (centros de costos) antes.
 */
class MontajeSeeder extends Seeder
{
    public function run(): void
    {
        $cuadrillas = require __DIR__.'/data/cuadrillas.php';
        Cuadrilla::query()->upsert(
            $cuadrillas,
            ['codigo'],
            ['nombre', 'centro_costo_id', 'rendimiento', 'formula_costo', 'descripcion'],
        );

        $fases = require __DIR__.'/data/fases_montaje.php';
        FaseMontaje::query()->upsert(
            $fases,
            ['codigo'],
            ['nombre', 'unidad', 'centro_costo_id', 'orden'],
        );

        // Fletes no tiene índice único en (grupo, orden), así que upsert no
        // deduplicaría. updateOrCreate por (grupo, orden) mantiene idempotencia.
        $fletes = require __DIR__.'/data/fletes_viaticos_catalogo.php';
        foreach ($fletes as $flete) {
            FleteViaticoCatalogo::query()->updateOrCreate(
                ['grupo' => $flete['grupo'], 'orden' => $flete['orden']],
                $flete,
            );
        }
    }
}
