<?php

namespace Database\Factories\Infra;

use App\Models\Infra\Compresor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Infra\Compresor>
 */
class CompresorFactory extends Factory
{
    protected $model = Compresor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'compresor_1_status' => fake()->boolean(),
            'compresor_1_presion_aire' => fake()->randomFloat(2, 100, 130),
            'compresor_1_tiempo_trabajo' => fake()->randomFloat(2, 0, 24),
            'compresor_1_tiempo_marcha' => fake()->randomFloat(2, 0, 24),
            'compresor_1_kwhr' => fake()->randomFloat(2, 0, 500),
            'compresor_2_status' => fake()->boolean(),
            'compresor_2_presion_aire' => fake()->randomFloat(2, 100, 130),
            'compresor_2_tiempo_trabajo' => fake()->randomFloat(2, 0, 24),
            'compresor_2_tiempo_marcha' => fake()->randomFloat(2, 0, 24),
            'compresor_2_kwhr' => fake()->randomFloat(2, 0, 500),
            'compresor_3_status' => fake()->boolean(),
            'compresor_3_presion_aire' => fake()->randomFloat(2, 100, 130),
            'compresor_3_tiempo_trabajo' => fake()->randomFloat(2, 0, 24),
            'compresor_3_tiempo_marcha' => fake()->randomFloat(2, 0, 24),
            'compresor_3_kwhr' => fake()->randomFloat(2, 0, 500),
            'observaciones' => fake()->optional()->sentence(),
        ];
    }
}
