<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\CentroCosto;
use App\Models\Cotiz\FaseMontaje;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\FaseMontaje>
 */
class FaseMontajeFactory extends Factory
{
    protected $model = FaseMontaje::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->regexify('[A-Z]{3}[0-9]{2}'),
            'nombre' => fake()->words(2, true),
            'unidad' => fake()->randomElement(['pza', 'm2', 'ml']),
            'centro_costo_id' => CentroCosto::factory(),
            'orden' => fake()->numberBetween(0, 99),
        ];
    }
}
