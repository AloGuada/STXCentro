<?php

namespace Database\Factories\Costos;

use App\Models\Costos\UsoCfdi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\UsoCfdi>
 */
class UsoCfdiFactory extends Factory
{
    protected $model = UsoCfdi::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clave' => fake()->unique()->regexify('[GIDPS][0-9]{2}'),
            'descripcion' => fake()->sentence(3),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
