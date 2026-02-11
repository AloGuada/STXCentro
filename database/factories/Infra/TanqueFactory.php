<?php

namespace Database\Factories\Infra;

use App\Models\Infra\Tanque;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Infra\Tanque>
 */
class TanqueFactory extends Factory
{
    protected $model = Tanque::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pa_sistema_oxigeno' => fake()->randomFloat(2, 0, 300),
            'presion_sistema_oxigeno' => fake()->randomFloat(2, 100, 300),
            'presion_tanque_oxigeno' => fake()->randomFloat(2, 100, 300),
            'lt_tanque_oxigeno' => fake()->randomFloat(2, 0, 10000),
            'kg_tanque_oxigeno' => fake()->randomFloat(2, 0, 5000),
            'pa_sistema_argon' => fake()->randomFloat(2, 0, 300),
            'presion_sistema_argon' => fake()->randomFloat(2, 100, 300),
            'presion_tanque_argon' => fake()->randomFloat(2, 100, 300),
            'lt_tanque_argon' => fake()->randomFloat(2, 0, 10000),
            'kg_tanque_argon' => fake()->randomFloat(2, 0, 5000),
            'pa_sistema_co2' => fake()->randomFloat(2, 0, 300),
            'presion_sistema_co2' => fake()->randomFloat(2, 100, 300),
            'presion_tanque_co2' => fake()->randomFloat(2, 100, 300),
            'lt_tanque_co2' => fake()->randomFloat(2, 0, 10000),
            'kg_tanque_co2' => fake()->randomFloat(2, 0, 5000),
            'pa_sistema_lp' => fake()->randomFloat(2, 0, 300),
            'presion_sistema_lp' => fake()->randomFloat(2, 100, 300),
            'presion_tanque_lp' => fake()->randomFloat(2, 100, 300),
            'numero_tanque_lp' => fake()->randomFloat(2, 1, 10),
            'lt_tanque_lp' => fake()->randomFloat(2, 0, 10000),
            'kg_tanque_lp' => fake()->randomFloat(2, 0, 5000),
            'observaciones' => fake()->optional()->sentence(),
        ];
    }
}
