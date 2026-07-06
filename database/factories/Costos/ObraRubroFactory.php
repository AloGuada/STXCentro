<?php

namespace Database\Factories\Costos;

use App\Models\Costos\ObraRubro;
use App\Models\Costos\Presupuesto;
use App\Models\Costos\Rubro;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\ObraRubro>
 */
class ObraRubroFactory extends Factory
{
    protected $model = ObraRubro::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'presupuesto_id' => Presupuesto::factory(),
            'rubro_id' => Rubro::factory(),
            'presupuestado' => fake()->randomFloat(2, 1000, 500000),
            'acumulado' => 0,
        ];
    }

    public function configure(): static
    {
        // Sincroniza obra_id con el presupuestable mientras la columna exista
        // (compatibilidad reporte PDF). Solo aplica a presupuestos de Obra.
        return $this->afterMaking(function (ObraRubro $obraRubro): void {
            if ($obraRubro->obra_id !== null || $obraRubro->presupuesto_id === null) {
                return;
            }

            $presupuesto = Presupuesto::find($obraRubro->presupuesto_id);

            if ($presupuesto?->presupuestable_type === Obra::class) {
                $obraRubro->obra_id = $presupuesto->presupuestable_id;
            }
        });
    }
}
