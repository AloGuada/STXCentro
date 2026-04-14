<?php

namespace Database\Factories\Dg;

use App\Models\Dg\Carpeta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Dg\Carpeta>
 */
class CarpetaFactory extends Factory
{
    protected $model = Carpeta::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->word(),
            'descripcion' => null,
            'orden' => 0,
        ];
    }
}
