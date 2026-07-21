<?php

namespace Database\Factories\Costos;

use App\Models\Costos\Requisicion;
use App\Models\Departamento;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\Requisicion>
 */
class RequisicionFactory extends Factory
{
    protected $model = Requisicion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'solicitante_id' => User::factory(),
            'departamento_id' => Departamento::factory(),
            'justificacion' => fake()->optional()->paragraph(),
            'fecha_requerida' => fake()->optional()->date(),
            'estatus' => 'borrador',
        ];
    }

    public function pendienteAprobacion(): static
    {
        return $this->state(fn () => ['estatus' => 'pendiente_aprobacion_interno']);
    }

    public function aprobada(): static
    {
        return $this->state(fn () => ['estatus' => 'aprobada']);
    }

    public function rechazada(): static
    {
        return $this->state(fn () => ['estatus' => 'rechazada']);
    }
}
