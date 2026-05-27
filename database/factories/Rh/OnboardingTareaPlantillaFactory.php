<?php

namespace Database\Factories\Rh;

use App\Models\Rh\OnboardingTareaPlantilla;
use App\Models\Rh\Puesto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rh\OnboardingTareaPlantilla>
 */
class OnboardingTareaPlantillaFactory extends Factory
{
    protected $model = OnboardingTareaPlantilla::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'puesto_id' => Puesto::factory(),
            'titulo' => fake()->sentence(4),
            'descripcion' => fake()->optional()->paragraph(),
            'dias_desde_inicio' => fake()->optional()->numberBetween(1, 30),
            'orden' => 0,
        ];
    }
}
