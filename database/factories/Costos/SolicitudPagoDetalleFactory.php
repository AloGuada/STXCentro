<?php

namespace Database\Factories\Costos;

use App\Models\Costos\ObraRubro;
use App\Models\Costos\SolicitudPago;
use App\Models\Costos\SolicitudPagoDetalle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\SolicitudPagoDetalle>
 */
class SolicitudPagoDetalleFactory extends Factory
{
    protected $model = SolicitudPagoDetalle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cantidad = fake()->randomFloat(2, 1, 100);
        $precioUnitario = fake()->randomFloat(2, 10, 5000);

        return [
            'solicitud_id' => SolicitudPago::factory(),
            'obra_rubro_id' => ObraRubro::factory(),
            'concepto' => fake()->words(3, true),
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'subtotal' => round($cantidad * $precioUnitario, 2),
        ];
    }
}
