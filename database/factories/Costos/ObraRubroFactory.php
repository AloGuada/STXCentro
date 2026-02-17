<?php

namespace Database\Factories\Costos;

use App\Models\Costos\ObraRubro;
use App\Models\Costos\Rubro;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\ObraRubro>
 */
class ObraRubroFactory extends Factory
{
    protected $model = ObraRubro::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'rubro_id' => Rubro::factory(),
            'presupuestado' => fake()->randomFloat(2, 1000, 500000),
            'acumulado' => 0,
        ];
    }
}
