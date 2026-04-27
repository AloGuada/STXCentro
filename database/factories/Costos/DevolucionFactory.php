<?php

namespace Database\Factories\Costos;

use App\Models\Costos\Devolucion;
use App\Models\Costos\EntregaDetalle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\Devolucion>
 */
class DevolucionFactory extends Factory
{
    protected $model = Devolucion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entrega_detalle_id' => EntregaDetalle::factory(),
            'cantidad' => fake()->randomFloat(2, 1, 10),
            'motivo' => fake()->sentence(),
            'fecha' => now()->toDateString(),
            'estatus' => 'vigente',
        ];
    }

    public function cancelada(): static
    {
        return $this->state(fn () => [
            'estatus' => 'cancelada',
            'motivo_cancelacion' => 'Devolución revertida por acuerdo con el proveedor',
        ]);
    }
}
