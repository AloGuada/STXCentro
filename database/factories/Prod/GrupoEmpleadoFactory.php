<?php

namespace Database\Factories\Prod;

use App\Models\Prod\CategoriaEmpleado;
use App\Models\Prod\GrupoEmpleado;
use App\Models\Prod\GrupoTrabajo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\GrupoEmpleado>
 */
class GrupoEmpleadoFactory extends Factory
{
    protected $model = GrupoEmpleado::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grupo_trabajo_id' => GrupoTrabajo::factory(),
            'nombre' => fake()->name(),
            'no_empleado' => fake()->unique()->regexify('[0-9]{4}'),
            // Reutiliza una sola categoria para que los grupos de prueba
            // reparten el excedente en partes iguales por defecto.
            'categoria_empleado_id' => fn () => CategoriaEmpleado::query()->firstOrCreate(
                ['nombre' => 'Categoria de prueba'],
                ['valor' => 1500, 'orden' => 0, 'activo' => true],
            )->id,
        ];
    }
}
