<?php

namespace Database\Factories\Cob;

use App\Models\Cob\Deduccion;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Deduccion> */
class DeduccionFactory extends Factory
{
    protected $model = Deduccion::class;

    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'descripcion' => fake()->sentence(3),
            'monto' => fake()->randomFloat(2, 1000, 100000),
            'moneda' => 'MXN',
            'fecha' => fake()->optional()->date(),
        ];
    }
}
