<?php

namespace Database\Seeders\Cotiz;

use App\Models\Cotiz\Insumo;
use Illuminate\Database\Seeder;

/**
 * Catálogo de insumos (~248). FKs (unidad_id, centro_costo_id,
 * categoria_tarjeta_id) ya vienen resueltos a id en el archivo de datos.
 * Idempotente: upsert por descripcion (única). peso_lineal/peso_default/
 * codigo_stumis quedan null (no vienen de prepsim).
 */
class InsumoSeeder extends Seeder
{
    public function run(): void
    {
        $filas = require __DIR__.'/data/insumos.php';

        foreach (array_chunk($filas, 200) as $chunk) {
            Insumo::query()->upsert(
                $chunk,
                ['descripcion'],
                ['unidad_id', 'precio_unitario', 'centro_costo_id', 'categoria_tarjeta_id'],
            );
        }
    }
}
