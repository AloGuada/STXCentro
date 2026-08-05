<?php

namespace Database\Factories\Prod;

use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Pieza;
use App\Models\Prod\Proceso;
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
            'pieza_id' => Pieza::factory(),
            'proceso_id' => Proceso::factory(),
            'grupo_trabajo_id' => GrupoTrabajo::factory(),
            'porcentaje' => 100,
        ];
    }
}
