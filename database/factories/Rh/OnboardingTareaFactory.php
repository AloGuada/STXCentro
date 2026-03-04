<?php

namespace Database\Factories\Rh;

use App\Models\Rh\Onboarding;
use App\Models\Rh\OnboardingTarea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rh\OnboardingTarea>
 */
class OnboardingTareaFactory extends Factory
{
    protected $model = OnboardingTarea::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'onboarding_id' => Onboarding::factory(),
            'titulo' => fake()->sentence(3),
            'descripcion' => fake()->optional()->paragraph(),
            'completada' => false,
            'fecha_vencimiento' => fake()->optional()->dateTimeBetween('now', '+30 days'),
        ];
    }
}
