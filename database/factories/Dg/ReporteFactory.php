<?php

namespace Database\Factories\Dg;

use App\Models\Dg\Carpeta;
use App\Models\Dg\Reporte;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Dg\Reporte>
 */
class ReporteFactory extends Factory
{
    protected $model = Reporte::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'carpeta_id' => Carpeta::factory(),
            'anio' => 2026,
            'semana' => fake()->numberBetween(1, 52),
            'creado_por_id' => null,
            'observaciones' => null,
        ];
    }
}
