<?php

namespace Database\Factories\Cob;

use App\Models\Cob\Disputa;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Disputa> */
class DisputaFactory extends Factory
{
    protected $model = Disputa::class;

    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'descripcion' => fake()->paragraph(),
            'fecha_inicio' => fake()->optional()->date(),
            'fecha_resolucion' => null,
            'estado' => 'en_proceso',
            'resultado' => null,
        ];
    }
}
