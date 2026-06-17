<?php

namespace Database\Factories\Cob;

use App\Models\Cob\Partida;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Partida> */
class PartidaFactory extends Factory
{
    protected $model = Partida::class;

    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'tipo' => fake()->randomElement(['suministro', 'montaje']),
            'descripcion' => fake()->sentence(3),
            'monto' => fake()->randomFloat(2, 1000, 500000),
            'moneda' => 'MXN',
        ];
    }
}
