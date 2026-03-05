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
            ['orden' => 1, 'descripcion' => 'Pendiente', 'detiene_tiempo' => false, 'color' => '#eab308'],
            ['orden' => 2, 'descripcion' => 'Trabajando', 'detiene_tiempo' => false, 'color' => '#3b82f6'],
            ['orden' => 3, 'descripcion' => 'En espera del usuario', 'detiene_tiempo' => true, 'color' => '#f97316'],
            ['orden' => 4, 'descripcion' => 'En espera del proveedor', 'detiene_tiempo' => true, 'color' => '#f97316'],
            ['orden' => 5, 'descripcion' => 'En requisición de compras', 'detiene_tiempo' => true, 'color' => '#a855f7'],
            ['orden' => 6, 'descripcion' => 'En espera de Pago', 'detiene_tiempo' => true, 'color' => '#f97316'],
            ['orden' => 7, 'descripcion' => 'En espera de recepción', 'detiene_tiempo' => true, 'color' => '#f97316'],
            ['orden' => 8, 'descripcion' => 'Completado', 'detiene_tiempo' => false, 'color' => '#22c55e'],
            ['orden' => 9, 'descripcion' => 'Rechazado', 'detiene_tiempo' => false, 'color' => '#ef4444'],
            ['orden' => 10, 'descripcion' => 'Cancelado', 'detiene_tiempo' => false, 'color' => '#6b7280'],
            ['orden' => 11, 'descripcion' => 'Completado, con solicitud de baja', 'detiene_tiempo' => false, 'color' => '#22c55e'],
        ];

        foreach ($statuses as $status) {
            Status::updateOrCreate(
                ['descripcion' => $status['descripcion']],
                $status
            );
        }
    }
}
