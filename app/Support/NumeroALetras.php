<?php

namespace App\Support;

/**
 * Convierte un monto a su representación en letras al estilo de facturación
 * mexicana, p. ej. 1234.50 -> "MIL DOSCIENTOS TREINTA Y CUATRO PESOS 50/100 M.N.".
 */
class NumeroALetras
{
    /** @var list<string> 0..20 */
    private const UNIDADES = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISEIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE'];

    /** @var array<int, string> 21..29 */
    private const VEINTI = [1 => 'VEINTIUN', 'VEINTIDOS', 'VEINTITRES', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISEIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];

    /** @var list<string> decenas a partir de 30 */
    private const DECENAS = ['', '', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];

    /** @var list<string> centenas */
    private const CENTENAS = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

    public static function convertir(float $monto, string $moneda = 'mxn'): string
    {
        $entero = (int) floor(round($monto, 2));
        $centavos = (int) round((round($monto, 2) - $entero) * 100);
        $centavosStr = str_pad((string) $centavos, 2, '0', STR_PAD_LEFT);

        $letras = $entero === 0 ? 'CERO' : self::entero($entero);

        $sufijo = match (strtolower($moneda)) {
            'usd' => 'DÓLARES',
            'eur' => 'EUROS',
            default => 'PESOS',
        };
        $mn = strtolower($moneda) === 'mxn' ? ' M.N.' : '';

        return "{$letras} {$sufijo} {$centavosStr}/100{$mn}";
    }

    private static function entero(int $n): string
    {
        $millones = intdiv($n, 1000000);
        $miles = intdiv($n % 1000000, 1000);
        $cientos = $n % 1000;

        $texto = '';

        if ($millones > 0) {
            $texto .= $millones === 1 ? 'UN MILLON ' : self::tresDigitos($millones).' MILLONES ';
        }

        if ($miles > 0) {
            $texto .= $miles === 1 ? 'MIL ' : self::tresDigitos($miles).' MIL ';
        }

        if ($cientos > 0) {
            $texto .= self::tresDigitos($cientos);
        }

        return trim($texto);
    }

    private static function tresDigitos(int $n): string
    {
        if ($n === 100) {
            return 'CIEN';
        }

        $centena = intdiv($n, 100);
        $resto = $n % 100;

        $texto = $centena > 0 ? self::CENTENAS[$centena].' ' : '';
        $texto .= self::dosDigitos($resto);

        return trim($texto);
    }

    private static function dosDigitos(int $n): string
    {
        if ($n <= 20) {
            return self::UNIDADES[$n];
        }

        if ($n < 30) {
            return self::VEINTI[$n - 20];
        }

        $decena = intdiv($n, 10);
        $unidad = $n % 10;

        $texto = self::DECENAS[$decena];
        if ($unidad > 0) {
            $texto .= ' Y '.self::UNIDADES[$unidad];
        }

        return $texto;
    }
}
