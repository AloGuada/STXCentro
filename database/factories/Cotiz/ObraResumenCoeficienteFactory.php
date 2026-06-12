<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraResumenCoeficiente;
use App\Models\Cotiz\ResumenFila;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\ObraResumenCoeficiente>
 */
class ObraResumenCoeficienteFactory extends Factory
{
    protected $model = ObraResumenCoeficiente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'fila_id' => ResumenFila::factory(),
            'coef' => fake()->randomFloat(4, 0, 2),
        ];
    }
}
