<?php

namespace Database\Factories\Cob;

use App\Models\Cob\Evento;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Evento> */
class EventoFactory extends Factory
{
    protected $model = Evento::class;

    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'parent_id' => null,
            'nombre' => fake()->sentence(3),
            'monto' => fake()->optional()->randomFloat(2, 5000, 200000),
            'inicio' => fake()->optional()->date(),
            'fin' => fake()->optional()->date(),
            'marcado' => false,
        ];
    }
}
