<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\CategoriaTarjeta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\CategoriaTarjeta>
 */
class CategoriaTarjetaFactory extends Factory
{
    protected $model = CategoriaTarjeta::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descripcion' => fake()->unique()->words(2, true),
            'orden' => fake()->numberBetween(0, 99),
        ];
    }
}
