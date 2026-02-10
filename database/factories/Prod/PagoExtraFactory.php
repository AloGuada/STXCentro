<?php

namespace Database\Factories\Prod;

use App\Models\Prod\Destajo;
use App\Models\Prod\PagoExtra;
use App\Models\Prod\Tipo;
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
            'destajo_id' => Destajo::factory(),
            'dest_grupo_id' => null,
            'tipo_id' => Tipo::factory(),
            'descripcion' => fake()->sentence(3),
            'precio' => fake()->randomFloat(2, 100, 2000),
            'dias' => fake()->numberBetween(1, 7),
            'personas' => fake()->numberBetween(1, 10),
        ];
    }
}
