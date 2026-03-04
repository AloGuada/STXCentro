<?php

namespace Database\Factories\Rh;

use App\Models\Rh\Candidatura;
use App\Models\Rh\Persona;
use App\Models\Rh\Requisicion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rh\Candidatura>
 */
class CandidaturaFactory extends Factory
{
    protected $model = Candidatura::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'requisicion_id' => Requisicion::factory(),
            'persona_id' => Persona::factory(),
            'fecha_aplicacion' => fake()->date(),
            'porcentaje_match' => fake()->optional()->randomFloat(2, 0, 100),
            'porcentaje_skills' => fake()->optional()->randomFloat(2, 0, 100),
            'porcentaje_requisitos' => fake()->optional()->randomFloat(2, 0, 100),
        ];
    }
}
