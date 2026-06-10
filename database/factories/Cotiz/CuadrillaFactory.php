<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\CentroCosto;
use App\Models\Cotiz\Cuadrilla;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\Cuadrilla>
 */
class CuadrillaFactory extends Factory
{
    protected $model = Cuadrilla::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->regexify('[A-Z]{3}[0-9]{2}'),
            'nombre' => fake()->words(2, true),
            'centro_costo_id' => CentroCosto::factory(),
            'insumo_id' => null,
            'rendimiento' => fake()->optional()->randomFloat(6, 0.001, 1),
            'formula_costo' => fake()->optional()->randomElement(['sueldo_semanal / 48', null]),
            'descripcion' => fake()->optional()->sentence(),
        ];
    }
}
