<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\PersonalCategoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\PersonalCategoria>
 */
class PersonalCategoriaFactory extends Factory
{
    protected $model = PersonalCategoria::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->regexify('[A-Z]{3}'),
            'nombre' => fake()->randomElement(['Oficial', 'Soldador', 'Ayudante', 'Cabo']),
            'sueldo_semanal' => fake()->randomFloat(2, 1500, 5000),
            'orden' => fake()->numberBetween(0, 99),
        ];
    }
}
