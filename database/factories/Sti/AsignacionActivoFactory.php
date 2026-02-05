<?php

namespace Database\Factories\Sti;

use App\Models\Departamento;
use App\Models\Sti\AsignacionActivo;
use App\Models\Sti\Equipo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sti\AsignacionActivo>
 */
class AsignacionActivoFactory extends Factory
{
    protected $model = AsignacionActivo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'departamento_id' => Departamento::factory(),
            'equipo_id' => Equipo::factory(),
            'no_empleado' => fake()->numerify('EMP###'),
            'empleado' => fake()->name(),
            'firma_empleado' => null,
            'no_ti' => fake()->numerify('TI###'),
            'nombre_ti' => fake()->name(),
            'firma_ti' => null,
            'fecha_inicial' => fake()->date(),
            'fecha_termino' => null,
            'estado' => 'activo',
        ];
    }

    public function devuelto(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'devuelto',
            'fecha_termino' => fake()->date(),
        ]);
    }
}
