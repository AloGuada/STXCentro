<?php

namespace Database\Factories\Qal;

use App\Models\Qal\Modelo;
use App\Models\Qal\ModeloMarca;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\ModeloMarca>
 */
class ModeloMarcaFactory extends Factory
{
    protected $model = ModeloMarca::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $marca = 'SX-CM'.fake()->unique()->numberBetween(1, 999).'-1';

        return [
            'modelo_id' => Modelo::factory(),
            'marca' => $marca,
            'archivo' => $marca,
            'nombre' => 'COLUMNA',
            'piezas' => 3,
            'peso_kg' => 120.5,
            'ensambles' => 2,
            'soldaduras' => 2,
            'bbox_mm' => [400, 400, 3000],
        ];
    }
}
