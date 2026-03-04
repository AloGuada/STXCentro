<?php

namespace Database\Factories\Rh;

use App\Models\Rh\Puesto;
use App\Models\Rh\Requisicion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rh\Requisicion>
 */
class RequisicionFactory extends Factory
{
    protected $model = Requisicion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'folio' => 'REQ-'.date('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'puesto_id' => Puesto::factory(),
            'cantidad' => fake()->numberBetween(1, 5),
            'estado' => 'borrador',
            'tipo_requisicion' => fake()->randomElement(['nueva', 'reemplazo', 'temporal']),
            'justificacion' => fake()->optional()->paragraph(),
            'nombre_solicitante' => fake()->optional()->name(),
            'fecha_creacion' => now(),
        ];
    }
}
