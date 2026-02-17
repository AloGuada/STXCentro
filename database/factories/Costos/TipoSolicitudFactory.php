<?php

namespace Database\Factories\Costos;

use App\Models\Costos\TipoSolicitud;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\TipoSolicitud>
 */
class TipoSolicitudFactory extends Factory
{
    protected $model = TipoSolicitud::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'titulo' => fake()->words(3, true),
            'descripcion' => fake()->optional()->sentence(),
            'rubros' => fake()->boolean(),
        ];
    }
}
