<?php

namespace App\Services\Prod;

use App\Models\Concepto;
use App\Models\Prod\LiquidacionDetalle;
use App\Models\Prod\Registro;
use Illuminate\Support\Collection;

/**
 * Cuánto se ha pagado de cada pieza contra lo que el catálogo vigente manda
 * fabricar.
 *
 * La unidad es la **pieza equivalente**: capturar 10 piezas al 60% consume 6 y
 * deja 4 para liquidarse en un destajo posterior. Así el mismo lote se puede
 * pagar en parcialidades sin rebasar nunca lo que pide el catálogo.
 *
 * El acumulado se cuenta **por linaje dentro de la obra, sumando todas las
 * versiones del catálogo**: al versionar, las piezas copiadas son filas nuevas
 * sin registros propios, así que contarlas por `concepto_id` a secas dejaría el
 * avance en cero y permitiría volver a pagar lo ya fabricado. Cada copia recuerda
 * de qué pieza viene (`concepto_origen_id`) y todas comparten la misma raíz.
 */
class AvanceDePiezas
{
    /** Margen para no rechazar por ruido de redondeo del porcentaje. */
    private const EPSILON = 0.0001;

    /**
     * Piezas equivalentes ya comprometidas en la obra, agrupadas por linaje.
     *
     * Se suman dos fuentes que no se solapan:
     *  - lo **pagado**, leído del snapshot inmutable de las liquidaciones, que
     *    no se mueve aunque después se edite, renombre o borre la pieza;
     *  - lo **capturado en semanas todavía abiertas**, que sigue siendo
     *    editable y por eso se lee de los registros.
     *
     * La clave es la pieza raíz del linaje (`concepto_origen_id` hacia arriba),
     * no el texto de la marca: así renombrar una pieza al versionar el catálogo
     * no reinicia el acumulado. Cuando la pieza ya no existe se cae al modelo del
     * snapshot (marca + etapa), para no perder lo pagado ni mezclar dos piezas
     * que comparten marca en etapas distintas.
     */
    public function mapaDeObra(int $obraId): AvanceDeObra
    {
        $raices = $this->raicesDeLinaje($obraId);

        $totales = [];

        foreach (LiquidacionDetalle::where('obra_id', $obraId)->get(['concepto_id', 'marca', 'etapa', 'cantidad', 'porcentaje']) as $detalle) {
            $clave = $this->clave($raices, $detalle->concepto_id, $detalle->marca, $detalle->etapa);
            $totales[$clave] = ($totales[$clave] ?? 0) + $detalle->cantidad * ((float) $detalle->porcentaje / 100);
        }

        foreach ($this->registrosNoLiquidados($obraId) as $registro) {
            $clave = $this->clave($raices, $registro->concepto_id, $registro->concepto?->marca, $registro->concepto?->etapa);
            $totales[$clave] = ($totales[$clave] ?? 0) + $registro->piezasEquivalentes();
        }

        return new AvanceDeObra($raices, $totales);
    }

    /**
     * Mapa conceptoId => id de la pieza raíz de su linaje, para toda la obra.
     *
     * @return array<int, int>
     */
    private function raicesDeLinaje(int $obraId): array
    {
        $origenes = Concepto::query()
            ->where('obra_id', $obraId)
            ->pluck('concepto_origen_id', 'id')
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
    private function clave(array $raices, ?int $conceptoId, ?string $marca, ?string $etapa): string
    {
        return isset($raices[$conceptoId])
            ? 'raiz:'.$raices[$conceptoId]
            : 'modelo:'.Concepto::claveDeModelo($marca, $etapa);
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
            ->with('concepto:id,marca,etapa')
            ->whereHas('concepto', fn ($q) => $q->where('obra_id', $obraId))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('prod_destajos')
                ->where('prod_destajos.cerrado', true)
                ->whereColumn('prod_destajos.fecha_inicio', '<=', 'prod_registros.fecha')
                ->whereColumn('prod_destajos.fecha_fin', '>=', 'prod_registros.fecha'))
            ->get(['id', 'concepto_id', 'cantidad', 'porcentaje']);
    }

    /**
     * Piezas equivalentes que todavía se pueden pagar de este concepto. Nunca
     * negativo: si el catálogo se recortó por debajo de lo ya pagado, queda 0.
     */
    public function disponible(Concepto $concepto): float
    {
        return round(max(0, (float) $concepto->cantidad - $this->capturado($concepto)), 4);
    }

    /** Piezas equivalentes ya pagadas o comprometidas de esta pieza. */
    public function capturado(Concepto $concepto): float
    {
        return $this->mapaDeObra($concepto->obra_id)->capturadoDe($concepto);
    }

    /** ¿Cabe pagar esta cantidad a este porcentaje sin rebasar el catálogo? */
    public function cabe(Concepto $concepto, int $cantidad, float $porcentaje): bool
    {
        $consumo = round($cantidad * ($porcentaje / 100), 4);

        return $consumo <= $this->disponible($concepto) + self::EPSILON;
    }

    /**
     * Decora una colección de conceptos con su avance, sin consultar la BD una
     * vez por pieza.
     *
     * @param  Collection<int, Concepto>  $conceptos
     * @return Collection<int, Concepto>
     */
    public function decorar(Collection $conceptos): Collection
    {
        $porObra = $conceptos
            ->pluck('obra_id')
            ->unique()
            ->mapWithKeys(fn (int $obraId) => [$obraId => $this->mapaDeObra($obraId)]);

        return $conceptos->each(function (Concepto $concepto) use ($porObra): void {
            $capturado = $porObra->get($concepto->obra_id)?->capturadoDe($concepto) ?? 0.0;

            $concepto->setAttribute('capturado', $capturado);
            $concepto->setAttribute('disponible', round(max(0, (float) $concepto->cantidad - $capturado), 4));
        });
    }
}
