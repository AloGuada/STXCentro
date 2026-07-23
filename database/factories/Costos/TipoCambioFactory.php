<?php

namespace Database\Factories\Costos;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\TipoCambio>
 */
class TipoCambioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fecha' => now()->toDateString(),
            'moneda' => 'usd',
            'fuente' => 'banxico',
            'tasa' => $this->faker->randomFloat(6, 17, 20),
        ];
    }

    public function usd(): static
    {
        return $this->state(fn (): array => ['moneda' => 'usd', 'fuente' => 'banxico']);
    }

    public function eur(): static
    {
        return $this->state(fn (): array => ['moneda' => 'eur', 'fuente' => 'ecb', 'tasa' => $this->faker->randomFloat(6, 19, 23)]);
    }
}
