<?php

namespace Database\Factories\Prod;

use App\Models\Prod\Liquidacion;
use App\Models\Prod\LiquidacionDetalle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\LiquidacionDetalle>
 */
class LiquidacionDetalleFactory extends Factory
{
    protected $model = LiquidacionDetalle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cantidad = fake()->numberBetween(1, 20);
        $precioKilo = fake()->randomFloat(4, 1, 50);
        $kilos = $cantidad * fake()->randomFloat(3, 5, 100);
        $total = round($kilos * $precioKilo, 2);

        return [
            'liquidacion_id' => Liquidacion::factory(),
            'concepto_id' => 1,
            'grupo_precio_id' => 1,
            'cantidad' => $cantidad,
            'kilos' => $kilos,
            'precio_kilo_aplicado' => $precioKilo,
            'total' => $total,
        ];
    }
}
