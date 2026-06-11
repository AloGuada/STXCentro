<?php

namespace App\Services\Cotiz\Variables;

/**
 * Parser de direcciones semánticas. Port de `src/lib/variables/parser.ts`.
 *
 * Gramática: [total|cuenta].dominio[#instancia].columna[clave=valor]. Ancla en los
 * nombres de dominio conocidos para no capturar códigos de factor (identificadores planos
 * como OXIGENO_PLANTA) ni funciones (ceil(, roundup(...)). No valida que la columna exista
 * (eso lo hace Validar).
 */
final class Parser
{
    private const INSTANCIA = '#(?:"(?<instQ>[^"]*)"|\'(?<instS>[^\']*)\'|(?<inst>[A-Za-z0-9_\-]+))';

    private const FILTRO = '\[(?<fkey>[a-z_]+)=(?:"(?<fvalQ>[^"]*)"|\'(?<fvalS>[^\']*)\'|(?<fval>[^\]]+))\]';

    private static function cuerpo(): string
    {
        $doms = implode('|', Catalogo::DOMINIOS);

        return '(?:(?<op>total|cuenta)\.)?(?<dom>'.$doms.')(?:'.self::INSTANCIA.')?\.(?<col>[a-z_][a-z0-9_]*)(?:'.self::FILTRO.')?';
    }

    /**
     * Parsea un token completo. Devuelve null si no es una dirección semántica válida.
     */
    public static function parse(string $token): ?Direccion
    {
        $re = '/^'.self::cuerpo().'$/';
        if (preg_match($re, trim($token), $m) !== 1) {
            return null;
        }
        if (! Catalogo::dominioExiste($m['dom'] ?? '')) {
            return null;
        }

        return self::construir($m, trim($token));
    }

    /**
     * Extrae todas las direcciones presentes en una fórmula, con sus offsets (en bytes).
     *
     * @return list<array{raw: string, start: int, end: int}>
     */
    public static function extraer(string $formula): array
    {
        // No precedida por carácter de identificador/punto/almohadilla (igual que RE_GLOBAL).
        $re = '/(?<![A-Za-z0-9_.#])'.self::cuerpo().'/';
        if (preg_match_all($re, $formula, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
            return [];
        }

        $out = [];
        foreach ($matches as $m) {
            $dom = $m['dom'][0] ?? '';
            if (! Catalogo::dominioExiste($dom)) {
                continue;
            }
            $raw = $m[0][0];
            $start = $m[0][1];
            $out[] = ['raw' => $raw, 'start' => $start, 'end' => $start + strlen($raw)];
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $groups
     */
    private static function construir(array $groups, string $raw): Direccion
    {
        $op = ($groups['op'] ?? '') !== '' ? $groups['op'] : 'valor';
        $dominio = $groups['dom'];

        $instancia = self::primeroNoVacio([$groups['instQ'] ?? '', $groups['instS'] ?? '', $groups['inst'] ?? '']);

        $filtro = null;
        $fkey = $groups['fkey'] ?? '';
        $fval = self::primeroNoVacio([$groups['fvalQ'] ?? '', $groups['fvalS'] ?? '', $groups['fval'] ?? '']);
        if ($fkey !== '' && $fval !== null) {
            $filtro = ['clave' => $fkey, 'valor' => trim($fval)];
        }

        return new Direccion($op, $dominio, $instancia, $groups['col'], $filtro, $raw);
    }

    /**
     * @param  list<string>  $valores
     */
    private static function primeroNoVacio(array $valores): ?string
    {
        foreach ($valores as $valor) {
            if ($valor !== '') {
                return $valor;
            }
        }

        return null;
    }
}
