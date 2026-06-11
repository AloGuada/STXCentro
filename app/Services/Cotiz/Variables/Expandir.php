<?php

namespace App\Services\Cotiz\Variables;

use Closure;

/**
 * Pre-expansión de direcciones semánticas a identificadores seguros (`__vN`) para el
 * evaluador. Port de `src/lib/variables/expandir.ts`.
 *
 * El evaluador no acepta `.`, `#`, `[`, `]` en identificadores: cada dirección se sustituye
 * por `__vN` y su valor se inyecta en el contexto como una variable plana más. Cada dirección
 * única se resuelve UNA vez (las repetidas reusan el mismo `__vN`).
 */
final class Expandir
{
    /**
     * @param  Closure(Direccion): float  $resolver
     * @return array{formula: string, vars: array<string, float>}
     */
    public static function formula(string $formula, Closure $resolver): array
    {
        $ocurrencias = Parser::extraer($formula);
        if ($ocurrencias === []) {
            return ['formula' => $formula, 'vars' => []];
        }

        $vars = [];
        $idPorRaw = [];
        $formulaSegura = $formula;

        // De derecha a izquierda para no invalidar los offsets restantes.
        usort($ocurrencias, fn ($a, $b) => $b['start'] <=> $a['start']);

        foreach ($ocurrencias as $oc) {
            $id = $idPorRaw[$oc['raw']] ?? null;
            if ($id === null) {
                $dir = Parser::parse($oc['raw']);
                if ($dir === null) {
                    continue; // no debería ocurrir: extraer ya validó el dominio
                }
                $id = '__v'.count($idPorRaw);
                $idPorRaw[$oc['raw']] = $id;
                $vars[$id] = $resolver($dir);
            }
            $formulaSegura = substr($formulaSegura, 0, $oc['start']).$id.substr($formulaSegura, $oc['end']);
        }

        return ['formula' => $formulaSegura, 'vars' => $vars];
    }
}
