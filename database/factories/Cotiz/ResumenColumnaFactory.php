<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ResumenColumna;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\ResumenColumna>
 */
class ResumenColumnaFactory extends Factory
{
    protected $model = ResumenColumna::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'nombre' => fake()->words(2, true),
            'orden' => fake()->numberBetween(0, 99),
            'sueldo_mo_pza' => null,
        ];
    }
}
