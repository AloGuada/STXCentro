<?php

namespace Database\Factories\Qal;

use App\Models\Qal\Flecha;
use App\Models\Qal\Reporte;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\Flecha>
 */
class FlechaFactory extends Factory
{
    protected $model = Flecha::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporte_id' => Reporte::factory(),
            'inicio_x' => fake()->randomFloat(4, 0, 1),
            'inicio_y' => fake()->randomFloat(4, 0, 1),
            'fin_x' => fake()->randomFloat(4, 0, 1),
            'fin_y' => fake()->randomFloat(4, 0, 1),
            'esdoble' => false,
            'tipo' => fake()->optional()->randomElement(['filete', 'penetracion', 'ranura']),
            'show_number' => true,
            'pagina' => 1,
        ];
    }
}
