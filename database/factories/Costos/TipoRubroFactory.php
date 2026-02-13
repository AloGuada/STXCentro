<?php

namespace Database\Factories\Costos;

use App\Models\Costos\TipoRubro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\TipoRubro>
 */
class TipoRubroFactory extends Factory
{
    protected $model = TipoRubro::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descripcion' => fake()->words(3, true),
        ];
    }
}
