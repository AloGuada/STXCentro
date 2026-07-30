<?php

namespace Database\Factories\Prod;

use App\Enums\Prod\EstadoAsistencia;
use App\Models\Prod\Asistencia;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoEmpleado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\Asistencia>
 */
class AsistenciaFactory extends Factory
{
    protected $model = Asistencia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'destajo_id' => Destajo::factory(),
            'grupo_empleado_id' => GrupoEmpleado::factory(),
            'fecha' => fake()->date(),
            'estado' => EstadoAsistencia::Asistencia,
        ];
    }
}
