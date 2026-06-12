<?php

namespace Database\Factories\Cotiz;

use App\Enums\Cotiz\MetodoFleteEstandar;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraFleteEstandar;
use App\Models\Cotiz\Tarjeta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\ObraFleteEstandar>
 */
class ObraFleteEstandarFactory extends Factory
{
    protected $model = ObraFleteEstandar::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'tarjeta_id' => Tarjeta::factory(),
            'metodo' => MetodoFleteEstandar::PorKg,
            'grupo' => fake()->optional()->word(),
            'volumen_override' => null,
            'kg_por_camion' => 15000,
            'pzas_por_camion' => 400,
            'ml_por_pza' => 3.05,
            'orden' => fake()->numberBetween(0, 99),
        ];
    }
}
