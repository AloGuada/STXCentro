<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Proyecto>
 */
class ProyectoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'no' => 'PRY-'.$this->faker->unique()->numberBetween(1000, 9999),
            'descripcion' => $this->faker->sentence(3),
            'cliente_id' => null,
            'estatus' => 'abierta',
            'activa' => true,
        ];
    }
}
