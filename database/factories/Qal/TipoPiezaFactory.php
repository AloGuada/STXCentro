<?php

namespace Database\Factories\Qal;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\TipoPieza>
 */
class TipoPiezaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prefijo' => mb_strtoupper($this->faker->unique()->lexify('??')),
            'descripcion' => ucfirst($this->faker->words(2, true)),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}
