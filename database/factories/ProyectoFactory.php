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
            'tipo_contrato' => $this->faker->randomElement(['precio_unitario', 'alzado']),
            'monto' => $this->faker->randomFloat(2, 100000, 5000000),
            'monto_iva' => null,
            'anticipo' => $this->faker->randomFloat(2, 0, 500000),
            'garantia' => null,
            'estatus' => 'abierta',
            'activa' => true,
        ];
    }
}
