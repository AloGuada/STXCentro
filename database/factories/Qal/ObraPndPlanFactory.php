<?php

namespace Database\Factories\Qal;

use App\Enums\Qal\MetodoPnd;
use App\Models\Qal\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\ObraPndPlan>
 */
class ObraPndPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'qal_obra_id' => Obra::factory(),
            'metodo' => $this->faker->randomElement(MetodoPnd::cases()),
            'comprometidas' => $this->faker->numberBetween(1, 120),
        ];
    }
}
