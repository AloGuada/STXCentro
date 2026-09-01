<?php

namespace Database\Seeders\Alm;

use Illuminate\Database\Seeder;

/**
 * Los layouts de carga que entregó el área, en el orden en que se dependen: el
 * catálogo primero —áreas, obras y almacenes, que nadie inventa— y encima el
 * saldo de cada almacén.
 *
 * Correrlo entero es seguro: cada carga se niega si su almacén ya abrió, así
 * que una tanda nueva sólo levanta lo que falta. **No borra nada**: los
 * almacenes que ya tienen inventario se quedan como están.
 *
 *   php artisan db:seed --class="Database\Seeders\Alm\LayoutsSeeder"
 */
class LayoutsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CatalogoLayoutSeeder::class,

            // Insumos: lo que se consume y se cuenta por cantidad.
            ConsSeeder::class,
            InsSeeder::class,
            MtoSeeder::class,
            ConstAmpliacionT4Seeder::class,
            ConstMbpSeeder::class,
            ConstParksNaveASeeder::class,
            ConstSixParksCancunSeeder::class,
            ConstTerrazaErnestoRosadoSeeder::class,
            ConstTresGuerrasSeeder::class,

            // Activos: lo que se sigue pieza por pieza.
            MonActivosSeeder::class,
            ProdActivosSeeder::class,
            InsActivosSeeder::class,
            MtoActivosSeeder::class,
            ConstAmpliacionT4ActivosSeeder::class,
            ConstMbpActivosSeeder::class,
            ConstParksNaveAActivosSeeder::class,
            ConstSixParksCancunActivosSeeder::class,
            ConstTerrazaErnestoRosadoActivosSeeder::class,
            ConstTresGuerrasActivosSeeder::class,
        ]);
    }
}
