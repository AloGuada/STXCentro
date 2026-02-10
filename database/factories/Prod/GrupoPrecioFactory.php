<?php

namespace Database\Factories\Prod;

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
            'descripcion' => fake()->words(2, true),
            'precio' => fake()->randomFloat(2, 5, 50),
        ];
    }
}
