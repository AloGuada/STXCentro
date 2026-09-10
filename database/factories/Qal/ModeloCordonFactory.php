<?php

namespace Database\Factories\Qal;

use App\Enums\Qal\TipoCordon;
use App\Models\Qal\ModeloCordon;
use App\Models\Qal\ModeloMarca;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\ModeloCordon>
 */
class ModeloCordonFactory extends Factory
{
    protected $model = ModeloCordon::class;

    /**
     * Un filete de 300 mm en rincón de 90 entre un alma de 8 y una base de 12.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'modelo_marca_id' => ModeloMarca::factory(),
            'numero' => fake()->unique()->numberBetween(1, 99999),
            'tipo' => TipoCordon::Filete,
            'junta' => 'T',
            'piezas' => ['P001', 'P000'],
            'largo_mm' => 300,
            'ancho_mm' => 8,
            'angulo' => 90,
            't1_mm' => 8,
            't2_mm' => 12,
            'cateto_min_mm' => 4.76,
            'cateto_max_mm' => 6.41,
            'garganta_min_mm' => 3.37,
            'preparacion' => ['bisel' => 'ninguno', 'angulo_bisel' => null, 'lados' => 1, 'penetracion' => 'parcial', 'nota' => 'corte recto, filete directo'],
            'avisos' => [],
            'centro' => [0.0, 0.0, 0.0],
            'puntos' => [[-0.15, 0.004, 0.0], [0.15, 0.004, 0.0]],
        ];
    }

    public function costura(): static
    {
        return $this->state(fn (): array => ['tipo' => TipoCordon::Costura, 'junta' => 'tope', 'angulo' => 180]);
    }
}
