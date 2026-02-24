<?php

namespace Database\Factories\Cob;

use App\Models\Cob\Estimacion;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Estimacion> */
class EstimacionFactory extends Factory
{
    protected $model = Estimacion::class;

    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'numero_estimacion' => fake()->numberBetween(1, 50),
            'folio' => fake()->optional()->regexify('EST-[0-9]{4}'),
            'tipo' => fake()->optional()->randomElement(['normal', 'extraordinaria']),
            'fecha_emision' => fake()->optional()->date(),
            'inicio' => fake()->optional()->date(),
            'fin' => fake()->optional()->date(),
            'monto_estimado' => fake()->randomFloat(2, 10000, 1000000),
            'monto_total' => fake()->randomFloat(2, 10000, 1000000),
            'monto_pagado' => 0,
            'moneda' => 'MXN',
            'estado' => 'pendiente',
            'comentarios' => fake()->optional()->sentence(),
        ];
    }
}
