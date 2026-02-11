<?php

namespace Database\Factories\Prod;

use App\Models\Concepto;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Registro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\Registro>
 */
class RegistroFactory extends Factory
{
    protected $model = Registro::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fecha' => fake()->date(),
            'concepto_id' => Concepto::factory(),
            'grupo_trabajo_id' => GrupoTrabajo::factory(),
            'cantidad' => fake()->numberBetween(1, 20),
        ];
    }
}
