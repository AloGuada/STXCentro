<?php

namespace Database\Factories\Cob;

use App\Enums\Cob\IcsoeEstatus;
use App\Enums\Cob\IcsoeMetodo;
use App\Models\Cob\IcsoeSeguimiento;
use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IcsoeSeguimiento> */
class IcsoeSeguimientoFactory extends Factory
{
    protected $model = IcsoeSeguimiento::class;

    public function definition(): array
    {
        return [
            'proyecto_id' => Proyecto::factory(),
            'metodo' => IcsoeMetodo::Porcentaje,
            'estatus' => IcsoeEstatus::Vigente,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-03-31',
            'superficie_m2' => null,
            'costo_m2' => null,
            'porcentaje_mo' => 30,
            'prima_riesgo' => 7.58875,
            'monto_base' => 0,
        ];
    }

    public function pendiente(): static
    {
        return $this->state(fn () => [
            'estatus' => IcsoeEstatus::PendienteVerificacion,
            'monto_base_anterior' => 100000,
            'motivo_cambio' => 'Comparativo actualizado',
            'recalculado_at' => now(),
        ]);
    }

    public function porSuperficie(): static
    {
        return $this->state(fn () => [
            'metodo' => IcsoeMetodo::Superficie,
            'superficie_m2' => 1164,
            'costo_m2' => 1154,
        ]);
    }
}
