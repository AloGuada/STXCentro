<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\CategoriaTarjeta;
use App\Models\Cotiz\Factor;
use App\Models\Cotiz\Insumo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\Factor>
 */
class FactorFactory extends Factory
{
    protected $model = Factor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->regexify('[A-Z_]{6,12}'),
            'nombre' => fake()->words(2, true),
            'insumo_id' => Insumo::factory(),
            'formula' => 'kg_fab * 0.001',
            'descripcion' => fake()->optional()->sentence(),
            'categoria_tarjeta_id' => CategoriaTarjeta::factory(),
        ];
    }

    public function manual(): static
    {
        return $this->state(fn (array $attributes) => ['formula' => null]);
    }
}
