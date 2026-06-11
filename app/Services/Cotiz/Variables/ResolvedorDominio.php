<?php

namespace App\Services\Cotiz\Variables;

/**
 * Contrato de un resolvedor por dominio. Lanza una excepción en condiciones inválidas
 * (fail-hard): una dirección irresoluble deja la fórmula sin resolver esa pasada y cae a 0.
 */
interface ResolvedorDominio
{
    public function dominio(): string;

    public function resolver(Direccion $dir, ContextoEval $ctx): float;
}
