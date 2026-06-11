<?php

namespace App\Services\Cotiz\Variables;

use Closure;
use RuntimeException;

/**
 * Registro de resolvedores por dominio + resolución raíz con memoización y detección de
 * ciclos. Port de `src/lib/variables/registry.ts`.
 *
 * El orden topológico cross-dominio emerge de la recursión perezosa (un resolvedor que
 * necesite otro dominio llama a `resolver`, que resuelve antes de devolver).
 */
final class Registry
{
    /** @var array<string, ResolvedorDominio> */
    private array $resolvedores = [];

    public function __construct(ResolvedorDominio ...$resolvedores)
    {
        foreach ($resolvedores as $resolvedor) {
            $this->resolvedores[$resolvedor->dominio()] = $resolvedor;
        }
    }

    public function resolver(Direccion $dir, ContextoEval $ctx): float
    {
        $key = self::claveCanonica($dir, $ctx);
        if (array_key_exists($key, $ctx->memo)) {
            return $ctx->memo[$key];
        }
        if (isset($ctx->pila[$key])) {
            throw new RuntimeException("Ciclo de dependencias en variables: {$key}");
        }

        $resolvedor = $this->resolvedores[$dir->dominio] ?? null;
        if ($resolvedor === null) {
            throw new RuntimeException("dominio sin resolvedor implementado: {$dir->dominio}");
        }

        $ctx->pila[$key] = true;
        try {
            $valor = $resolvedor->resolver($dir, $ctx);
            $ctx->memo[$key] = $valor;

            return $valor;
        } finally {
            unset($ctx->pila[$key]);
        }
    }

    /**
     * Función de expansión lista para pasar a FactorResolver, atada a un contexto. Inyecta
     * los valores de factores en vuelo en el contexto para resolver `tarjeta.factor[cod=X]`.
     *
     * @return Closure(string, array<string, float>): array{formula: string, vars: array<string, float>}
     */
    public function expandirConContexto(ContextoEval $ctx): Closure
    {
        return function (string $formula, array $valoresFactores) use ($ctx): array {
            $ctx->factores = $valoresFactores;

            return Expandir::formula($formula, fn (Direccion $dir): float => $this->resolver($dir, $ctx));
        };
    }

    /** Clave canónica para memo/ciclos: normaliza el "yo" implícito a la instancia concreta. */
    private static function claveCanonica(Direccion $dir, ContextoEval $ctx): string
    {
        if ($dir->instancia !== null) {
            $inst = '#'.$dir->instancia;
        } elseif (($ctx->self['dominio'] ?? null) === $dir->dominio) {
            $inst = '@self:'.$ctx->self['instancia'];
        } else {
            $inst = '@todas';
        }
        $f = $dir->filtro !== null ? "[{$dir->filtro['clave']}={$dir->filtro['valor']}]" : '';

        return "{$dir->op}.{$dir->dominio}{$inst}.{$dir->columna}{$f}";
    }
}
