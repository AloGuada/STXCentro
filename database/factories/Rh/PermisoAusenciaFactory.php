<?php

namespace Database\Factories\Rh;

use App\Models\Rh\PermisoAusencia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rh\PermisoAusencia>
 */
class PermisoAusenciaFactory extends Factory
{
    protected $model = PermisoAusencia::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'folio' => 'PA-'.fake()->unique()->numberBetween(1000, 9999),
            'numero_empleado' => fake()->optional()->numerify('EMP-####'),
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName(),
            'departamento' => fake()->optional()->word(),
            'gerente' => fake()->optional()->name(),
            'tipo' => fake()->optional()->randomElement(['vacaciones', 'incapacidad', 'personal', 'maternidad']),
            'modalidad' => fake()->optional()->randomElement(['con_goce', 'sin_goce']),
            'razon' => fake()->optional()->sentence(),
            'fecha_permiso' => fake()->date(),
        ];
    }
}
