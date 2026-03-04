<?php

namespace Database\Factories\Rh;

use App\Models\Rh\Requerimiento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rh\Requerimiento>
 */
class RequerimientoFactory extends Factory
{
    protected $model = Requerimiento::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'descripcion' => fake()->sentence(4),
            'valor' => fake()->optional()->word(),
        ];
    }
}
