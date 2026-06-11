<?php

namespace App\Services\Cotiz\Variables\Dominios;

use App\Models\Cotiz\Cuadrilla;
use App\Services\Cotiz\Variables\ContextoEval;
use App\Services\Cotiz\Variables\Direccion;
use App\Services\Cotiz\Variables\ResolvedorDominio;
use RuntimeException;

/**
 * Dominio `cuadrilla` (catálogo global): `rendimiento` (valor). Port de
 * `src/lib/variables/dominios/cuadrilla.ts`. `importe` no está implementado.
 */
final class ResolvedorCuadrilla implements ResolvedorDominio
{
    public function dominio(): string
    {
        return 'cuadrilla';
    }

    public function resolver(Direccion $dir, ContextoEval $ctx): float
    {
        if ($dir->columna === 'importe') {
            throw new RuntimeException('cuadrilla.importe no está implementado (falta vínculo cuadrilla↔tarjeta)');
        }
        if ($dir->columna !== 'rendimiento') {
            throw new RuntimeException("columna desconocida: cuadrilla.{$dir->columna}");
        }
        if ($dir->op !== 'valor') {
            throw new RuntimeException('cuadrilla.rendimiento es un valor (sin total/cuenta)');
        }
        if ($dir->instancia === null) {
            throw new RuntimeException('cuadrilla.rendimiento requiere #<código>');
        }

        $cuadrilla = Cuadrilla::query()->where('codigo', $dir->instancia)->first();
        if ($cuadrilla === null) {
            throw new RuntimeException("cuadrilla no encontrada: \"{$dir->instancia}\"");
        }

        return (float) ($cuadrilla->rendimiento ?? 0);
    }
}
