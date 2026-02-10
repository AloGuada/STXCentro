<?php

namespace Database\Factories;

use App\Models\Obra;
use App\Models\Pieza;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pieza>
 */
class PiezaFactory extends Factory
{
    protected $model = Pieza::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'marca' => fake()->unique()->regexify('[A-Z]{2}-[0-9]{3}'),
            'descripcion' => fake()->words(3, true),
            'longitud' => fake()->optional()->randomFloat(2, 0.5, 20),
            'peso' => fake()->randomFloat(2, 5, 500),
            'cantidad' => fake()->numberBetween(1, 50),
            'version' => 1,
        ];
    }
}
