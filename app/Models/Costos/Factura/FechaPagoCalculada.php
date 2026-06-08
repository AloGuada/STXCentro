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

        $dias = (int) ($this->factura->dias_credito ?? $this->factura->proveedor?->dias_credito_default ?? 0);

        $fecha = Carbon::parse($fechaBase)->addDays($dias);

        return $fecha->dayOfWeek === Carbon::FRIDAY
            ? $fecha
            : $fecha->next(Carbon::FRIDAY);
    }

    /**
     * Fecha base sobre la que se cuentan los días de crédito. Si el proveedor
     * exige respetar la fecha de la factura, siempre es la del CFDI; de lo
     * contrario, la base configurada (factura, recepción o aprobación).
     */
    private function fechaBase(): Carbon|string|null
    {
        if ($this->factura->proveedor?->respetar_fecha_factura) {
            return $this->factura->fecha_factura;
        }

        $base = $this->factura->base_dias_credito ?? BaseDiasCredito::Factura;

        return match ($base) {
            BaseDiasCredito::Factura => $this->factura->fecha_factura,
            BaseDiasCredito::Recepcion => $this->factura->entregas()->latest('fecha_entrega')->value('fecha_entrega'),
            BaseDiasCredito::Aprobacion => $this->factura->aprobada_costos_at,
        };
    }
}
