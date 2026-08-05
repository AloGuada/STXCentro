<?php

namespace App\Services\Prod;

use App\Models\Prod\LiquidacionDetalle;
use App\Models\Prod\Pieza;
use App\Models\Prod\Registro;
use Illuminate\Support\Collection;

/**
 * Cuánto se ha pagado de cada pieza en cada proceso.
 *
 * La unidad es la **pieza equivalente**: una pieza vale 1 en cada proceso por el
 * que pasa, y pagarla al 60% consume 0.6 dejando 0.4 para una semana posterior.
 * Así el mismo QS se puede pagar en parcialidades sin rebasar nunca su tope, y
 * soldarlo no consume nada de lo que le toca por pintarlo.
 *
 * El acumulado se cuenta **por linaje de pieza dentro de la obra, sumando todas
 * las versiones del catálogo**: al versionar, las piezas copiadas son filas
 * nuevas sin registros propios, así que contarlas por `pieza_id` a secas dejaría
 * el avance en cero y permitiría volver a pagar lo ya fabricado. Cada copia
 * recuerda de qué pieza viene (`pieza_origen_id`) y todas comparten la misma
 * raíz.
 */
class AvanceDePiezas
{
    /** Lo máximo que se puede pagar de una pieza en un proceso. */
    private const TOPE_POR_PIEZA = 1.0;

    /** Margen para no rechazar por ruido de redondeo del porcentaje. */
    private const EPSILON = 0.0001;

    /**
     * Avance ya comprometido en la obra, agrupado por (linaje de pieza, proceso).
     *
     * Se suman dos fuentes que no se solapan:
     *  - lo **pagado**, leído del snapshot inmutable de las liquidaciones, que
     *    no se mueve aunque después se edite, renombre o borre la pieza;
     *  - lo **capturado en semanas todavía abiertas**, que sigue siendo
     *    editable y por eso se lee de los registros.
     *
     * Cuando la pieza ya no existe se cae al QS del snapshot, para no perder lo
     * pagado ni mezclarlo con otra pieza.
     */
    public function mapaDeObra(int $obraId): AvanceDeObra
    {
        $raices = $this->raicesDeLinaje($obraId);

        $totales = [];

        $detalles = LiquidacionDetalle::query()
            ->where('obra_id', $obraId)
            ->get(['pieza_id', 'qs', 'proceso_id', 'porcentaje']);

        foreach ($detalles as $detalle) {
            $clave = $this->clave($raices, $detalle->pieza_id, $detalle->qs, $detalle->proceso_id);
            $totales[$clave] = ($totales[$clave] ?? 0) + (float) $detalle->porcentaje / 100;
        }

        foreach ($this->registrosNoLiquidados($obraId) as $registro) {
            $clave = $this->clave($raices, $registro->pieza_id, $registro->pieza?->qs, $registro->proceso_id);
            $totales[$clave] = ($totales[$clave] ?? 0) + $registro->piezasEquivalentes();
        }

        return new AvanceDeObra($raices, $totales);
    }

    /**
     * Mapa piezaId => id de la pieza raíz de su linaje, para toda la obra.
     *
     * @return array<int, int>
     */
    private function raicesDeLinaje(int $obraId): array
    {
        $origenes = Pieza::query()
            ->whereHas('catalogo', fn ($q) => $q->where('obra_id', $obraId))
            ->pluck('pieza_origen_id', 'id')
            ->all();

        $raices = [];

        foreach (array_keys($origenes) as $id) {
            $actual = $id;
            $vistos = [];

            // El `isset($vistos)` corta cualquier ciclo por datos corruptos.
            while (! empty($origenes[$actual]) && ! isset($vistos[$actual])) {
                $vistos[$actual] = true;
                $actual = $origenes[$actual];
            }

            $raices[$id] = $actual;
        }

        return $raices;
    }

    /**
     * @param  array<int, int>  $raices
     */
    private function clave(array $raices, ?int $piezaId, ?string $qs, ?int $procesoId): string
    {
        $pieza = isset($raices[$piezaId])
            ? 'raiz:'.$raices[$piezaId]
            : 'qs:'.($qs ?? '');

        return $pieza.'|proceso:'.($procesoId ?? 0);
    }

    /**
     * Registros que aún no están respaldados por una liquidación: los de
     * semanas abiertas y los sueltos (capturados en un destajo que se borró).
     * Se cuentan por prudencia: mejor topar de más que pagar dos veces.
     *
     * @return Collection<int, Registro>
     */
    private function registrosNoLiquidados(int $obraId): Collection
    {
        return Registro::query()
            ->with('pieza:id,qs')
            ->whereHas('pieza.catalogo', fn ($q) => $q->where('obra_id', $obraId))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('prod_destajos')
                ->where('prod_destajos.cerrado', true)
                ->whereColumn('prod_destajos.fecha_inicio', '<=', 'prod_registros.fecha')
                ->whereColumn('prod_destajos.fecha_fin', '>=', 'prod_registros.fecha'))
            ->get(['id', 'pieza_id', 'proceso_id', 'porcentaje']);
    }

    /**
     * Fracción de pieza que todavía se puede pagar de este QS en este proceso.
     * Nunca negativo.
     */
    public function disponible(Pieza $pieza, int $procesoId): float
    {
        return round(max(0, self::TOPE_POR_PIEZA - $this->capturado($pieza, $procesoId)), 4);
    }

    /** Fracción ya pagada o comprometida de esta pieza en este proceso. */
    public function capturado(Pieza $pieza, int $procesoId): float
    {
        return $this->mapaDeObra($this->obraDe($pieza))->capturadoDe($pieza, $procesoId);
    }

    /** ¿Cabe pagar esta pieza a este porcentaje sin rebasar su tope? */
    public function cabe(Pieza $pieza, int $procesoId, float $porcentaje): bool
    {
        return round($porcentaje / 100, 4) <= $this->disponible($pieza, $procesoId) + self::EPSILON;
    }

    /**
     * Decora una colección de piezas con su avance por proceso, sin consultar la
     * base una vez por pieza. Cada pieza recibe `avance`: procesoId => capturado.
     *
     * @param  Collection<int, Pieza>  $piezas
     * @param  list<int>  $procesoIds
     * @return Collection<int, Pieza>
     */
    public function decorar(Collection $piezas, array $procesoIds): Collection
    {
        $porObra = $piezas
            ->map(fn (Pieza $pieza) => $this->obraDe($pieza))
            ->unique()
            ->mapWithKeys(fn (int $obraId) => [$obraId => $this->mapaDeObra($obraId)]);

        return $piezas->each(function (Pieza $pieza) use ($porObra, $procesoIds): void {
            $mapa = $porObra->get($this->obraDe($pieza));

            $avance = [];
            foreach ($procesoIds as $procesoId) {
                $capturado = $mapa?->capturadoDe($pieza, $procesoId) ?? 0.0;

                $avance[$procesoId] = [
                    'capturado' => $capturado,
                    'disponible' => round(max(0, self::TOPE_POR_PIEZA - $capturado), 4),
                ];
            }

            $pieza->setAttribute('avance', $avance);
        });
    }

    /** La obra sale del catálogo de la pieza, que es quien la ancla. */
    private function obraDe(Pieza $pieza): int
    {
        $pieza->loadMissing('catalogo:id,obra_id');

        return (int) ($pieza->catalogo?->obra_id ?? 0);
    }
}
