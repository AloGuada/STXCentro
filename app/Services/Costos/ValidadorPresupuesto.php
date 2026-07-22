<?php

namespace App\Services\Costos;

use App\Events\Costos\PresupuestoExcedido;
use App\Exceptions\Costos\SobregiroPresupuestalException;
use App\Models\Costos\ObraRubro;
use Illuminate\Database\Eloquent\Model;

/**
 * Centraliza la decisión de qué hacer cuando una operación pone un
 * obra_rubro en sobregiro o cerca del umbral crítico:
 *
 * - Si `costos.bloquear_sobregiro` = true y el monto excede disponible:
 *   lanza SobregiroPresupuestalException (la transacción debe abortarse).
 * - Si excede sin bloqueo activo: dispara PresupuestoExcedido(nivel='sobregiro').
 * - Si NO excede pero queda >= umbral crítico tras aplicar:
 *   dispara PresupuestoExcedido(nivel='critico').
 *
 * El servicio NO modifica el `acumulado` — eso sigue siendo responsabilidad
 * del modelo `aplicarImpactoPresupuestal()`. Aquí solo se decide si dejar
 * pasar y a qué nivel notificar.
 */
class ValidadorPresupuesto
{
    /**
     * Llamar ANTES de incrementar el acumulado. La fuente de verdad es
     * la suma actual + el monto que se va a sumar.
     *
     * Si `$allowSobregiro` es true, nunca lanza excepción: solo dispara
     * el evento de sobregiro y deja pasar (usado por apartados temporales,
     * donde el negocio permite sobrecargar pero quiere que se marque).
     */
    public function validar(ObraRubro $obraRubro, float $montoAdicional, Model $entrada, bool $allowSobregiro = false): void
    {
        $disponible = $obraRubro->disponible;

        if ($montoAdicional > $disponible + config('costos.epsilon_monto')) {
            if (! $allowSobregiro && config('costos.bloquear_sobregiro', false)) {
                throw new SobregiroPresupuestalException($obraRubro, $montoAdicional, $disponible);
            }

            event(new PresupuestoExcedido($obraRubro, $montoAdicional, $entrada, 'sobregiro'));

            return;
        }

        // No excede, pero podría dejar al rubro en zona crítica tras aplicar.
        $presup = (float) $obraRubro->presupuestado;
        if ($presup <= 0.0) {
            return;
        }

        $pctTrasAplicar = (($obraRubro->comprometido + $montoAdicional) / $presup) * 100.0;
        $umbral = (int) config('costos.umbral_alerta_porcentaje', 90);

        $pctActual = ($obraRubro->comprometido / $presup) * 100.0;

        // Solo dispara si este movimiento es el que cruza el umbral
        // (de no-critico a critico) — evita ruido en cada operación.
        if ($pctActual < $umbral && $pctTrasAplicar >= $umbral) {
            event(new PresupuestoExcedido($obraRubro, $montoAdicional, $entrada, 'critico'));
        }
    }
}
