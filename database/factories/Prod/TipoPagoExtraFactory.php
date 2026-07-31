<?php

namespace Database\Factories\Prod;

use App\Models\Prod\TipoPagoExtra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\TipoPagoExtra>
 */
class TipoPagoExtraFactory extends Factory
{
    protected $model = TipoPagoExtra::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descripcion' => fake()->unique()->word(),
            'orden' => fake()->numberBetween(1, 20),
            'desgloce' => fake()->boolean(),
        ];
    }
}
