<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\CategoriaTarjeta;
use App\Models\Cotiz\CentroCosto;
use App\Models\Cotiz\Insumo;
use App\Models\Cotiz\Unidad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\Insumo>
 */
class InsumoFactory extends Factory
{
    protected $model = Insumo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descripcion' => fake()->unique()->words(3, true),
            'codigo_stumis' => fake()->optional()->bothify('STU-####'),
            'unidad_id' => Unidad::factory(),
            'precio_unitario' => fake()->randomFloat(4, 1, 1000),
            'peso_lineal' => fake()->optional()->randomFloat(6, 0.1, 100),
            'peso_default' => fake()->optional()->randomFloat(6, 0.1, 500),
            'centro_costo_id' => CentroCosto::factory(),
            'categoria_tarjeta_id' => CategoriaTarjeta::factory(),
        ];
    }
}
