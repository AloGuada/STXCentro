<?php

namespace Database\Factories\Costos;

use App\Models\Costos\AfectacionDetalle;
use App\Models\Costos\AfectacionPresupuestal;
use App\Models\Costos\ObraRubro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\AfectacionDetalle>
 */
class AfectacionDetalleFactory extends Factory
{
    protected $model = AfectacionDetalle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cantidad = fake()->randomFloat(2, 1, 100);
        $precioUnitario = fake()->randomFloat(2, 10, 5000);

        return [
            'afectacion_id' => AfectacionPresupuestal::factory(),
            'obra_rubro_id' => ObraRubro::factory(),
            'concepto' => fake()->words(3, true),
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'monto' => round($cantidad * $precioUnitario, 2),
        ];
    }
}
