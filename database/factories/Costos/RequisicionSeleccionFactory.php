<?php

namespace Database\Factories\Costos;

use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\RequisicionSeleccion>
 */
class RequisicionSeleccionFactory extends Factory
{
    protected $model = RequisicionSeleccion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requisicion_detalle_id' => RequisicionDetalle::factory(),
            'cotizacion_precio_id' => RequisicionCotizacionPrecio::factory(),
            'proveedor_id' => Proveedor::factory(),
            'cantidad' => fake()->randomFloat(2, 1, 50),
        ];
    }
}
