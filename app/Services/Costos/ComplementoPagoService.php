<?php

namespace App\Services\Costos;

use App\Enums\Costos\ComplementoPagoEstatus;
use App\Models\Costos\ComplementoPago;
use App\Models\Costos\Factura;
use App\Models\Costos\Pago;
use Illuminate\Support\Carbon;

class ComplementoPagoService
{
    /**
     * Genera la obligación de complemento de pago para un pago recién ejecutado
     * contra una factura PPD. Idempotente por `pago_id`. Devuelve null si el
     * pago no corresponde a una factura PPD o si ya existe la obligación.
     */
    public function generarObligacion(Pago $pago): ?ComplementoPago
    {
        $factura = $pago->pagable;

        if (! $factura instanceof Factura || ! $factura->esPpd()) {
            return null;
        }

        if (ComplementoPago::where('pago_id', $pago->id)->exists()) {
            return null;
        }

        $fechaPago = $pago->fecha_pago_realizada ?? now();

        return ComplementoPago::create([
            'factura_id' => $factura->id,
            'pago_id' => $pago->id,
            'proveedor_id' => $factura->proveedor_id,
            'monto_pago' => $pago->monto_pago,
            'fecha_pago' => $fechaPago->toDateString(),
            'fecha_generacion' => now()->toDateString(),
            'fecha_limite' => $this->fechaLimite($fechaPago)->toDateString(),
            'estatus' => ComplementoPagoEstatus::Pendiente->value,
        ]);
    }

    /**
     * Fecha límite fiscal: día N del mes siguiente al pago (política SAT,
     * configurable en costos.complemento_pago.dia_limite_mes_siguiente).
     */
    public function fechaLimite(\DateTimeInterface|string $fechaPago): Carbon
    {
        $dia = (int) config('costos.complemento_pago.dia_limite_mes_siguiente', 5);

        return Carbon::parse($fechaPago)->addMonthNoOverflow()->day($dia);
    }
}
