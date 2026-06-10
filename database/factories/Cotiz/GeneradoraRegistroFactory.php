<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\Generadora;
use App\Models\Cotiz\GeneradoraRegistro;
use App\Models\Cotiz\Insumo;
use App\Models\Cotiz\Merma;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\GeneradoraRegistro>
 */
class GeneradoraRegistroFactory extends Factory
{
    protected $model = GeneradoraRegistro::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'generadora_id' => Generadora::factory(),
            'material_origen_id' => Insumo::factory(),
            'material' => fake()->optional()->words(2, true),
            'marca' => fake()->optional()->word(),
            'ancho' => fake()->randomFloat(4, 0.1, 5),
            'largo' => fake()->randomFloat(4, 0.1, 12),
            'cantidad' => fake()->numberBetween(1, 50),
            'cant_pzas' => fake()->numberBetween(1, 10),
            'peso_porcentual' => null,
            'kilos_totales' => null,
            'merma_id' => Merma::factory(),
            'validado' => false,
        ];
    }

    public function validado(): static
    {
        return $this->state(fn (array $attributes) => ['validado' => true]);
    }
}
