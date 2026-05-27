<?php

namespace Database\Factories\Cal;

use App\Models\Cal\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cal\Obra>
 */
class ObraFactory extends Factory
{
    protected $model = Obra::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'no' => fake()->unique()->bothify('S####-##'),
            'descripcion' => fake()->words(3, true),
            'activa' => true,
        ];
    }
}
