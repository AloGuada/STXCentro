<?php

namespace Database\Factories\Cal;

use App\Models\Cal\Soldador;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cal\Soldador>
 */
class SoldadorFactory extends Factory
{
    protected $model = Soldador::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'certificacion' => fake()->bothify('??-####'),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }
}
