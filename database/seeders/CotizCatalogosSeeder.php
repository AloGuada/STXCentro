<?php

namespace Database\Seeders;

use Database\Seeders\Cotiz\CatalogosBaseSeeder;
use Database\Seeders\Cotiz\FactorSeeder;
use Database\Seeders\Cotiz\InsumoSeeder;
use Database\Seeders\Cotiz\MontajeSeeder;
use Illuminate\Database\Seeder;

/**
 * Orquestador de los catálogos globales del módulo Cotización (cotiz_),
 * portados desde la app prepsim. Idempotente y re-ejecutable.
 *
 * Orden: catálogos base (unidades, centros de costos, categorías de tarjeta,
 * etc.) → insumos → factores y montaje (dependen de insumos / centros de costos).
 */
class CotizCatalogosSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CatalogosBaseSeeder::class,
            InsumoSeeder::class,
            FactorSeeder::class,
            MontajeSeeder::class,
        ]);
    }
}
