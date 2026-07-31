<?php

namespace Database\Seeders;

use App\Models\Prod\Ubicacion;
use Illuminate\Database\Seeder;

/**
 * Catálogo de módulos de trabajo (ubicaciones) de producción.
 *
 * Es idempotente: se puede correr en producción cuantas veces haga falta sin
 * duplicar ni reactivar lo que se haya dado de baja a mano.
 */
class ProdUbicacionSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const UBICACIONES = [
        '1era-Pintura',
        'M1.1 Arco 1 (4PL)',
        'M1.1 Arco 2 (4PL)',
        'M1.1 Corimp (3PL)',
        'M1.1 Fabricacion',
        'M1.2 Arco 1 (4PL)',
        'M1.2 Arco 2 (4PL)',
        'M1.2 Corimp (3PL)',
        'M1.2 Fabricacion',
        'M1.3 Arco 1 (4PL)',
        'M1.3 Arco 2 (4PL)',
        'M1.3 Corimp (3PL)',
        'M1.3 Fabricacion',
        'M1.4 Corimp (3PL)',
        'M1.4 Fabricacion',
        'M1.5 Arco 2 (4PL)',
        'M1.5 Corimp (3PL)',
        'M1.5 Fabricacion',
        'M1.6 Corimp (3PL)',
        'M1.6 Fabricacion',
        'M1.7 Corimp (3PL)',
        'M1.7 Fabricacion',
        'M1.8 Corimp (3PL)',
        'M1.8 Fabricacion',
        'M1.9 Arco 1 (4PL)',
        'M1.9 Corimp (3PL)',
        'M1.9 Fabricacion',
        'M2.1 Arco 3 (4PL)',
        'M2.1 Corimp (3PL)',
        'M2.1 Fabricacion',
        'M2.2 Arco 3 (4PL)',
        'M2.2 Corimp (3PL)',
        'M2.2 Fabricacion',
        'M2.3 Arco 3 (4PL)',
        'M2.3 Corimp (3PL)',
        'M2.3 Fabricacion',
        'M2.4 Arco 2 (4PL)',
        'M2.4 Corimp (3PL)',
        'M2.4 Fabricacion',
        'M2.5 Corimp (3PL)',
        'M2.5 Fabricacion',
        'M2.6 Corimp (3PL)',
        'M2.6 Fabricacion',
        'M2.7 Arco 3 (4PL)',
        'M2.7 Corimp (3PL)',
        'M2.7 Fabricacion',
        'M2.8 Corimp (3PL)',
        'M2.8 Fabricacion',
        'M2.9 Corimp (3PL)',
        'M2.9 Fabricacion',
        'M3.1 Corimp (3PL)',
        'M3.1 Fabricacion',
        'M3.2 Corimp (3PL)',
        'M3.2 Fabricacion',
        'M3.3 Corimp (3PL)',
        'M3.3 Fabricacion',
        'M3.4 Corimp (3PL)',
        'M3.4 Fabricacion',
        'M3.5 Arco 2 (4PL)',
        'M3.5 Corimp (3PL)',
        'M3.5 Fabricacion',
        'M3.6 Arco 2 (4PL)',
        'M3.6 Corimp (3PL)',
        'M3.6 Fabricacion',
        'M3.7 Corimp (3PL)',
        'M3.7 Fabricacion',
        'M3.8 Corimp (3PL)',
        'M3.8 Fabricacion',
        'M3.9 Corimp (3PL)',
        'M3.9 Fabricacion',
        'M3.10 Corimp (3PL)',
        'M3.10 Fabricacion',
        'M4.1 Corimp (3PL)',
        'M4.1 Fabricacion',
        'M4.2 Fabricacion',
        'M4.3 Arco 2 (4PL)',
        'M4.3 Corimp (3PL)',
        'M4.3 Fabricacion',
        'M4.4 Arco 2 (4PL)',
        'M4.4 Corimp (3PL)',
        'M4.4 Fabricacion',
        'M4.5 Corimp (3PL)',
        'M4.5 Fabricacion',
        'M4.6 Corimp (3PL)',
        'M4.6 Fabricacion',
        'M4.7 Corimp (3PL)',
        'M4.7 Fabricacion',
        'M4.8 Arco 2 (4PL)',
        'M4.8 Corimp (3PL)',
        'M4.8 Fabricacion',
        'M4.9 Arco 2 (4PL)',
        'M4.9 Corimp (3PL)',
        'M4.9 Fabricacion',
        'M4.10 Corimp (3PL)',
        'M4.10 Fabricacion',
        'M5.1 Corimp (3PL)',
        'M5.1 Fabricacion',
        'M5.2 Corimp (3PL)',
        'M5.2 Fabricacion',
        'M5.3 Corimp (3PL)',
        'M5.3 Fabricacion',
        'M5.4 Corimp (3PL)',
        'M5.4 Fabricacion',
        'M5.5 Corimp (3PL)',
        'M5.5 Fabricacion',
        'SC EN MOD - PINTURA',
        'SC EN MOD - PINTURA (PLS)',
    ];

    public function run(): void
    {
        foreach (self::UBICACIONES as $nombre) {
            Ubicacion::firstOrCreate(['nombre' => $nombre], ['activo' => true]);
        }
    }
}
