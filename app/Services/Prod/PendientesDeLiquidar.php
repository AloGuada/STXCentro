<?php

namespace App\Services\Prod;

use App\Models\Prod\Destajo;
use App\Models\Prod\Registro;
use Illuminate\Support\Collection;

/**
 * Piezas que se empezaron a pagar en parcialidades y todavía tienen saldo.
 *
 * No incluye las piezas que nadie ha tocado (esas se capturan normal): sólo lo
 * que quedó a medias en algún proceso, para poder liquidarlo en el destajo
 * siguiente al mismo grupo que lo trabajó.
 */
class PendientesDeLiquidar
{
    public function __construct(private AvanceDePiezas $avance) {}

    /**
     * @return Collection<int, array{
     *     pieza_id: int,
     *     qr: string,
     *     qs: ?string,
     *     marca: string,
     *     lote: ?string,
     *     descripcion: string,
     *     proceso_id: int,
     *     proceso: string,
     *     obra: string,
     *     grupo_trabajo_id: int|null,
     *     grupo_trabajo: string|null,
     *     pagado: float,
     *     saldo: float,
     *     porcentaje_sugerido: float
     * }>
     */
    public function paraDestajo(Destajo $destajo): Collection
    {
        $parciales = Registro::query()
            ->with(['pieza.marca.obra', 'pieza.catalogo', 'proceso', 'grupoTrabajo'])
            ->where('porcentaje', '<', 100)
            ->where('fecha', '<', $destajo->fecha_inicio)
            ->orderByDesc('fecha')
            ->get()
            ->filter(fn (Registro $registro) => $registro->pieza?->marca !== null);

        if ($parciales->isEmpty()) {
            return collect();
        }

        $capturadoPorObra = $parciales
            ->map(fn (Registro $registro) => (int) $registro->pieza->catalogo?->obra_id)
            ->unique()
            ->filter()
            ->mapWithKeys(fn (int $obraId) => [$obraId => $this->avance->mapaDeObra($obraId)]);

        return $parciales
            // Una fila por pieza y proceso: el último parcial manda para sugerir grupo.
            ->unique(fn (Registro $registro) => $registro->pieza_id.'|'.$registro->proceso_id)
            ->map(function (Registro $registro) use ($capturadoPorObra) {
                $pieza = $registro->pieza;
                $marca = $pieza->marca;
                $obraId = (int) $pieza->catalogo?->obra_id;
                $procesoId = (int) $registro->proceso_id;

                $pagado = $capturadoPorObra->get($obraId)?->capturadoDe($pieza, $procesoId) ?? 0.0;
                $saldo = round(max(0, 1 - $pagado), 4);

                if ($saldo <= 0) {
                    return null;
                }

                return [
                    'pieza_id' => (int) $pieza->id,
                    'qr' => $pieza->qr,
                    'qs' => $pieza->qs,
                    'marca' => $marca->marca,
                    'lote' => $marca->lote,
                    'descripcion' => $marca->descripcion,
                    'proceso_id' => $procesoId,
                    'proceso' => $registro->proceso?->nombre ?? '',
                    'obra' => $marca->obra?->no ?? '',
                    'grupo_trabajo_id' => $registro->grupo_trabajo_id,
                    'grupo_trabajo' => $registro->grupoTrabajo?->descripcion,
                    'pagado' => $pagado,
                    'saldo' => $saldo,
                    // Cerrar el saldo completo es lo que se ofrece por default.
                    'porcentaje_sugerido' => round($saldo * 100, 2),
                ];
            })
            ->filter()
            ->sortBy(['marca', 'qr'])
            ->values();
    }
}
