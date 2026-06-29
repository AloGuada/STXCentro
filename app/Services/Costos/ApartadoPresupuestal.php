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
 * - apartarDocumento(): crea RubroAfectado(Apartado) + increment acumulado.
 * - convertirAPermanente(): muta apartados vigentes de la entrada a Aplicado.
 * - cancelarApartadosDe(): libera todos los apartados/aplicados vigentes.
 * - liberarVencidos(): worker para el scheduled command.
 *
 * Los apartados siempre se permiten en sobregiro (el campo `sobre_giro` se
 * marca true cuando el rubro queda con disponible negativo).
 */
class ApartadoPresupuestal
{
    /** Valor por defecto si no hay configuración guardada. */
    public const DIAS_APARTADO = 5;

    public function __construct(private readonly ValidadorPresupuesto $validador) {}

    private function diasApartado(): int
    {
        return \App\Models\Costos\ConfiguracionCostos::actual()->dias_apartado ?: self::DIAS_APARTADO;
    }

    /**
     * Aparta presupuesto temporalmente para una entrada (SolicitudPago o
     * Requisicion). Cada item debe tener `obra_rubro_id` y `monto`.
     *
     * @param  iterable<array{obra_rubro_id: int, monto: float, descripcion?: string|null}>  $items
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
                );
            }
        });
    }

    /**
     * Convierte los apartados vigentes de la entrada en afectaciones
     * permanentes (Aplicado). No mueve el acumulado (ya estaba contado).
     * Si no hay apartados vigentes, lo deja pasar sin error.
     */
    public function convertirAPermanente(Model $entrada): void
    {
        DB::transaction(function () use ($entrada) {
            RubroAfectado::query()
                ->where('entrada_type', $entrada::class)
                ->where('entrada_id', $entrada->getKey())
                ->where('estatus', RubroAfectadoEstatus::Apartado->value)
                ->get()
                ->each(function (RubroAfectado $ra) {
                    $ra->update([
                        'estatus' => RubroAfectadoEstatus::Aplicado,
                        'apartado_hasta' => null,
                        'fecha_aplicacion' => now(),
                    ]);
                });
        });
    }

    /**
     * Cancela apartados/aplicados vigentes de la entrada y devuelve el
     * monto al acumulado del rubro. Útil al cancelar el documento dueño.
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
                ObraRubro::where('id', $ra->obra_rubro_id)
                    ->decrement('acumulado', (float) $ra->monto);

                $ra->update([
                    'estatus' => RubroAfectadoEstatus::Cancelado,
                    'descripcion' => trim(($ra->descripcion ? $ra->descripcion.' · ' : '').$motivo),
                ]);
            }
        });
    }

    /**
     * Procesa todos los RubroAfectado en estado Apartado cuyo apartado_hasta
     * ya pasó: decrementa el acumulado del rubro y los marca como Vencido.
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
                ObraRubro::where('id', $ra->obra_rubro_id)
                    ->decrement('acumulado', (float) $ra->monto);

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
     * @param  iterable<array{obra_rubro_id: int, monto: float, descripcion?: string|null}>  $items
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
     * Aplica un cargo presupuestal a un rubro: valida sobregiro, incrementa el
     * acumulado y registra el RubroAfectado. Primitiva compartida por el
     * apartado temporal y la afectación permanente (trait AfectaPresupuesto).
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
    ): RubroAfectado {
        $userId = $userId ?? Auth::id();
        $obraRubro = ObraRubro::findOrFail($obraRubroId);

        $this->validador->validar($obraRubro, $monto, $entrada, allowSobregiro: $allowSobregiro);

        ObraRubro::where('id', $obraRubroId)->increment('acumulado', $monto);

        $obraRubro->refresh();

        return RubroAfectado::create([
            'entrada_type' => $entrada::class,
            'entrada_id' => $entrada->getKey(),
            'obra_rubro_id' => $obraRubroId,
            'monto' => $monto,
            'sobre_giro' => $obraRubro->disponible < 0,
            'descripcion' => $descripcion,
            'tipo_movimiento' => 'cargo',
            'estatus' => $estatus,
            'apartado_hasta' => $apartadoHasta,
            'usuario_aplica_id' => $userId,
            'fecha_aplicacion' => now(),
        ]);
    }
}
