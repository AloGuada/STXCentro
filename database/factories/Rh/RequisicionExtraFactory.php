<?php

namespace Database\Factories\Rh;

use App\Models\Rh\Requisicion;
use App\Models\Rh\RequisicionExtra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rh\RequisicionExtra>
 */
class RequisicionExtraFactory extends Factory
{
    protected $model = RequisicionExtra::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'requisicion_id' => Requisicion::factory(),
            'salario_mensual' => fake()->optional()->randomFloat(2, 8000, 100000),
            'salario_diario' => fake()->optional()->randomFloat(2, 250, 3500),
            'periodicidad_pago' => fake()->optional()->randomElement(['quincenal', 'mensual', 'semanal']),
            'tipo_jornada' => fake()->optional()->randomElement(['diurna', 'nocturna', 'mixta']),
        ];
    }
}
