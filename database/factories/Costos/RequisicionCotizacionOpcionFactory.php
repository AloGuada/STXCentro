<?php

namespace Database\Factories\Costos;

use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionOpcion;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\RequisicionCotizacionOpcion>
 */
class RequisicionCotizacionOpcionFactory extends Factory
{
    protected $model = RequisicionCotizacionOpcion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requisicion_id' => Requisicion::factory(),
            'proveedor_id' => Proveedor::factory(),
            'etiqueta' => null,
            'orden' => 1,
        ];
    }
}
