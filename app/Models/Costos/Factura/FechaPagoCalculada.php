<?php

namespace App\Models\Costos\Factura;

use App\Enums\Costos\BaseDiasCredito;
use App\Models\Costos\Factura;
use Illuminate\Support\Carbon;

/**
 * Calcula la fecha tentativa de pago de una factura: aplica los días de crédito
 * sobre la base configurada (factura, recepción o aprobación) y ajusta al
 * próximo viernes hábil si la fecha resultante no cae en viernes.
 *
 * Devuelve null si falta información para calcular (ej. base=aprobación pero la
 * factura aún no fue aprobada por costos).
 */
final class FechaPagoCalculada
{
    public function __construct(private readonly Factura $factura) {}

    public function calcular(): ?Carbon
    {
        $this->factura->loadMissing('proveedor');

        $fechaBase = $this->fechaBase();

        if (! $fechaBase) {
            return null;
        }

        // Días de crédito: override de la factura, o el default del proveedor;
        // si no hay (0/null), el término normal es 30 días.
        $dias = (int) ($this->factura->dias_credito ?? ($this->factura->proveedor?->dias_credito_default ?: 30));

        $fecha = Carbon::parse($fechaBase)->addDays($dias);

        // Ajuste a viernes con corte el miércoles: lunes-miércoles → viernes de
        // esa misma semana; jueves en adelante (pasado el miércoles) → viernes
        // de la semana siguiente.
        $viernes = $fecha->copy()->startOfWeek(Carbon::MONDAY)->addDays(4);

        if ($fecha->dayOfWeekIso > Carbon::WEDNESDAY) {
            $viernes->addWeek();
        }

        return $viernes;
    }

    /**
     * Fecha base sobre la que se cuentan los días de crédito. Si el proveedor
     * exige respetar la fecha de la factura, siempre es la del CFDI; de lo
     * contrario, se cuenta desde HOY (las bases por recepción/aprobación son
     * overrides explícitos que casi no se usan).
     */
    private function fechaBase(): Carbon|string|null
    {
        if ($this->factura->proveedor?->respetar_fecha_factura) {
            return $this->factura->fecha_factura;
        }

        return match ($this->factura->base_dias_credito) {
            BaseDiasCredito::Recepcion => $this->factura->entregas()->latest('fecha_entrega')->value('fecha_entrega'),
            BaseDiasCredito::Aprobacion => $this->factura->aprobada_costos_at,
            default => Carbon::today(),
        };
    }
}
