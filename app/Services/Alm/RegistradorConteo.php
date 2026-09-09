<?php

namespace App\Services\Alm;

use App\Enums\Alm\AjusteMotivo;
use App\Enums\Alm\ConteoEstatus;
use App\Models\Alm\Conteo;
use App\Models\Alm\ConteoDetalle;
use App\Models\Alm\Existencia;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Lo que pasa con una hoja de conteo después de imprimirla: se captura lo que
 * se encontró y se cierra.
 *
 * Capturar sella en cada renglón el saldo que el sistema tenía **en ese
 * momento**, no cuando se generó la hoja: entre programar y contar pudieron
 * pasar semanas de recepciones y salidas. Cerrar no toca el saldo: levanta un
 * ajuste con motivo `conteo_fisico` y es ése el que mueve el kardex, contra el
 * saldo bloqueado al guardar (que puede diferir del sellado si algo entró
 * mientras se contaba; manda el del ajuste).
 */
class RegistradorConteo
{
    public function __construct(private readonly RegistradorAjuste $ajustes) {}

    /**
     * Guarda lo contado de los renglones que vienen. Un renglón con cantidad
     * nula se des-captura (se tecleó por error y se borró). La hoja pasa a
     * `contando` con el primer renglón y toma como responsable al primero que
     * captura, si no tenía.
     *
     * @param  list<array{id: int, cantidad_contada: float|int|string|null, observaciones?: string|null}>  $renglones
     */
    public function capturar(Conteo $conteo, array $renglones, ?string $userId = null): Conteo
    {
        if (! $conteo->estatus->abierto()) {
            throw new InvalidArgumentException('Esta hoja ya está cerrada; lo que se cuente ahora va en una hoja nueva.');
        }

        return DB::transaction(function () use ($conteo, $renglones, $userId): Conteo {
            $detalles = $conteo->detalles()->get()->keyBy('id');

            foreach ($renglones as $renglon) {
                /** @var ConteoDetalle|null $detalle */
                $detalle = $detalles->get((int) $renglon['id']);

                if ($detalle === null) {
                    throw new InvalidArgumentException('Uno de los renglones no es de esta hoja.');
                }

                $contada = $renglon['cantidad_contada'];
                $vacio = $contada === null || $contada === '';

                $detalle->fill([
                    'cantidad_contada' => $vacio ? null : (float) $contada,
                    'cantidad_sistema' => $vacio ? null : $this->saldoActual($conteo, $detalle),
                    'observaciones' => $renglon['observaciones'] ?? null,
                ])->save();
            }

            $hayCapturados = $conteo->detalles()->whereNotNull('cantidad_contada')->exists();

            $conteo->fill([
                'estatus' => $hayCapturados ? ConteoEstatus::Contando : ConteoEstatus::Pendiente,
                'responsable_id' => $conteo->responsable_id ?? ($hayCapturados ? $userId : null),
            ])->save();

            return $conteo->refresh();
        });
    }

    /**
     * Cierra la hoja: exige que todo esté contado (un renglón sin contar no es
     * un cero, es una pregunta sin responder) y levanta el ajuste con los
     * renglones completos, también los exactos: el acta del conteo es la
     * evidencia de lo que se contó, no sólo de lo que descuadró. El ajuste
     * decide por su cuenta qué renglones llegan al kardex.
     */
    public function cerrar(Conteo $conteo, string $userId, ?string $observaciones = null): Conteo
    {
        if (! $conteo->estatus->abierto()) {
            throw new InvalidArgumentException('Esta hoja ya está cerrada.');
        }

        $detalles = $conteo->detalles()->get();
        $sinContar = $detalles->whereNull('cantidad_contada')->count();

        if ($detalles->isEmpty() || $sinContar > 0) {
            throw new InvalidArgumentException(
                $sinContar === 1
                    ? 'Falta 1 renglón por contar. Si no se encontró, se captura cero.'
                    : "Faltan {$sinContar} renglones por contar. Si no se encontraron, se capturan en cero."
            );
        }

        return DB::transaction(function () use ($conteo, $detalles, $userId, $observaciones): Conteo {
            $nota = "Cierre del conteo {$conteo->folio}";

            $ajuste = $this->ajustes->registrar(
                cabecera: [
                    'almacen_id' => $conteo->almacen_id,
                    'motivo' => AjusteMotivo::ConteoFisico->value,
                    'fecha' => today(),
                    'observaciones' => $observaciones === null || $observaciones === '' ? $nota : "{$nota}: {$observaciones}",
                    'autorizado_por' => $userId,
                ],
                renglones: $detalles->map(fn (ConteoDetalle $d): array => [
                    'articulo_id' => $d->articulo_id,
                    'cantidad_contada' => (float) $d->cantidad_contada,
                    'observaciones' => $d->observaciones,
                ])->values()->all(),
                userId: $userId,
            );

            $conteo->fill([
                'estatus' => ConteoEstatus::Cerrado,
                'fecha_cierre' => today(),
                'ajuste_id' => $ajuste->id,
                'observaciones' => $observaciones,
                'responsable_id' => $conteo->responsable_id ?? $userId,
            ])->save();

            return $conteo->refresh();
        });
    }

    /** Sin renglón de existencia el saldo es cero, y eso es un hecho. */
    private function saldoActual(Conteo $conteo, ConteoDetalle $detalle): float
    {
        $existencia = Existencia::query()
            ->where('almacen_id', $conteo->almacen_id)
            ->where('articulo_id', $detalle->articulo_id)
            ->first();

        return (float) ($existencia?->cantidad ?? 0);
    }
}
