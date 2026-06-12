<?php

namespace Database\Factories\Cotiz;

use App\Enums\Cotiz\GrupoFlete;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraFleteViatico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\ObraFleteViatico>
 */
class ObraFleteViaticoFactory extends Factory
{
    protected $model = ObraFleteViatico::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'grupo' => fake()->randomElement(GrupoFlete::cases()),
            'orden' => fake()->numberBetween(0, 99),
            'concepto' => fake()->words(2, true),
            'unidad' => fake()->randomElement(['lote', 'Semana', 'Mes', 'día', 'Pers']),
            'cantidad' => fake()->randomFloat(2, 0, 50),
            'p_unit' => fake()->randomFloat(2, 0, 20000),
            'notas' => null,
            'clave' => null,
            'formula_cantidad' => null,
            'formula_p_unit' => null,
        ];
    }
}
