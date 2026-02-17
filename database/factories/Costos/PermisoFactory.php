<?php

namespace Database\Factories\Costos;

use App\Models\Costos\Permiso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\Permiso>
 */
class PermisoFactory extends Factory
{
    protected $model = Permiso::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descripcion' => fake()->randomElement(['Jefe Depto', 'Gerente', 'Director', 'Contralor']),
            'nivel' => fake()->numberBetween(1, 5),
        ];
    }
}
