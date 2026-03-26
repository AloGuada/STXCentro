<?php

namespace Database\Factories\Costos;

use App\Models\Costos\Entrega;
use App\Models\Costos\Factura;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\Entrega>
 */
class EntregaFactory extends Factory
{
    protected $model = Entrega::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'factura_id' => Factura::factory(),
            'recibido_por' => User::factory(),
            'fecha_entrega' => fake()->date(),
            'observaciones' => fake()->optional()->sentence(),
            'tipo' => fake()->randomElement(['parcial', 'completa']),
        ];
    }
}
