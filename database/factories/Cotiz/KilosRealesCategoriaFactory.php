<?php

namespace Database\Factories\Cotiz;

use App\Enums\Cotiz\TipoCorte;
use App\Models\Cotiz\KilosRealesCategoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\KilosRealesCategoria>
 */
class KilosRealesCategoriaFactory extends Factory
{
    protected $model = KilosRealesCategoria::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descripcion' => fake()->unique()->words(2, true),
            'tipo_corte' => fake()->randomElement(TipoCorte::cases()),
            'orden' => fake()->numberBetween(0, 99),
        ];
    }
}
