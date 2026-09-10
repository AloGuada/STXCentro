<?php

namespace App\Services\Prod;

use App\Models\Prod\Pieza;

/**
 * Avance ya comprometido de las piezas de una obra, resuelto de una sola vez
 * para no consultar la base pieza por pieza.
 */
readonly class AvanceDeObra
{
    /**
     * @param  array<int, int>  $raices  piezaId => id de la pieza raiz de su linaje
     * @param  array<string, float>  $totales  clave de (linaje, proceso, subproceso) => piezas equivalentes
     */
    public function __construct(
        private array $raices,
        private array $totales,
    ) {}

    /**
     * Fraccion ya pagada o comprometida de esta pieza en este proceso, y en el
     * subproceso si el grupo paga por pasos.
     *
     * Suma el bucket de su linaje y el de su QR: el segundo recoge lo pagado de
     * piezas que ya se borraron del catalogo y por eso no tienen linaje.
     */
    public function capturadoDe(Pieza $pieza, int $procesoId, ?int $subprocesoId = null): float
    {
        $sufijo = '|proceso:'.$procesoId.'|sub:'.($subprocesoId ?? 0);

        $porLinaje = $this->totales['raiz:'.($this->raices[$pieza->id] ?? $pieza->id).$sufijo] ?? 0;
        $porQr = $this->totales['qr:'.$pieza->qr.$sufijo] ?? 0;

        return round((float) $porLinaje + (float) $porQr, 4);
    }
}
