<?php

namespace App\Services\Qal;

/**
 * Las pocas funciones de estadística que usa el tablero.
 *
 * Son las del tablero anterior, portadas tal cual: la descriptiva, el p del
 * chi-cuadrado —por la gamma incompleta— y la normal acumulada de la prueba de
 * tendencia. El proyecto no tiene librería de estadística y no hace falta una
 * para esto.
 */
class Estadistica
{
    /**
     * Media, mediana y σ muestral (n − 1). El coeficiente de variación es la σ
     * en % de la media: la vuelve comparable entre unidades distintas.
     *
     * @param  list<float|int>  $valores
     * @return array{n: int, media: float, mediana: float, sigma: float, cv: float|null, min: float, max: float}|null
     */
    public static function describir(array $valores): ?array
    {
        $n = count($valores);

        if ($n === 0) {
            return null;
        }

        sort($valores);
        $media = array_sum($valores) / $n;
        $mediana = $n % 2 === 1 ? $valores[intdiv($n, 2)] : ($valores[$n / 2 - 1] + $valores[$n / 2]) / 2;
        $sigma = $n > 1
            ? sqrt(array_sum(array_map(fn (float|int $valor): float => ($valor - $media) ** 2, $valores)) / ($n - 1))
            : 0.0;

        return [
            'n' => $n,
            'media' => (float) $media,
            'mediana' => (float) $mediana,
            'sigma' => (float) $sigma,
            'cv' => abs($media) > 0 ? $sigma / $media * 100 : null,
            'min' => (float) $valores[0],
            'max' => (float) $valores[$n - 1],
        ];
    }

    /** La probabilidad de un chi-cuadrado igual o mayor con esos grados de libertad. */
    public static function pChiCuadrado(float $chi2, int $gl): float
    {
        if ($gl <= 0) {
            return 1.0;
        }

        return max(0.0, 1 - self::gammaInferior($gl / 2, $chi2 / 2));
    }

    /** La normal estándar acumulada (Abramowitz y Stegun, 26.2.17). */
    public static function normalAcumulada(float $z): float
    {
        $t = 1 / (1 + 0.2316419 * abs($z));
        $densidad = 0.3989423 * exp(-$z * $z / 2);
        $cola = $densidad * $t * (0.3193815 + $t * (-0.3565638 + $t * (1.781478 + $t * (-1.821256 + $t * 1.330274))));

        return $z > 0 ? 1 - $cola : $cola;
    }

    /**
     * La probabilidad exacta, a dos colas, de ver tantos éxitos en tantos
     * ensayos si la probabilidad fuera la de la base. Con pocas piezas es la
     * prueba honesta: la aproximación normal se rompe justo ahí.
     */
    public static function pBinomialDosColas(int $exitos, int $ensayos, float $probabilidad): float
    {
        $abajo = 0.0;
        $arriba = 0.0;

        for ($i = 0; $i <= $exitos; $i++) {
            $abajo += self::binomial($i, $ensayos, $probabilidad);
        }

        for ($i = $exitos; $i <= $ensayos; $i++) {
            $arriba += self::binomial($i, $ensayos, $probabilidad);
        }

        return min(1.0, 2 * min($abajo, $arriba));
    }

    /** La probabilidad de exactamente k éxitos en n ensayos. */
    private static function binomial(int $exitos, int $ensayos, float $probabilidad): float
    {
        if ($probabilidad <= 0) {
            return $exitos === 0 ? 1.0 : 0.0;
        }

        if ($probabilidad >= 1) {
            return $exitos === $ensayos ? 1.0 : 0.0;
        }

        return exp(
            self::lnGamma($ensayos + 1) - self::lnGamma($exitos + 1) - self::lnGamma($ensayos - $exitos + 1)
            + $exitos * log($probabilidad) + ($ensayos - $exitos) * log(1 - $probabilidad),
        );
    }

    /**
     * La gamma incompleta inferior regularizada P(a, x): por su serie cuando
     * x < a + 1 y por fracción continua cuando no, que es donde cada una
     * converge rápido.
     */
    private static function gammaInferior(float $a, float $x): float
    {
        if ($x <= 0) {
            return 0.0;
        }

        if ($x < $a + 1) {
            $ap = $a;
            $suma = 1 / $a;
            $termino = 1 / $a;

            for ($i = 0; $i < 300; $i++) {
                $ap++;
                $termino *= $x / $ap;
                $suma += $termino;

                if (abs($termino) < abs($suma) * 1e-14) {
                    break;
                }
            }

            return $suma * exp(-$x + $a * log($x) - self::lnGamma($a));
        }

        $b = $x + 1 - $a;
        $c = 1e30;
        $d = 1 / $b;
        $h = $d;

        for ($i = 1; $i < 300; $i++) {
            $an = -$i * ($i - $a);
            $b += 2;
            $d = $an * $d + $b;

            if (abs($d) < 1e-30) {
                $d = 1e-30;
            }

            $c = $b + $an / $c;

            if (abs($c) < 1e-30) {
                $c = 1e-30;
            }

            $d = 1 / $d;
            $delta = $d * $c;
            $h *= $delta;

            if (abs($delta - 1) < 1e-14) {
                break;
            }
        }

        return 1 - exp(-$x + $a * log($x) - self::lnGamma($a)) * $h;
    }

    /** El logaritmo de la función gamma, por la aproximación de Lanczos. */
    private static function lnGamma(float $x): float
    {
        $coeficientes = [
            0.99999999999980993, 676.5203681218851, -1259.1392167224028, 771.32342877765313,
            -176.61502916214059, 12.507343278686905, -0.13857109526572012, 9.9843695780195716e-6,
            1.5056327351493116e-7,
        ];

        if ($x < 0.5) {
            return log(M_PI / sin(M_PI * $x)) - self::lnGamma(1 - $x);
        }

        $x -= 1;
        $a = $coeficientes[0];
        $t = $x + 7.5;

        for ($i = 1; $i < 9; $i++) {
            $a += $coeficientes[$i] / ($x + $i);
        }

        return 0.5 * log(2 * M_PI) + ($x + 0.5) * log($t) - $t + log($a);
    }
}
