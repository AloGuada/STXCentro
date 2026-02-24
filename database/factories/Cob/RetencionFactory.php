<?php

namespace Database\Factories\Cob;

use App\Models\Cob\Estimacion;
use App\Models\Cob\Retencion;
use App\Models\Cob\TipoRetencion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Retencion> */
class RetencionFactory extends Factory
{
    protected $model = Retencion::class;

    public function definition(): array
    {
        return [
            'estimacion_id' => Estimacion::factory(),
            'tipo_retencion_id' => TipoRetencion::factory(),
            'monto' => fake()->randomFloat(2, 500, 50000),
            'moneda' => 'MXN',
        ];
    }
}
