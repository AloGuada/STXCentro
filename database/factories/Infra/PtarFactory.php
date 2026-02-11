<?php

namespace Database\Factories\Infra;

use App\Models\Infra\Ptar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Infra\Ptar>
 */
class PtarFactory extends Factory
{
    protected $model = Ptar::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'soplador_activa' => fake()->boolean(),
            'bomba_activa' => fake()->boolean(),
            'nivel_cloro' => fake()->randomFloat(2, 0, 100),
            'trampa_solida' => fake()->boolean(),
            'observaciones' => fake()->optional()->sentence(),
        ];
    }
}
