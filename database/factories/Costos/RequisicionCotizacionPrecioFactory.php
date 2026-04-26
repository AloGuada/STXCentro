<?php

namespace Database\Factories\Costos;

use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\RequisicionCotizacionPrecio>
 */
class RequisicionCotizacionPrecioFactory extends Factory
{
    protected $model = RequisicionCotizacionPrecio::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requisicion_detalle_id' => RequisicionDetalle::factory(),
            'proveedor_id' => Proveedor::factory(),
            'precio_unitario' => fake()->randomFloat(2, 10, 5000),
            'tiempo_entrega_dias' => fake()->optional()->numberBetween(1, 30),
            'observaciones' => fake()->optional()->sentence(),
        ];
    }
}
