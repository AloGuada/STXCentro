<?php

namespace Database\Factories\Cob;

use App\Models\Cob\Estimacion;
use App\Models\Obra;
use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Estimacion> */
class EstimacionFactory extends Factory
{
    protected $model = Estimacion::class;

    public function definition(): array
    {
        return [
            'proyecto_id' => null,
            'obra_id' => Obra::factory(),
            'nivel' => 'obra',
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

    public function configure(): static
    {
        // Si se creó con obra y sin proyecto, hereda el proyecto de la obra.
        return $this->afterCreating(function (Estimacion $estimacion): void {
            if ($estimacion->proyecto_id === null && $estimacion->obra_id !== null) {
                $proyectoId = Obra::whereKey($estimacion->obra_id)->value('proyecto_id');
                if ($proyectoId !== null) {
                    $estimacion->update(['proyecto_id' => $proyectoId]);
                }
            }
        });
    }

    /** Estimación global (a nivel proyecto, sin obra). */
    public function global(): static
    {
        return $this->state(fn () => [
            'nivel' => 'proyecto',
            'obra_id' => null,
            'proyecto_id' => Proyecto::factory(),
        ]);
    }
}
