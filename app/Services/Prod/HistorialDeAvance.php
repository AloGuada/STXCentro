<?php

namespace App\Services\Prod;

/**
 * En qué avance va cada pieza de una obra en una semana dada, y cuánto lleva
 * pagado con esa semana incluida. Lo arma AvanceDePiezas::historialHasta().
 *
 * El avance cuenta **semanas**, no capturas: dos parcialidades de la misma pieza
 * en la misma semana se liquidan en un solo renglón, así que son un solo avance.
 */
readonly class HistorialDeAvance
{
    /**
     * @param  array<int, int>  $raices  piezaId => id de la pieza raiz de su linaje
     * @param  array<string, array<string, true>>  $semanasPrevias  clave => semanas anteriores con pago
     * @param  array<string, float>  $acumulados  clave => piezas equivalentes hasta la semana, incluida
     */
    public function __construct(
        private array $raices,
        private array $semanasPrevias,
        private array $acumulados,
    ) {}

    /** 1 para la primera semana en que se paga la pieza en ese paso, 2 para la segunda... */
    public function numeroDeAvance(?int $piezaId, ?string $qr, int $procesoId, ?int $subprocesoId = null): int
    {
        [$porLinaje, $porQr] = $this->claves($piezaId, $qr, $procesoId, $subprocesoId);

        $semanas = ($this->semanasPrevias[$porLinaje] ?? []) + ($this->semanasPrevias[$porQr] ?? []);

        return count($semanas) + 1;
    }

    /** Porcentaje de la pieza pagado hasta esta semana, con ella incluida. */
    public function porcentajeAcumulado(?int $piezaId, ?string $qr, int $procesoId, ?int $subprocesoId = null): float
    {
        [$porLinaje, $porQr] = $this->claves($piezaId, $qr, $procesoId, $subprocesoId);

        return round((($this->acumulados[$porLinaje] ?? 0) + ($this->acumulados[$porQr] ?? 0)) * 100, 2);
    }

    /**
     * Las mismas dos cubetas que AvanceDeObra: el linaje y el QR, que recoge lo
     * pagado de piezas que ya se borraron del catalogo.
     *
     * @return array{string, string}
     */
    private function claves(?int $piezaId, ?string $qr, int $procesoId, ?int $subprocesoId): array
    {
        $sufijo = '|proceso:'.$procesoId.'|sub:'.($subprocesoId ?? 0);
        $raiz = $piezaId !== null ? ($this->raices[$piezaId] ?? $piezaId) : 0;

        return ['raiz:'.$raiz.$sufijo, 'qr:'.($qr ?? '').$sufijo];
    }
}
