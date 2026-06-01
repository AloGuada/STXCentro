<?php

namespace Database\Factories\Costos;

use App\Models\Costos\Factura;
use App\Models\Costos\FacturaDetalle;
use App\Models\Costos\OrdenCompraDetalle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\FacturaDetalle>
 */
class FacturaDetalleFactory extends Factory
{
    protected $model = FacturaDetalle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cantidad = fake()->randomFloat(2, 1, 20);
        $precioUnitario = fake()->randomFloat(2, 50, 1000);

        return [
            'factura_id' => Factura::factory(),
            'orden_compra_detalle_id' => OrdenCompraDetalle::factory(),
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'subtotal' => round($cantidad * $precioUnitario, 2),
        ];
    }
}
