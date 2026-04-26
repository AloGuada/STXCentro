<?php

namespace Database\Factories\Costos;

use App\Models\Costos\Factura;
use App\Models\Costos\NotaCredito;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\NotaCredito>
 */
class NotaCreditoFactory extends Factory
{
    protected $model = NotaCredito::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $monto = fake()->randomFloat(2, 100, 5000);

        return [
            'factura_id' => Factura::factory(),
            'subtotal' => round($monto / 1.16, 2),
            'iva_trasladado' => round($monto - ($monto / 1.16), 2),
            'monto' => $monto,
            'concepto' => fake()->sentence(),
            'fecha_emision' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'estatus' => 'vigente',
        ];
    }

    public function cancelada(): static
    {
        return $this->state(fn () => [
            'estatus' => 'cancelada',
            'motivo_cancelacion' => 'Anulada por el proveedor',
        ]);
    }
}
