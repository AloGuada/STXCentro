<?php

namespace Database\Seeders;

use App\Models\Prod\Ubicacion;
use Illuminate\Database\Seeder;

/**
 * Catálogo de ubicaciones (transformaciones, módulos, líneas y máquinas) de producción.
 *
 * Es la única fuente de verdad: lo que no esté en esta lista se borra, incluso si
 * estaba asignado a grupos de trabajo (esa asignación se suelta antes de borrar).
 * Correrlo de nuevo no duplica nada ni reactiva lo que se haya dado de baja a mano.
 */
class ProdUbicacionSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const UBICACIONES = [
        '2T 2da Transformación',
        'Zona de embarques',
        'Zona de producto terminado',
        '1T 1era Transformación',
        '3T 3era Transformación',
        '2T Modulo 1.1',
        '2T Modulo 1.2',
        '2T Modulo 1.3',
        '3T L1 Modulo 1',
        '3T L1 Modulo 2',
        '3T L1 Modulo 3',
        '3T L2 Modulo 4',
        '3T L2 Modulo 5',
        '3T L2 Modulo 6',
        '2T Modulo 1.4',
        '2T Modulo 1.5',
        '2T Modulo 1.6',
        '2T Modulo 1.7',
        '2T Modulo 1.8',
        '2T Modulo 1.9',
        '2T Modulo 2.1',
        '2T Modulo 2.2',
        '2T Modulo 2.3',
        'Corimpex',
        '2T Modulo 2.4',
        '2T Modulo 2.5',
        '2T Modulo 2.6',
        '2T Modulo 2.7',
        '2T Modulo 2.8',
        '2T Modulo 2.9',
        '2T Modulo 3.1',
        '2T Modulo 3.2',
        '2T Modulo 3.3',
        '2T Modulo 3.4',
        '2T Modulo 3.5',
        '2T Modulo 3.6',
        '2T Modulo 3.7',
        '2T Modulo 3.8',
        '2T Modulo 3.9',
        '2T Modulo 3.10',
        '2T Modulo 4.1',
        '2T Modulo 4.2 Robot Somey',
        '2T Modulo 4.3',
        '2T Modulo 4.4',
        '2T Modulo 4.5',
        '2T Modulo 4.6',
        '2T Modulo 4.7',
        '2T Modulo 4.8',
        '2T Modulo 4.9',
        '2T Modulo 4.10',
        '2T Modulo 5.1',
        '2T Modulo 5.2',
        '2T Modulo 5.3',
        '2T Modulo 5.4',
        '2T Modulo 5.5',
        'Arco 1',
        'Arco 2',
        'Arco 3',
        '3T L3 Modulo 7',
        '3T L3 Modulo 8',
        '3T L3 Modulo 9',
        '3T L4 Modulo 10',
        '3T L4 Modulo 11',
        '3T L4 Modulo 12',
        '3T L4 Modulo 13',
        '3T L5 Modulo 14',
        '3T L5 Modulo 15',
        '3T L5 Modulo 16',
        'RAZ',
        'VALIANT',
        'GEMINI',
        'KRONOS',
        'SOMEY',
        'LINCOLN',
    ];

    public function run(): void
    {
        foreach (self::UBICACIONES as $nombre) {
            Ubicacion::firstOrCreate(['nombre' => $nombre], ['activo' => true]);
        }

        $this->borrarLasQueYaNoExisten();
    }

    /**
     * Las ubicaciones fuera del catálogo se van, aunque algún grupo de trabajo
     * las tuviera asignadas: primero se suelta el pivote y luego se borran.
     */
    private function borrarLasQueYaNoExisten(): void
    {
        Ubicacion::query()
            ->whereNotIn('nombre', self::UBICACIONES)
            ->get()
            ->each(function (Ubicacion $ubicacion): void {
                $ubicacion->gruposTrabajo()->detach();
                $ubicacion->delete();
            });
    }
}
