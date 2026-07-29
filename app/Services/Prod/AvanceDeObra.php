<?php

namespace App\Services\Prod;

use App\Models\Concepto;

/**
 * Avance ya comprometido de las piezas de una obra, resuelto de una sola vez
 * para no consultar la base pieza por pieza.
 */
readonly class AvanceDeObra
{
    /**
     * @param  array<int, int>  $raices  conceptoId => id de la pieza raiz de su linaje
     * @param  array<string, float>  $totales  clave de linaje => piezas equivalentes
     */
    public function __construct(
        private array $raices,
        private array $totales,
    ) {}

    /**
     * Piezas equivalentes ya pagadas o comprometidas de esta pieza.
     *
     * Suma el bucket de su linaje y el de su marca: el segundo recoge lo pagado
     * de piezas que ya se borraron del catalogo y por eso no tienen linaje.
     */
    public function capturadoDe(Concepto $concepto): float
    {
        $porLinaje = $this->totales['raiz:'.($this->raices[$concepto->id] ?? $concepto->id)] ?? 0;
        $porMarca = $this->totales['marca:'.$concepto->marca] ?? 0;

        return round((float) $porLinaje + (float) $porMarca, 4);
    }
}
