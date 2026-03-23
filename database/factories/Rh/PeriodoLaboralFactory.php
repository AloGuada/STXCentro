<?php

namespace Database\Factories\Rh;

use App\Models\Rh\PeriodoLaboral;
use App\Models\Rh\Persona;
use App\Models\Rh\Puesto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rh\PeriodoLaboral>
 */
class PeriodoLaboralFactory extends Factory
{
    protected $model = PeriodoLaboral::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'persona_id' => Persona::factory(),
            'puesto_id' => Puesto::factory(),
            'fecha_inicio' => fake()->dateTimeBetween('-2 years', 'now'),
            'estado' => 'activo',
            'salario_diario' => fake()->optional()->randomFloat(2, 200, 3000),
            'sueldo_mensual' => fake()->optional()->randomFloat(2, 5000, 80000),
            'tipo_contrato' => fake()->optional()->randomElement(['indefinido', 'temporal', 'prueba']),
        ];
    }
}
