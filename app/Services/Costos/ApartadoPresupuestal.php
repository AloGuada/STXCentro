<?php

namespace App\Services\Costos;

use App\Enums\Costos\RubroAfectadoEstatus;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\RubroAfectado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Centraliza la mutación del presupuesto cuando se aparta temporalmente o se
 * convierte en afectación permanente.
 *
 * Modelo: `acumulado` = solo ejercido (Aplicado); `apartado` = reserva viva
 * (Apartado). El disponible = presupuestado − acumulado − apartado. Apartar NO
 * toca el ejercido; solo convertir a permanente mueve el monto de apartado a
 * acumulado. Así el número duro (`acumulado`) nunca se mueve solo.
 *
 * - apartarDocumento(): crea RubroAfectado(Apartado) + increment `apartado`.
 * - convertirAPermanente(): apartado → aplicado (baja `apartado`, sube `acumulado`).
 * - cancelarApartadosDe(): libera apartados (baja `apartado`) y aplicados
 *   (reverso del `acumulado` vía ledger).
 * - liberarVencidos(): baja `apartado` y marca Vencido; no toca el ejercido.
 *
 * `acumulado` es la única columna con libro de movimientos ({@see AcumuladoLedger}).
 * `apartado` es una reserva viva, mantenida exclusivamente por este servicio.
 *
 * Los apartados siempre se permiten en sobregiro (el campo `sobre_giro` se
 * marca true cuando el rubro queda con disponible negativo).
 */
class ApartadoPresupuestal
{
    /** Valor por defecto si no hay configuración guardada. */
    public const DIAS_APARTADO = 5;

    public function __construct(
        private readonly ValidadorPresupuesto $validador,
        private readonly AcumuladoLedger $ledger,
        private readonly TipoCambioService $tipoCambio,
    ) {}

    private function diasApartado(): int
    {
        return \App\Models\Costos\ConfiguracionCostos::actual()->dias_apartado ?: self::DIAS_APARTADO;
    }

    /**
     * Aparta presupuesto temporalmente para una entrada (SolicitudPago o
     * Requisicion). Cada item debe tener `obra_rubro_id` y `monto`.
     *
     * @param  iterable<array{obra_rubro_id: int, monto: float, descripcion?: string|null, moneda?: string, tipo_cambio?: float}>  $items
     */
    public function apartarDocumento(Model $entrada, iterable $items, ?string $userId = null): void
    {
        $userId = $userId ?? Auth::id();
        $apartadoHasta = Carbon::today()->addDays($this->diasApartado());

        DB::transaction(function () use ($entrada, $items, $userId, $apartadoHasta) {
            foreach ($items as $item) {
                $this->aplicarCargo(
                    entrada: $entrada,
                    obraRubroId: (int) $item['obra_rubro_id'],
                    monto: (float) $item['monto'],
                    estatus: RubroAfectadoEstatus::Apartado,
                    descripcion: $item['descripcion'] ?? null,
                    userId: $userId,
                    apartadoHasta: $apartadoHasta,
                    allowSobregiro: true,
                    moneda: $item['moneda'] ?? 'mxn',
                    tc: $item['tipo_cambio'] ?? null,
                );
            }
        });
    }

    /**
     * Convierte los apartados vigentes de la entrada en afectaciones
     * permanentes (Aplicado): mueve el monto de `apartado` (comprometido) a
     * `acumulado` (ejercido). El comprometido total no cambia; el número duro
     * sube. Si no hay apartados vigentes, lo deja pasar sin error.
     */
    public function convertirAPermanente(Model $entrada): void
    {
        DB::transaction(function () use ($entrada) {
            $apartados = RubroAfectado::query()
                ->where('entrada_type', $entrada::class)
                ->where('entrada_id', $entrada->getKey())
                ->where('estatus', RubroAfectadoEstatus::Apartado->value)
                ->get();

            foreach ($apartados as $ra) {
                $obraRubro = ObraRubro::whereKey($ra->obra_rubro_id)->lockForUpdate()->firstOrFail();

                $obraRubro->update(['apartado' => max(0.0, (float) $obraRubro->apartado - (float) $ra->monto)]);
                $this->ledger->registrar($obraRubro, (float) $ra->monto, $ra->id, 'apartado → aplicado');

                $ra->update([
                    'estatus' => RubroAfectadoEstatus::Aplicado,
                    'apartado_hasta' => null,
                    'fecha_aplicacion' => now(),
                ]);
            }
        });
    }

    /**
     * Cancela apartados/aplicados vigentes de la entrada. Un Aplicado revierte
     * el `acumulado` (ejercido) vía el ledger; un Apartado solo baja la reserva
     * viva (`apartado`), sin tocar el número duro. Útil al cancelar el documento
     * dueño.
     */
    public function cancelarApartadosDe(Model $entrada, string $motivo = 'documento cancelado'): void
    {
        DB::transaction(function () use ($entrada, $motivo) {
            $afectaciones = RubroAfectado::query()
                ->where('entrada_type', $entrada::class)
                ->where('entrada_id', $entrada->getKey())
                ->whereIn('estatus', array_map(fn ($e) => $e->value, RubroAfectadoEstatus::activos()))
                ->get();

            foreach ($afectaciones as $ra) {
                if ($ra->estatus === RubroAfectadoEstatus::Aplicado) {
                    $this->ledger->registrarPorId($ra->obra_rubro_id, -(float) $ra->monto, $ra->id, $motivo);
                } else {
                    $obraRubro = ObraRubro::whereKey($ra->obra_rubro_id)->lockForUpdate()->firstOrFail();
                    $obraRubro->update(['apartado' => max(0.0, (float) $obraRubro->apartado - (float) $ra->monto)]);
                }

                $ra->update([
                    'estatus' => RubroAfectadoEstatus::Cancelado,
                    'descripcion' => trim(($ra->descripcion ? $ra->descripcion.' · ' : '').$motivo),
                ]);
            }
        });
    }

    /**
     * Procesa todos los RubroAfectado en estado Apartado cuyo apartado_hasta
     * ya pasó: baja la reserva viva (`apartado`) del rubro y los marca como
     * Vencido. No toca el ejercido (`acumulado`), que nunca los contó.
     * Usado por el comando programado costos:liberar-apartados-vencidos.
     */
    public function liberarVencidos(): int
    {
        $hoy = Carbon::today()->toDateString();
        $count = 0;

        DB::transaction(function () use ($hoy, &$count) {
            $vencidos = RubroAfectado::query()
                ->where('estatus', RubroAfectadoEstatus::Apartado->value)
                ->whereDate('apartado_hasta', '<', $hoy)
                ->get();

            foreach ($vencidos as $ra) {
                $obraRubro = ObraRubro::whereKey($ra->obra_rubro_id)->lockForUpdate()->firstOrFail();
                $obraRubro->update(['apartado' => max(0.0, (float) $obraRubro->apartado - (float) $ra->monto)]);

                $ra->update([
                    'estatus' => RubroAfectadoEstatus::Vencido,
                    'vencido_at' => now(),
                ]);
                $count++;
            }
        });

        return $count;
    }

    /**
     * Re-aparta presupuesto para una entrada cuyos apartados vencieron.
     * No tiene impacto si la entrada todavía tiene apartados vigentes.
     *
     * @param  iterable<array{obra_rubro_id: int, monto: float, descripcion?: string|null, moneda?: string, tipo_cambio?: float}>  $items
     */
    public function reApartarDocumento(Model $entrada, iterable $items, ?string $userId = null): void
    {
        $vigentes = RubroAfectado::query()
            ->where('entrada_type', $entrada::class)
            ->where('entrada_id', $entrada->getKey())
            ->whereIn('estatus', array_map(fn ($e) => $e->value, RubroAfectadoEstatus::activos()))
            ->exists();

        if ($vigentes) {
            return;
        }

        $this->apartarDocumento($entrada, $items, $userId);
    }

    /**
     * Aplica un cargo presupuestal a un rubro: valida sobregiro y registra el
     * RubroAfectado. Un cargo `Aplicado` incrementa el ejercido (`acumulado`)
     * vía el ledger; un `Apartado` incrementa la reserva viva (`apartado`) sin
     * tocar el número duro. Primitiva compartida por el apartado temporal y la
     * afectación permanente (trait AfectaPresupuesto).
     *
     * `$monto` viene en `$moneda` (la divisa del documento). El presupuesto
     * siempre pesa en MXN: se convierte con `$tc` (el tipo de cambio guardado en
     * el documento) y el RubroAfectado sella `moneda`, `monto_origen` (divisa) y
     * `tipo_cambio`; `monto` queda en MXN. Si el llamador no pasa `$tc`, se toma
     * el TC de referencia del día ({@see TipoCambioService}) como respaldo.
     */
    public function aplicarCargo(
        Model $entrada,
        int $obraRubroId,
        float $monto,
        RubroAfectadoEstatus $estatus,
        ?string $descripcion = null,
        ?string $userId = null,
        ?Carbon $apartadoHasta = null,
        bool $allowSobregiro = false,
        string $moneda = 'mxn',
        ?float $tc = null,
    ): RubroAfectado {
        $userId = $userId ?? Auth::id();
        $moneda = strtolower($moneda);
        $tipoCambio = $tc ?? ($moneda === 'mxn' ? 1.0 : $this->tipoCambio->mxnPorUnidad($moneda));
        $montoOrigen = $monto;
        $montoMxn = round($monto * $tipoCambio, 2);

        return DB::transaction(function () use ($entrada, $obraRubroId, $montoMxn, $montoOrigen, $moneda, $tipoCambio, $estatus, $descripcion, $userId, $apartadoHasta, $allowSobregiro): RubroAfectado {
            $obraRubro = ObraRubro::whereKey($obraRubroId)->lockForUpdate()->firstOrFail();

            $this->validador->validar($obraRubro, $montoMxn, $entrada, allowSobregiro: $allowSobregiro);

            $sobreGiro = ($obraRubro->disponible - $montoMxn) < 0;

            $ra = RubroAfectado::create([
                'entrada_type' => $entrada::class,
                'entrada_id' => $entrada->getKey(),
                'obra_rubro_id' => $obraRubroId,
                'monto' => $montoMxn,
                'moneda' => $moneda,
                'monto_origen' => $montoOrigen,
                'tipo_cambio' => $tipoCambio,
                'sobre_giro' => $sobreGiro,
                'descripcion' => $descripcion,
                'tipo_movimiento' => 'cargo',
                'estatus' => $estatus,
                'apartado_hasta' => $apartadoHasta,
                'usuario_aplica_id' => $userId,
                'fecha_aplicacion' => now(),
            ]);

            if ($estatus === RubroAfectadoEstatus::Aplicado) {
                $this->ledger->registrar($obraRubro, $montoMxn, $ra->id, $descripcion ?? $estatus->value, $userId);
            } else {
                // Reserva viva: no toca el ejercido, solo el comprometido.
                $obraRubro->update(['apartado' => (float) $obraRubro->apartado + $montoMxn]);
            }

            return $ra;
        });
    }
}
