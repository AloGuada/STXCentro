<?php

namespace Database\Factories\Prod;

use App\Models\Obra;
use App\Models\Prod\GrupoPrecio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\GrupoPrecio>
 */
class GrupoPrecioFactory extends Factory
{
    protected $model = GrupoPrecio::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'descripcion' => fake()->words(2, true),
            'precio_kilo' => fake()->randomFloat(4, 1, 50),
        ];
    }
}
