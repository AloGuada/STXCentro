<?php

namespace Database\Factories\Costos;

use App\Models\Costos\Producto;
use App\Models\Costos\ProductoPrecio;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductoPrecio>
 */
class ProductoPrecioFactory extends Factory
{
    protected $model = ProductoPrecio::class;

    public function definition(): array
    {
        return [
            'producto_id' => Producto::factory(),
            'proveedor_id' => Proveedor::factory(),
            'precio' => $this->faker->randomFloat(2, 10, 5000),
            'moneda' => 'mxn',
            'fecha' => now()->toDateString(),
            'requisicion_id' => null,
        ];
    }
}
