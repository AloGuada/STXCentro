<?php

namespace Database\Factories\Prod;

use App\Models\Prod\Destajo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\Destajo>
 */
class DestajoFactory extends Factory
{
    protected $model = Destajo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'semana' => fake()->numberBetween(1, 52),
            'cerrada' => false,
        ];
    }

    public function cerrada(): static
    {
        return $this->state(fn () => [
            'cerrada' => true,
            'fecha_cierre' => now(),
        ]);
    }
}
