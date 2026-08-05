<?php

namespace Database\Factories\Prod;

use App\Models\Prod\Proceso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\Proceso>
 */
class ProcesoFactory extends Factory
{
    protected $model = Proceso::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->word(),
            'orden' => fake()->numberBetween(1, 20),
            'activo' => true,
        ];
    }

    /** El proceso que ya viene sembrado por la migración. */
    public function soldadura(): static
    {
        return $this->state(['nombre' => 'Soldadura', 'orden' => 1]);
    }

    public function pintura(): static
    {
        return $this->state(['nombre' => 'Pintura', 'orden' => 2]);
    }
}
