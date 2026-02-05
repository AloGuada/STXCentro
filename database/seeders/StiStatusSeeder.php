<?php

namespace Database\Seeders;

use App\Models\Sti\Status;
use Illuminate\Database\Seeder;

class StiStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            ['orden' => 1, 'descripcion' => 'Pendiente', 'detiene_tiempo' => false],
            ['orden' => 2, 'descripcion' => 'Trabajando', 'detiene_tiempo' => false],
            ['orden' => 3, 'descripcion' => 'En espera del usuario', 'detiene_tiempo' => true],
            ['orden' => 4, 'descripcion' => 'En espera del proveedor', 'detiene_tiempo' => true],
            ['orden' => 5, 'descripcion' => 'En requisición de compras', 'detiene_tiempo' => true],
            ['orden' => 6, 'descripcion' => 'En espera de Pago', 'detiene_tiempo' => true],
            ['orden' => 7, 'descripcion' => 'En espera de recepción', 'detiene_tiempo' => true],
            ['orden' => 8, 'descripcion' => 'Completado', 'detiene_tiempo' => false],
            ['orden' => 9, 'descripcion' => 'Rechazado', 'detiene_tiempo' => false],
            ['orden' => 10, 'descripcion' => 'Cancelado', 'detiene_tiempo' => false],
            ['orden' => 11, 'descripcion' => 'Completado, con solicitud de baja', 'detiene_tiempo' => false],
        ];

        foreach ($statuses as $status) {
            Status::updateOrCreate(
                ['descripcion' => $status['descripcion']],
                $status
            );
        }
    }
}
