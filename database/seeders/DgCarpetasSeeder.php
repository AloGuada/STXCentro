<?php

namespace Database\Seeders;

use App\Models\Dg\Carpeta;
use Illuminate\Database\Seeder;

class DgCarpetasSeeder extends Seeder
{
    public function run(): void
    {
        $carpetas = [
            'Administración',
            'Compras',
            'Construcción',
            'Ingeniería',
            'Planta',
            'Producción',
            'Proyectos',
            'RRHH',
            'Ventas',
        ];

        foreach ($carpetas as $orden => $nombre) {
            Carpeta::firstOrCreate(
                ['nombre' => $nombre],
                ['orden' => $orden + 1],
            );
        }
    }
}
