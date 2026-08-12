<?php

namespace Database\Factories\Qal;

use App\Models\Qal\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\Obra>
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
