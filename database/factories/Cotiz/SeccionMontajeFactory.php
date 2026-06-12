<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\Obra;
use App\Models\Cotiz\SeccionMontaje;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\SeccionMontaje>
 */
class SeccionMontajeFactory extends Factory
{
    protected $model = SeccionMontaje::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'nombre' => fake()->words(2, true),
            'area_m2' => fake()->randomFloat(2, 0, 5000),
            'orden' => fake()->numberBetween(0, 99),
        ];
    }
}
