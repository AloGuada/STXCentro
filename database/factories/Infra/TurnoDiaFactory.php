<?php

namespace Database\Factories\Infra;

use App\Models\Infra\TurnoDia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Infra\TurnoDia>
 */
class TurnoDiaFactory extends Factory
{
    protected $model = TurnoDia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'infra_turno_id' => TurnoFactory::new(),
            'dia_semana' => fake()->numberBetween(1, 7),
        ];
    }
}
