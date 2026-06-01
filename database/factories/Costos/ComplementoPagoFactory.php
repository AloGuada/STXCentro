<?php

namespace Database\Factories\Costos;

use App\Models\Costos\ComplementoPago;
use App\Models\Costos\Factura;
use App\Models\Costos\Pago;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\ComplementoPago>
 */
class ComplementoPagoFactory extends Factory
{
    protected $model = ComplementoPago::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $monto = fake()->randomFloat(2, 100, 50000);
        $fechaPago = now();

        return [
            'factura_id' => Factura::factory(),
            'pago_id' => Pago::factory(),
            'proveedor_id' => Proveedor::factory(),
            'monto_pago' => $monto,
            'fecha_pago' => $fechaPago->toDateString(),
            'fecha_generacion' => $fechaPago->toDateString(),
            'fecha_limite' => $fechaPago->copy()->addMonthNoOverflow()->day(5)->toDateString(),
            'estatus' => 'pendiente',
        ];
    }

    public function pendiente(): static
    {
        return $this->state(fn () => ['estatus' => 'pendiente']);
    }

    public function cumplido(): static
    {
        return $this->state(fn () => ['estatus' => 'cumplido', 'recibido_at' => now()]);
    }

    public function vencido(): static
    {
        return $this->state(fn () => ['estatus' => 'vencido']);
    }
}
