<?php

namespace Database\Factories\Costos;

use App\Models\Costos\Entrega;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\OrdenCompraDetalle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\EntregaDetalle>
 */
class EntregaDetalleFactory extends Factory
{
    protected $model = EntregaDetalle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entrega_id' => Entrega::factory(),
            'orden_compra_detalle_id' => OrdenCompraDetalle::factory(),
            'cantidad_recibida' => fake()->randomFloat(2, 1, 50),
            'observaciones' => fake()->optional()->sentence(),
        ];
    }
}
