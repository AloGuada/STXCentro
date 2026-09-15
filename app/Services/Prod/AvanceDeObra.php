<?php

namespace App\Services\Prod;

use App\Models\Prod\Pieza;

/**
 * Avance ya comprometido de las piezas de una obra, resuelto de una sola vez
 * para no consultar la base pieza por pieza.
 *
 * Lleva dos cuentas que se leen juntas:
 *  - por **pieza** (linaje o QR), que es la que numera parcialidades y sabe
 *    cuál QR se soldó;
 *  - por **modelo** (marca + lote), que es contra la que se paga. El QR de una
 *    pieza cambia con su orden de trabajo, así que la única identidad estable
 *    para saber cuánto va y cuánto falta es el modelo y su cantidad.
 */
readonly class AvanceDeObra
{
    /**
     * @param  array<int, int>  $raices  piezaId => id de la pieza raiz de su linaje
     * @param  array<string, float>  $totales  clave de (linaje, proceso, subproceso) => piezas equivalentes
     * @param  array<string, float>  $porModelo  clave de (modelo, proceso, subproceso) => piezas equivalentes
     * @param  array<string, int>  $cantidades  clave de modelo => piezas que pide el catálogo vigente
     * @param  array<int, string>  $modeloDeConcepto  conceptoId => clave de modelo, para todas las versiones
     */
    public function __construct(
        private array $raices,
        private array $totales,
        private array $porModelo = [],
        private array $cantidades = [],
        private array $modeloDeConcepto = [],
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

    /**
     * Piezas equivalentes ya pagadas o comprometidas del modelo en ese proceso,
     * sumando todas sus piezas de todas las órdenes y versiones, incluidas las
     * que ya se desactivaron porque su QR cambió.
     */
    public function capturadoDeModelo(string $claveModelo, int $procesoId, ?int $subprocesoId = null): float
    {
        return round((float) ($this->porModelo[self::claveDeModelo($claveModelo, $procesoId, $subprocesoId)] ?? 0), 4);
    }

    /** Cuántas piezas pide el catálogo vigente para el modelo; 0 si ya no está. */
    public function cantidadDeModelo(string $claveModelo): int
    {
        return $this->cantidades[$claveModelo] ?? 0;
    }

    /** El modelo al que pertenece la pieza, resuelto sin ir a la base. */
    public function modeloDe(Pieza $pieza): ?string
    {
        return $this->modeloDeConcepto[(int) $pieza->concepto_id] ?? null;
    }

    public static function claveDeModelo(string $claveModelo, int $procesoId, ?int $subprocesoId = null): string
    {
        return 'modelo:'.$claveModelo.'|proceso:'.$procesoId.'|sub:'.($subprocesoId ?? 0);
    }
}
