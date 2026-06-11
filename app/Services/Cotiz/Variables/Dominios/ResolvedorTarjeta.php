<?php

namespace App\Services\Cotiz\Variables\Dominios;

use App\Models\Cotiz\Tarjeta;
use App\Services\Cotiz\PinturaCalculator;
use App\Services\Cotiz\TarjetaCalculator;
use App\Services\Cotiz\Variables\ContextoEval;
use App\Services\Cotiz\Variables\Direccion;
use App\Services\Cotiz\Variables\ResolvedorDominio;
use Closure;
use RuntimeException;

/**
 * Dominio `tarjeta`. Port de `src/lib/variables/dominios/tarjeta.ts`.
 *
 * - Self (sin #, dentro de la fórmula de un factor de la propia tarjeta): calcula desde los
 *   datos precargados en memoria (sin re-query).
 * - Sin instancia desde un dominio ajeno: Σ sobre todas las tarjetas de la obra (cache M039).
 * - `tarjeta.factor[cod=X]`: lee el mapa de factores en vuelo del contexto.
 * - Instancia concreta (`#nombre`): se carga vía el callback inyectado (lo provee el motor).
 */
final class ResolvedorTarjeta implements ResolvedorDominio
{
    private const CORTE_KEY = ['tiras' => 'TIRAS', 'raz' => 'RAZ', 'kg' => 'KG', 'cnx' => 'CNX'];

    /**
     * @param  (Closure(string, int|null): array<string, mixed>|null)|null  $cargarDatosPorNombre
     */
    public function __construct(
        private readonly PinturaCalculator $pintura,
        private readonly ?Closure $cargarDatosPorNombre = null,
    ) {}

    public function dominio(): string
    {
        return 'tarjeta';
    }

    public function resolver(Direccion $dir, ContextoEval $ctx): float
    {
        if ($dir->columna === 'factor') {
            return $this->valorFactor($dir, $ctx);
        }

        $esSelf = $dir->instancia === null && ($ctx->self['dominio'] ?? null) === 'tarjeta';

        if ($esSelf && ($ctx->precargados['dominio'] ?? null) === 'tarjeta') {
            return $this->medida($dir, $ctx->precargados);
        }

        if ($dir->instancia === null && ($ctx->self['dominio'] ?? null) !== 'tarjeta') {
            return $this->totalTodasTarjetas($dir, $ctx->obraId);
        }

        // Instancia concreta (o self sin precargados): cargar de BD vía callback.
        if ($dir->instancia !== null && $this->cargarDatosPorNombre !== null) {
            $datos = ($this->cargarDatosPorNombre)($dir->instancia, $ctx->obraId);
            if ($datos === null) {
                throw new RuntimeException("tarjeta no encontrada: \"{$dir->instancia}\"");
            }

            return $this->medida($dir, $datos);
        }

        throw new RuntimeException("tarjeta#{$dir->instancia}.{$dir->columna} no resoluble en este contexto");
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function medida(Direccion $dir, array $datos): float
    {
        if ($dir->op !== 'total') {
            throw new RuntimeException("tarjeta.{$dir->columna} solo admite 'total', no '{$dir->op}'");
        }

        $registros = $datos['registros'] ?? [];

        switch ($dir->columna) {
            case 'importe':
                if ($dir->filtro === null) {
                    return array_sum(array_map(
                        fn ($r) => ($r['cantidad'] ?? 0) * ($r['precio_unitario'] ?? 0),
                        $registros,
                    ));
                }
                if ($dir->filtro['clave'] !== 'cc') {
                    throw new RuntimeException("filtro no válido para tarjeta.importe: {$dir->filtro['clave']}");
                }
                $slug = TarjetaCalculator::slugCategoria($dir->filtro['valor']);

                return array_sum(array_map(
                    fn ($r) => TarjetaCalculator::slugCategoria($r['categoria'] ?? null) === $slug
                        ? ($r['cantidad'] ?? 0) * ($r['precio_unitario'] ?? 0)
                        : 0.0,
                    $registros,
                ));

            case 'kg':
                return array_sum(array_map(
                    fn ($r) => strtolower((string) ($r['unidad'] ?? '')) === 'kg' ? ($r['cantidad'] ?? 0) : 0.0,
                    $registros,
                ));

            case 'area':
                $formulas = $datos['pintura_formulas'] ?? [];

                return array_sum(array_map(
                    fn ($r) => $this->pintura->area(
                        $r['tipo_pintura'] ?? 'auto',
                        $r['cantidad'] ?? null,
                        isset($r['peso_lineal']) ? (float) $r['peso_lineal'] : null,
                        $r['descripcion'] ?? null,
                        $formulas,
                    ),
                    $registros,
                ));

            case 'kg_real':
                $k = $datos['kg_por_tipo_corte'] ?? ['TIRAS' => 0, 'RAZ' => 0, 'KG' => 0, 'CNX' => 0];
                if ($dir->filtro === null) {
                    return $k['TIRAS'] + $k['RAZ'] + $k['KG'] + $k['CNX'];
                }
                if ($dir->filtro['clave'] !== 'corte') {
                    throw new RuntimeException("filtro no válido para tarjeta.kg_real: {$dir->filtro['clave']}");
                }
                $key = self::CORTE_KEY[strtolower($dir->filtro['valor'])] ?? null;
                if ($key === null) {
                    throw new RuntimeException("corte desconocido: {$dir->filtro['valor']}");
                }

                return (float) $k[$key];

            default:
                throw new RuntimeException("columna desconocida: tarjeta.{$dir->columna}");
        }
    }

    private function valorFactor(Direccion $dir, ContextoEval $ctx): float
    {
        if ($dir->op !== 'valor') {
            throw new RuntimeException('tarjeta.factor solo admite valor (sin total/cuenta)');
        }
        if ($dir->instancia !== null) {
            throw new RuntimeException('tarjeta.factor solo soporta la tarjeta actual (self)');
        }
        if (($dir->filtro['clave'] ?? null) !== 'cod' || ($dir->filtro['valor'] ?? '') === '') {
            throw new RuntimeException('tarjeta.factor requiere [cod=CÓDIGO]');
        }
        $valor = $ctx->factores[$dir->filtro['valor']] ?? null;
        if ($valor === null) {
            throw new RuntimeException("factor no resuelto aún: {$dir->filtro['valor']}");
        }

        return (float) $valor;
    }

    private function totalTodasTarjetas(Direccion $dir, ?int $obraId): float
    {
        if ($dir->op !== 'total' || $dir->filtro !== null) {
            throw new RuntimeException("total.tarjeta.{$dir->columna} (todas) no admite filtro/operación: usa #instancia");
        }
        if ($dir->columna === 'importe' || $dir->columna === 'kg') {
            $col = $dir->columna === 'importe' ? 'importe_materiales' : 'kilos_reales';

            return (float) Tarjeta::query()
                ->where('obra_id', $obraId)
                ->sum($col);
        }

        throw new RuntimeException("total.tarjeta.{$dir->columna} sobre todas las instancias no está soportado (usa #instancia)");
    }
}
