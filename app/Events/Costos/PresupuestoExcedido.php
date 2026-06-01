<?php

namespace App\Events\Costos;

use App\Models\Costos\ObraRubro;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado cuando una operación causa que un obra_rubro quede en
 * sobregiro (acumulado > presupuestado), o cuando supera el umbral
 * crítico de alerta. El listener `EnviarAlertaPresupuesto` notifica
 * a los aprobadores configurados del departamento involucrado.
 */
class PresupuestoExcedido
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ObraRubro $obraRubro,
        public readonly float $montoIntentado,
        public readonly Model $entrada,
        public readonly string $nivel,
    ) {}

    /**
     * 'sobregiro' (acumulado > presupuestado) o 'critico' (>= umbral).
     */
    public function esSobregiro(): bool
    {
        return $this->nivel === 'sobregiro';
    }
}
