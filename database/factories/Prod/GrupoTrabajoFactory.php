<?php

namespace Database\Factories\Prod;

use App\Models\Prod\GrupoTrabajo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\GrupoTrabajo>
 */
class GrupoTrabajoFactory extends Factory
{
    protected $model = GrupoTrabajo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descripcion' => 'Grupo '.fake()->unique()->word(),
            'linea' => fake()->numberBetween(1, 5),
            'modulo' => fake()->numberBetween(1, 10),
            'activo' => true,
        ];
    }
}
