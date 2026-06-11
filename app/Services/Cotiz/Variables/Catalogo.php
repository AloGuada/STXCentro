<?php

namespace App\Services\Cotiz\Variables;

/**
 * Catálogo estático de variables semánticas. Port de `src/lib/variables/catalogo.ts`.
 *
 * Describe, por dominio, qué columnas/medidas se pueden direccionar con la gramática
 *   [total|cuenta].dominio[#instancia].columna[filtro]
 *
 * Es la fuente única para la validación de fórmulas (Validar). `resoluble: false` marca una
 * columna declarada cuyo resolvedor aún no está implementado.
 */
final class Catalogo
{
    public const DOMINIOS = ['tarjeta', 'generadora', 'resumen', 'seccion', 'cuadrilla'];

    private const CORTES = ['tiras', 'raz', 'kg', 'cnx'];

    private const BLOQUES_RESUMEN = ['mo_fab', 'mo_montaje', 'extras', 'totales'];

    public static function dominioExiste(string $dominio): bool
    {
        return in_array($dominio, self::DOMINIOS, true);
    }

    /**
     * Definición de una columna del catálogo, o null si no existe.
     *
     * @return array{operaciones: list<string>, filtros: list<string>, valoresFiltro: array<string, list<string>|'dinamico'>, resoluble: bool}|null
     */
    public static function getColumna(string $dominio, string $columna): ?array
    {
        return self::columnas()[$dominio][$columna] ?? null;
    }

    public static function tieneInstancia(string $dominio): bool
    {
        return in_array($dominio, ['tarjeta', 'generadora', 'seccion', 'cuadrilla'], true);
    }

    /**
     * @return array<string, array<string, array{operaciones: list<string>, filtros: list<string>, valoresFiltro: array<string, list<string>|'dinamico'>, resoluble: bool}>>
     */
    private static function columnas(): array
    {
        return [
            'tarjeta' => [
                'importe' => ['operaciones' => ['total'], 'filtros' => ['cc'], 'valoresFiltro' => ['cc' => 'dinamico'], 'resoluble' => true],
                'kg' => ['operaciones' => ['total'], 'filtros' => [], 'valoresFiltro' => [], 'resoluble' => true],
                'area' => ['operaciones' => ['total'], 'filtros' => [], 'valoresFiltro' => [], 'resoluble' => true],
                'kg_real' => ['operaciones' => ['total'], 'filtros' => ['corte'], 'valoresFiltro' => ['corte' => self::CORTES], 'resoluble' => true],
                'factor' => ['operaciones' => ['valor'], 'filtros' => ['cod'], 'valoresFiltro' => ['cod' => 'dinamico'], 'resoluble' => true],
            ],
            'generadora' => [
                'kg' => ['operaciones' => ['total'], 'filtros' => ['marca'], 'valoresFiltro' => ['marca' => 'dinamico'], 'resoluble' => true],
                'kg_real' => ['operaciones' => ['total'], 'filtros' => ['marca'], 'valoresFiltro' => ['marca' => 'dinamico'], 'resoluble' => true],
            ],
            'resumen' => [
                'importe' => ['operaciones' => ['total'], 'filtros' => ['bloque', 'fila'], 'valoresFiltro' => ['bloque' => self::BLOQUES_RESUMEN, 'fila' => 'dinamico'], 'resoluble' => false],
                'kg' => ['operaciones' => ['total'], 'filtros' => [], 'valoresFiltro' => [], 'resoluble' => false],
                'venta' => ['operaciones' => ['valor'], 'filtros' => [], 'valoresFiltro' => [], 'resoluble' => false],
            ],
            'seccion' => [
                'importe' => ['operaciones' => ['total'], 'filtros' => [], 'valoresFiltro' => [], 'resoluble' => false],
                'dias' => ['operaciones' => ['total'], 'filtros' => ['fase'], 'valoresFiltro' => ['fase' => 'dinamico'], 'resoluble' => false],
                'nomina' => ['operaciones' => ['total'], 'filtros' => [], 'valoresFiltro' => [], 'resoluble' => false],
            ],
            'cuadrilla' => [
                'rendimiento' => ['operaciones' => ['valor'], 'filtros' => [], 'valoresFiltro' => [], 'resoluble' => true],
                'importe' => ['operaciones' => ['total'], 'filtros' => [], 'valoresFiltro' => [], 'resoluble' => false],
            ],
        ];
    }
}
