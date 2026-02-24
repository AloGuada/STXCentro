<?php

namespace Database\Factories\Cob;

use App\Models\Cob\Anticipo;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Anticipo> */
class AnticipoFactory extends Factory
{
    protected $model = Anticipo::class;

    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'folio' => fake()->optional()->regexify('ANT-[0-9]{4}'),
            'fecha_emision' => fake()->optional()->date(),
            'monto' => fake()->randomFloat(2, 10000, 500000),
            'moneda' => 'MXN',
            'estado' => 'pendiente',
            'comentarios' => fake()->optional()->sentence(),
            'fecha_pagado' => null,
            'comprobante' => null,
        ];
    }
}
