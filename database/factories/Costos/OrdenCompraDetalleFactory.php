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
        return [
            'orden_compra_id' => OrdenCompra::factory(),
            'obra_rubro_id' => ObraRubro::factory(),
            'monto' => fake()->randomFloat(2, 500, 50000),
        ];
    }
}
