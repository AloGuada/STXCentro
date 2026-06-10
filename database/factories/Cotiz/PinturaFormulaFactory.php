<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\PinturaFormula;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\PinturaFormula>
 */
class PinturaFormulaFactory extends Factory
{
    protected $model = PinturaFormula::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clave' => fake()->unique()->randomElement(['no_pinta', 'placa', 'tira', 'hss', 'ipr']),
            'nombre' => fake()->words(2, true),
            'formula' => 'kg / peso_lineal',
            'orden' => fake()->numberBetween(0, 99),
        ];
    }
}
