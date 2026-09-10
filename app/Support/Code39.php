<?php

namespace App\Support;

/**
 * Code 39: convierte un folio en las barras que lo representan.
 *
 * Es el mismo alfabeto que `resources/js/components/alm/codigo-barras.tsx`
 * dibuja en pantalla, escrito otra vez en PHP porque el impreso no puede
 * reutilizarlo: dompdf no interpreta SVG —por eso el logo ya se referencia
 * como `<img>`— así que el formato dibuja cada barra como un bloque negro
 * posicionado.
 *
 * Code 39 y no Code 128 porque los folios son cortos y en mayúsculas
 * (`SAL-2608-0007`): lo lee cualquier lector sin configurarlo, que es el que
 * ya está en la caseta leyendo las etiquetas de anaquel.
 */
final class Code39
{
    /**
     * Cada carácter son 9 elementos que alternan barra y espacio empezando por
     * barra: `n` es angosto (1 módulo) y `w` es ancho (3 módulos).
     *
     * @var array<string, string>
     */
    private const PATRONES = [
        '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn',
        '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw',
        '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn',
        'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw', 'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw',
        'E' => 'wnnnwwnnn', 'F' => 'nnwnwwnnn', 'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn',
        'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn', 'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww',
        'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww', 'O' => 'wnnnwnnwn', 'P' => 'nnwnwnnwn',
        'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn', 'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn',
        'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw', 'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw',
        'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn',
        '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn', '$' => 'nwnwnwnnn',
        '/' => 'nwnwnnnwn', '+' => 'nwnnnwnwn', '%' => 'nnnwnwnwn',
        /* Delimitador: abre y cierra todo código Code 39. */
        '*' => 'nwnnwnwnn',
    ];

    /** Lo que Code 39 sabe representar. Fuera de esto, no hay sello. */
    public static function esCodificable(string $valor): bool
    {
        $limpio = mb_strtoupper(trim($valor));

        if ($limpio === '') {
            return false;
        }

        foreach (mb_str_split($limpio) as $caracter) {
            if ($caracter === '*' || ! isset(self::PATRONES[$caracter])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Las barras negras con su posición, todo medido en módulos. Los espacios
     * no se dibujan: son el papel entre barra y barra.
     *
     * Devuelve `null` cuando el valor no es imprimible, para que la plantilla
     * omita el sello en vez de pintar un código que ningún lector acepta.
     *
     * @return array{barras: list<array{x: int, ancho: int}>, modulos: int}|null
     */
    public static function dibujo(string $valor): ?array
    {
        if (! self::esCodificable($valor)) {
            return null;
        }

        $texto = '*'.mb_strtoupper(trim($valor)).'*';
        $caracteres = mb_str_split($texto);
        $ultimo = count($caracteres) - 1;
        $barras = [];
        $x = 0;

        foreach ($caracteres as $indice => $caracter) {
            foreach (str_split(self::PATRONES[$caracter]) as $posicion => $elemento) {
                $ancho = $elemento === 'w' ? 3 : 1;

                // Los elementos pares son barra; los impares, espacio.
                if ($posicion % 2 === 0) {
                    $barras[] = ['x' => $x, 'ancho' => $ancho];
                }

                $x += $ancho;
            }

            // Separación entre caracteres: un módulo de espacio, salvo al final.
            if ($indice < $ultimo) {
                $x++;
            }
        }

        return ['barras' => $barras, 'modulos' => $x];
    }
}
