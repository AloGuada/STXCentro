<?php

namespace Database\Factories;

use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Obra>
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
            'no' => fake()->unique()->regexify('[0-9]{4}'),
            'descripcion' => fake()->words(4, true),
            'fecha_inicio' => fake()->optional()->date(),
            'fecha_fin' => fake()->optional()->date(),
            'presupuesto_total' => fake()->randomFloat(2, 0, 10000000),
            'ingreso_real' => fake()->optional()->randomFloat(4, 0, 10000000),
            'estatus' => fake()->randomElement(['abierta', 'cerrada']),
        ];
    }
}
