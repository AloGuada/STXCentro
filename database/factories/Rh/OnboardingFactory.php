<?php

namespace Database\Factories\Rh;

use App\Models\Rh\Onboarding;
use App\Models\Rh\PeriodoLaboral;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rh\Onboarding>
 */
class OnboardingFactory extends Factory
{
    protected $model = Onboarding::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'periodo_id' => PeriodoLaboral::factory(),
            'fecha_inicio' => now(),
            'progreso' => 0,
        ];
    }
}
