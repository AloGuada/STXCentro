<?php

namespace Database\Factories\Costos;

use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\OrdenCompraDetalle>
 */
class OrdenCompraDetalleFactory extends Factory
{
    protected $model = OrdenCompraDetalle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cantidad = fake()->randomFloat(2, 1, 100);
        $precioUnitario = fake()->randomFloat(2, 50, 1000);

        return [
            'orden_compra_id' => OrdenCompra::factory(),
            'obra_rubro_id' => ObraRubro::factory(),
            'descripcion' => fake()->words(3, true),
            'unidad' => fake()->randomElement(['pza', 'kg', 'm3', 'hr', 'lt']),
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'subtotal' => round($cantidad * $precioUnitario, 2),
        ];
    }
}
