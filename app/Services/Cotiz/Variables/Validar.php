<?php

namespace App\Services\Cotiz\Variables;

use App\Services\Cotiz\FormulaEvaluator;

/**
 * Validación de fórmulas que pueden contener direcciones semánticas. Port de
 * `src/lib/variables/validar.ts`. Valida cada dirección contra el catálogo (dominio/columna/
 * operación/filtro existen y la columna está implementada) más la sintaxis del evaluador.
 */
final class Validar
{
    /**
     * Valida una dirección contra el catálogo. Null si es válida, o el mensaje de error.
     */
    public static function direccion(Direccion $dir): ?string
    {
        if (! Catalogo::dominioExiste($dir->dominio)) {
            return "dominio desconocido: {$dir->dominio}";
        }
        $col = Catalogo::getColumna($dir->dominio, $dir->columna);
        if ($col === null) {
            return "columna desconocida: {$dir->dominio}.{$dir->columna}";
        }
        if (! in_array($dir->op, $col['operaciones'], true)) {
            $ops = implode(', ', array_map(fn ($o) => $o === 'valor' ? '(valor)' : $o, $col['operaciones']));

            return "{$dir->dominio}.{$dir->columna} admite: {$ops}";
        }
        if ($dir->instancia !== null && ! Catalogo::tieneInstancia($dir->dominio)) {
            return "{$dir->dominio} no tiene instancias (#)";
        }
        if ($dir->filtro !== null) {
            if (! in_array($dir->filtro['clave'], $col['filtros'], true)) {
                return "filtro no válido para {$dir->dominio}.{$dir->columna}: {$dir->filtro['clave']}";
            }
            $vals = $col['valoresFiltro'][$dir->filtro['clave']] ?? null;
            if (is_array($vals) && ! in_array(mb_strtolower($dir->filtro['valor']), $vals, true)) {
                return "valor de {$dir->filtro['clave']} no válido: \"{$dir->filtro['valor']}\" (use: ".implode(' | ', $vals).')';
            }
        }
        if (! $col['resoluble']) {
            return "{$dir->dominio}.{$dir->columna} aún no está implementado";
        }

        return null;
    }

    /**
     * Valida una fórmula completa: direcciones semánticas + sintaxis del evaluador.
     */
    public static function formula(string $formula, FormulaEvaluator $evaluator): ?string
    {
        if (trim($formula) === '') {
            return null;
        }

        $plana = $formula;
        $ocurrencias = Parser::extraer($formula);
        usort($ocurrencias, fn ($a, $b) => $b['start'] <=> $a['start']);

        foreach ($ocurrencias as $oc) {
            $dir = Parser::parse($oc['raw']);
            if ($dir !== null) {
                $err = self::direccion($dir);
                if ($err !== null) {
                    return $err;
                }
            }
            // Sustituye la dirección por '1' para validar la aritmética restante.
            $plana = substr($plana, 0, $oc['start']).'1'.substr($plana, $oc['end']);
        }

        return $evaluator->validar($plana);
    }
}
