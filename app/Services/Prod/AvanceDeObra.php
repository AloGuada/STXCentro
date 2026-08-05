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
     * @param  array<string, float>  $totales  clave de (linaje, proceso) => piezas equivalentes
     */
    public function __construct(
        private array $raices,
        private array $totales,
    ) {}

    /**
     * Fraccion ya pagada o comprometida de esta pieza en este proceso.
     *
     * Suma el bucket de su linaje y el de su QS: el segundo recoge lo pagado de
     * piezas que ya se borraron del catalogo y por eso no tienen linaje.
     */
    public function capturadoDe(Pieza $pieza, int $procesoId): float
    {
        $sufijo = '|proceso:'.$procesoId;

        $porLinaje = $this->totales['raiz:'.($this->raices[$pieza->id] ?? $pieza->id).$sufijo] ?? 0;
        $porQs = $this->totales['qs:'.$pieza->qs.$sufijo] ?? 0;

        return round((float) $porLinaje + (float) $porQs, 4);
    }
}
