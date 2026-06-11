<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\Insumo;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraInsumoOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\ObraInsumoOverride>
 */
class ObraInsumoOverrideFactory extends Factory
{
    protected $model = ObraInsumoOverride::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'insumo_id' => Insumo::factory(),
            'precio_unitario' => fake()->randomFloat(4, 1, 1000),
        ];
    }
}
