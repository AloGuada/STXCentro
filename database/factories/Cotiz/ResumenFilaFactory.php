<?php

namespace Database\Factories\Cotiz;

use App\Enums\Cotiz\ResumenBloque;
use App\Enums\Cotiz\ResumenTipoFormula;
use App\Models\Cotiz\ResumenFila;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\ResumenFila>
 */
class ResumenFilaFactory extends Factory
{
    protected $model = ResumenFila::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descripcion' => fake()->words(3, true),
            'bloque' => fake()->randomElement(ResumenBloque::cases()),
            'tipo_formula' => fake()->randomElement(ResumenTipoFormula::cases()),
            'coef_default' => fake()->optional()->randomFloat(6, 0, 2),
            'referencia_extra' => fake()->optional()->word(),
            'orden' => fake()->numberBetween(0, 99),
            'bloqueada' => false,
        ];
    }
}
