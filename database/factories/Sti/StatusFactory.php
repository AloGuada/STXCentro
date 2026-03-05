<?php

namespace Database\Factories\Sti;

use App\Models\Sti\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sti\Status>
 */
class StatusFactory extends Factory
{
    protected $model = Status::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descripcion' => fake()->word(),
            'orden' => fake()->numberBetween(1, 10),
            'detiene_tiempo' => fake()->boolean(20),
            'color' => fake()->hexColor(),
        ];
    }

    public function detieneTiempo(): static
    {
        return $this->state(fn (array $attributes) => [
            'detiene_tiempo' => true,
        ]);
    }
}
