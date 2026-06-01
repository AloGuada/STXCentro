<?php

namespace Database\Factories\Costos;

use App\Models\Costos\ObraRubro;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\UsoCfdi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\RequisicionDetalle>
 */
class RequisicionDetalleFactory extends Factory
{
    protected $model = RequisicionDetalle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requisicion_id' => Requisicion::factory(),
            'obra_rubro_id' => ObraRubro::factory(),
            'uso_cfdi_id' => UsoCfdi::factory(),
            'tipo_fiscal' => 'mercancia',
            'descripcion' => fake()->words(3, true),
            'codigo_producto' => fake()->optional()->bothify('PRD-####'),
            'unidad' => 'pza',
            'cantidad' => fake()->randomFloat(2, 1, 100),
            'notas' => fake()->optional()->sentence(),
        ];
    }
}
