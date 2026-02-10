<?php

namespace Database\Factories\Prod;

use App\Models\Prod\EmpleadoGrupo;
use App\Models\Prod\Grupo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\EmpleadoGrupo>
 */
class EmpleadoGrupoFactory extends Factory
{
    protected $model = EmpleadoGrupo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grupo_id' => Grupo::factory(),
            'nombre' => fake()->name(),
            'no_empleado' => fake()->unique()->regexify('[0-9]{4}'),
        ];
    }
}
