<?php

namespace App\Services\Cotiz\Variables;

/**
 * Dirección semántica parseada: [total|cuenta].dominio[#instancia].columna[clave=valor].
 * Port del tipo `Direccion` de `src/lib/variables/parser.ts`.
 */
final class Direccion
{
    /**
     * @param  'total'|'cuenta'|'valor'  $op
     * @param  array{clave: string, valor: string}|null  $filtro
     */
    public function __construct(
        public readonly string $op,
        public readonly string $dominio,
        public readonly ?string $instancia,
        public readonly string $columna,
        public readonly ?array $filtro,
        public readonly string $raw,
    ) {}
}
