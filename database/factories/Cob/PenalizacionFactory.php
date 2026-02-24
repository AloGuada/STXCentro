<?php

namespace Database\Factories\Cob;

use App\Models\Cob\Penalizacion;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Penalizacion> */
class PenalizacionFactory extends Factory
{
    protected $model = Penalizacion::class;

    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'descripcion' => fake()->sentence(3),
            'monto' => fake()->randomFloat(2, 1000, 100000),
            'moneda' => 'MXN',
            'tipo' => fake()->optional()->randomElement(['retraso', 'calidad', 'incumplimiento']),
            'fecha' => fake()->optional()->date(),
        ];
    }
}
