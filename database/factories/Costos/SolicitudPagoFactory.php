<?php

namespace Database\Factories\Costos;

use App\Models\Costos\SolicitudPago;
use App\Models\Costos\TipoSolicitud;
use App\Models\Departamento;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\SolicitudPago>
 */
class SolicitudPagoFactory extends Factory
{
    protected $model = SolicitudPago::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'solicitante_id' => User::factory(),
            'departamento_id' => Departamento::factory(),
            'proveedor_id' => Proveedor::factory(),
            'tipo_solicitud_id' => TipoSolicitud::factory(),
            'concepto' => fake()->sentence(),
            'justificacion' => fake()->optional()->paragraph(),
            'monto_total' => fake()->randomFloat(2, 100, 50000),
            'tipo_pago' => fake()->randomElement(['transferencia', 'cheque', 'efectivo']),
            'fecha_pago_solicitada' => fake()->optional()->date(),
            'estatus' => 'borrador',
        ];
    }

    public function pendienteFirma(): static
    {
        return $this->state(fn () => ['estatus' => 'pendiente_firma']);
    }

    public function aprobada(): static
    {
        return $this->state(fn () => ['estatus' => 'aprobada']);
    }

    public function pagada(): static
    {
        return $this->state(fn () => ['estatus' => 'pagada']);
    }
}
