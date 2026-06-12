<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraCuadrillaGlobal;
use App\Models\Cotiz\PersonalCategoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\ObraCuadrillaGlobal>
 */
class ObraCuadrillaGlobalFactory extends Factory
{
    protected $model = ObraCuadrillaGlobal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'categoria_id' => PersonalCategoria::factory(),
            'cantidad_por_grupo' => fake()->numberBetween(0, 8),
        ];
    }
}
