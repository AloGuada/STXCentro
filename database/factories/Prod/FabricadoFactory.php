<?php

namespace Database\Factories\Prod;

use App\Models\Pieza;
use App\Models\Prod\Destajo;
use App\Models\Prod\Fabricado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\Fabricado>
 */
class FabricadoFactory extends Factory
{
    protected $model = Fabricado::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'destajo_id' => Destajo::factory(),
            'dest_grupo_id' => null,
            'pieza_id' => Pieza::factory(),
            'cantidad' => fake()->numberBetween(1, 10),
            'porcentual' => 100,
            'precio_unitario_aplicado' => fake()->randomFloat(2, 5, 50),
            'total_calculado' => 0,
            'saldo_pendiente' => 0,
        ];
    }
}
