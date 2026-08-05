<?php

namespace Database\Factories\Prod;

use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\Liquidacion;
use App\Models\Prod\LiquidacionDetalle;
use App\Models\Prod\Pieza;
use App\Models\Prod\Proceso;
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
        $precioKilo = fake()->randomFloat(4, 1, 50);
        $kilos = fake()->randomFloat(3, 5, 100);

        return [
            'liquidacion_id' => Liquidacion::factory(),
            'pieza_id' => Pieza::factory(),
            'grupo_precio_id' => GrupoPrecio::factory(),
            'proceso_id' => Proceso::factory(),
            'porcentaje' => 100,
            'kilos' => $kilos,
            'precio_kilo_aplicado' => $precioKilo,
            'total' => round($kilos * $precioKilo, 2),
        ];
    }
}
