<?php

namespace App\Exceptions\Costos;

use App\Models\Costos\ObraRubro;
use RuntimeException;

/**
 * Lanzada cuando se intenta exceder el presupuesto disponible de un
 * obra_rubro y `costos.bloquear_sobregiro` está activo.
 */
class SobregiroPresupuestalException extends RuntimeException
{
    public function __construct(
        public readonly ObraRubro $obraRubro,
        public readonly float $montoIntentado,
        public readonly float $disponible,
    ) {
        parent::__construct(sprintf(
            'Sobregiro presupuestal: el centro de costos "%s" en obra "%s" tiene %s disponible y se intenta cargar %s.',
            $obraRubro->rubro?->descripcion ?? '(centro de costos)',
            $obraRubro->obra?->descripcion ?? '(obra)',
            number_format($disponible, 2),
            number_format($montoIntentado, 2),
        ));
    }
}
