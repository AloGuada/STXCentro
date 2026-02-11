<?php

namespace Database\Factories\Prod;

use App\Models\Prod\Liquidacion;
use App\Models\Prod\LiquidacionEmpleado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\LiquidacionEmpleado>
 */
class LiquidacionEmpleadoFactory extends Factory
{
    protected $model = LiquidacionEmpleado::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'liquidacion_id' => Liquidacion::factory(),
            'nombre' => fake()->name(),
            'no_empleado' => fake()->unique()->regexify('[0-9]{4}'),
            'porcentaje' => 100.00,
            'monto_asignado' => fake()->randomFloat(2, 500, 10000),
        ];
    }
}
