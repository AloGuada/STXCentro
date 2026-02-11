<?php

namespace Database\Factories\Prod;

use App\Models\Prod\Corte;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Liquidacion;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\Liquidacion>
 */
class LiquidacionFactory extends Factory
{
    protected $model = Liquidacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'corte_id' => Corte::factory(),
            'grupo_trabajo_id' => GrupoTrabajo::factory(),
            'total_kilos' => 0,
            'total_produccion' => 0,
            'total_extras' => 0,
            'total_final' => 0,
            'generado_en' => now(),
            'generado_por' => Usuario::factory(),
        ];
    }
}
