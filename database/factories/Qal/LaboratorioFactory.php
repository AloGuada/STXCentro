<?php

namespace Database\Factories\Qal;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\Laboratorio>
 */
class LaboratorioFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => mb_strtoupper($this->faker->unique()->company()),
            'siglas' => mb_strtoupper($this->faker->unique()->lexify('???')),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}
