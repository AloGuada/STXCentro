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
            'lectura_301' => fake()->randomFloat(2, 0, 1000),
            'lectura_302' => fake()->randomFloat(2, 0, 1000),
            'lectura_303' => fake()->randomFloat(2, 0, 1000),
            'lectura_310' => fake()->randomFloat(2, 0, 1000),
            'observaciones' => fake()->optional()->sentence(),
        ];
    }
}
