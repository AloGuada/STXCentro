<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\Obra>
 */
class ObraFactory extends Factory
{
    protected $model = Obra::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->company(),
            'op' => fake()->optional()->bothify('OP-####'),
            'factor_contratista' => 1.15,
            'num_grupos' => fake()->numberBetween(1, 4),
        ];
    }
}
