<?php

namespace Database\Factories\Cob;

use App\Models\Cob\IcsoeSbcAnio;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IcsoeSbcAnio> */
class IcsoeSbcAnioFactory extends Factory
{
    protected $model = IcsoeSbcAnio::class;

    public function definition(): array
    {
        return [
            'anio' => fake()->unique()->numberBetween(2000, 2100),
            'sbc' => fake()->randomFloat(2, 200, 400),
            'costo_m2' => 1154,
            'prima_riesgo' => 7.58875,
            'notas' => null,
        ];
    }
}
