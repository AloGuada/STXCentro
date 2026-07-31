<?php

namespace Database\Factories\Prod;

use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\PagoExtra;
use App\Models\Prod\TipoPagoExtra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\PagoExtra>
 */
class PagoExtraFactory extends Factory
{
    protected $model = PagoExtra::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descripcion' => fake()->sentence(3),
            'tipo_id' => TipoPagoExtra::factory(),
            'destajo_id' => Destajo::factory(),
            'grupo_trabajo_id' => GrupoTrabajo::factory(),
            'precio' => fake()->randomFloat(2, 100, 2000),
            'dias' => fake()->numberBetween(1, 6),
            'personas' => fake()->numberBetween(1, 10),
        ];
    }
}
