<?php

namespace Database\Factories\Drive;

use App\Models\Drive\Externo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Drive\Externo>
 */
class ExternoFactory extends Factory
{
    protected $model = Externo::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'telefono' => fake()->optional()->phoneNumber(),
            'empresa' => fake()->optional()->company(),
            'activo' => true,
        ];
    }
}
