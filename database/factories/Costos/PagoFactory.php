<?php

namespace Database\Factories\Costos;

use App\Models\Costos\Pago;
use App\Models\Costos\SolicitudPago;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\Pago>
 */
class PagoFactory extends Factory
{
    protected $model = Pago::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pagable_type' => SolicitudPago::class,
            'pagable_id' => SolicitudPago::factory()->aprobada(),
            'monto_pago' => fake()->randomFloat(2, 100, 50000),
            'moneda' => 'mxn',
            'tipo_cambio' => 1.0,
            'tipo_pago' => fake()->randomElement(['contado', 'credito']),
            'fecha_pago_programada' => fake()->dateTimeBetween('now', '+30 days'),
            'fecha_pago_maxima' => fake()->dateTimeBetween('+30 days', '+60 days'),
            'estatus' => 'pendiente',
        ];
    }

    public function contado(): static
    {
        return $this->state(fn () => ['tipo_pago' => 'contado']);
    }

    public function credito(): static
    {
        return $this->state(fn () => ['tipo_pago' => 'credito']);
    }

    public function programado(): static
    {
        return $this->state(fn () => ['estatus' => 'programado']);
    }

    public function pagado(): static
    {
        return $this->state(fn () => [
            'estatus' => 'pagado',
            'fecha_pago_realizada' => now(),
        ]);
    }

    public function hijo(Pago $padre, int $numero): static
    {
        return $this->state(fn () => [
            'pagable_type' => $padre->pagable_type,
            'pagable_id' => $padre->pagable_id,
            'pago_padre_id' => $padre->id,
            'numero_parcialidad' => $numero,
            'moneda' => $padre->moneda,
            'tipo_cambio' => $padre->tipo_cambio,
            'tipo_pago' => $padre->tipo_pago,
            'estatus' => 'programado',
        ]);
    }
}
