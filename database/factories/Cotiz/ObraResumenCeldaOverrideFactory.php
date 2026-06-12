<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraResumenCeldaOverride;
use App\Models\Cotiz\ResumenColumna;
use App\Models\Cotiz\ResumenFila;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\ObraResumenCeldaOverride>
 */
class ObraResumenCeldaOverrideFactory extends Factory
{
    protected $model = ObraResumenCeldaOverride::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'fila_id' => ResumenFila::factory(),
            'columna_id' => ResumenColumna::factory(),
            'coef' => fake()->randomFloat(4, 0, 2),
        ];
    }
}
