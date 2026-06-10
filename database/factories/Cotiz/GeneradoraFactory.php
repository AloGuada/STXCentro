<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\Generadora;
use App\Models\Cotiz\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\Generadora>
 */
class GeneradoraFactory extends Factory
{
    protected $model = Generadora::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'titulo' => fake()->words(2, true),
            'orden' => fake()->numberBetween(0, 20),
        ];
    }
}
