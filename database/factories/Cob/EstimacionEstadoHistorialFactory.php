<?php

namespace Database\Factories\Cob;

use App\Models\Cob\Estimacion;
use App\Models\Cob\EstimacionEstadoHistorial;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EstimacionEstadoHistorial> */
class EstimacionEstadoHistorialFactory extends Factory
{
    protected $model = EstimacionEstadoHistorial::class;

    public function definition(): array
    {
        return [
            'estimacion_id' => Estimacion::factory(),
            'estado_anterior' => 'ingresada',
            'estado_nuevo' => 'autorizada',
            'folio' => fake()->optional()->regexify('FOL-[0-9]{4}'),
            'usuario_id' => User::factory(),
            'comentario' => fake()->optional()->sentence(),
            'fecha_cambio' => now(),
        ];
    }
}
