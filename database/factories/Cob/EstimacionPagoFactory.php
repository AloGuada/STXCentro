<?php

namespace Database\Factories\Cob;

use App\Models\Cob\Estimacion;
use App\Models\Cob\EstimacionPago;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EstimacionPago> */
class EstimacionPagoFactory extends Factory
{
    protected $model = EstimacionPago::class;

    public function definition(): array
    {
        return [
            'estimacion_id' => Estimacion::factory(),
            'monto_pagado' => fake()->randomFloat(2, 1000, 500000),
            'fecha_pago' => fake()->date(),
            'folio' => fake()->optional()->regexify('PAG-[0-9]{4}'),
            'comprobante' => null,
        ];
    }
}
