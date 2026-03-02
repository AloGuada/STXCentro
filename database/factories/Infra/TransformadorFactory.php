<?php

namespace Database\Factories\Infra;

use App\Models\Infra\Transformador;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Infra\Transformador>
 */
class TransformadorFactory extends Factory
{
    protected $model = Transformador::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'linea_A' => fake()->randomFloat(2, 100, 500),
            'linea_A_max' => fake()->randomFloat(2, 400, 600),
            'date_A' => fake()->dateTimeThisMonth(),
            'linea_B' => fake()->randomFloat(2, 100, 500),
            'linea_B_max' => fake()->randomFloat(2, 400, 600),
            'date_B' => fake()->dateTimeThisMonth(),
            'linea_C' => fake()->randomFloat(2, 100, 500),
            'linea_C_max' => fake()->randomFloat(2, 400, 600),
            'date_C' => fake()->dateTimeThisMonth(),
            'total_1' => fake()->randomFloat(2, 1000, 5000),
            'total_5' => fake()->randomFloat(2, 1000, 5000),
            'lectura_5y5' => fake()->randomFloat(2, 0, 1000),
            'registro_a' => fake()->randomFloat(2, 0, 1000),
            'registro_b' => fake()->randomFloat(2, 0, 1000),
            'registro_c' => fake()->randomFloat(2, 0, 1000),
            'tarifa' => fake()->randomFloat(2, 0, 10),
            'observaciones' => fake()->optional()->sentence(),
            'infra_turno_id' => null,
        ];
    }
}
