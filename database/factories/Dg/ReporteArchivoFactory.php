<?php

namespace Database\Factories\Dg;

use App\Models\Dg\Reporte;
use App\Models\Dg\ReporteArchivo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Dg\ReporteArchivo>
 */
class ReporteArchivoFactory extends Factory
{
    protected $model = ReporteArchivo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporte_id' => Reporte::factory(),
            'nombre_original' => fake()->word().'.pdf',
            'path' => 'dg-reportes/test/'.fake()->uuid().'.pdf',
            'mime' => 'application/pdf',
            'size' => fake()->numberBetween(1024, 1048576),
            'subido_por_id' => null,
        ];
    }
}
