<?php

namespace Database\Factories\Drive;

use App\Models\Drive\Carpeta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Drive\Carpeta>
 */
class CarpetaFactory extends Factory
{
    protected $model = Carpeta::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'nombre' => fake()->words(3, true),
            'descripcion' => fake()->optional()->sentence(),
        ];
    }
}
