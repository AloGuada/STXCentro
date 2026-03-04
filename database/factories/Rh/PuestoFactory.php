<?php

namespace Database\Factories\Rh;

use App\Models\Departamento;
use App\Models\Rh\Puesto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rh\Puesto>
 */
class PuestoFactory extends Factory
{
    protected $model = Puesto::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'departamento_id' => Departamento::factory(),
            'nombre' => fake()->jobTitle(),
            'descripcion' => fake()->optional()->paragraph(),
            'codigo' => fake()->optional()->passthrough(fake()->unique()->regexify('[A-Z]{2}[0-9]{3}')),
            'ubicacion' => fake()->optional()->city(),
        ];
    }
}
