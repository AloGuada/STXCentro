<?php

namespace Database\Factories\Infra;

use App\Models\Infra\Turno;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Infra\Turno>
 */
class TurnoFactory extends Factory
{
    protected $model = Turno::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->word(),
            'hora_inicio' => '08:00',
            'hora_fin' => '12:00',
            'orden' => fake()->numberBetween(0, 10),
            'activo' => true,
        ];
    }
}
