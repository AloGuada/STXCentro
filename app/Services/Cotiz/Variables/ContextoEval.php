<?php

namespace App\Services\Cotiz\Variables;

/**
 * Contexto de una evaluación de variables. Port de `ContextoEval` de `tipos.ts`.
 * Mutable: `memo`/`pila` se actualizan durante la resolución; `factores` lo inyecta
 * el expansor en cada pasada para resolver `tarjeta.factor[cod=X]`.
 */
final class ContextoEval
{
    /** @var array<string, float>|null  Valores de factores ya resueltos en la evaluación en curso. */
    public ?array $factores = null;

    /** @var array<string, float>  Memo entre direcciones de UNA evaluación (clave canónica → valor). */
    public array $memo = [];

    /** @var array<string, true>  Pila de visitados para detectar ciclos cross-dominio. */
    public array $pila = [];

    /**
     * @param  array{dominio: string, instancia: string|int}|null  $self  Instancia "yo" (define el self cuando una dirección omite #).
     * @param  array<string, mixed>|null  $precargados  Datos en memoria de la instancia self.
     */
    public function __construct(
        public readonly ?int $obraId,
        public readonly ?array $self = null,
        public readonly ?array $precargados = null,
    ) {}
}
