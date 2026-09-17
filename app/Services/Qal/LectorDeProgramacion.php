<?php

namespace App\Services\Qal;

/**
 * La lista de marcas que pega producción para programar la semana.
 *
 * Es la regla de `components/qal/avance/marcas.ts`, portada de la aplicación
 * anterior: la pantalla la usa para contar en vivo mientras se pega, pero la
 * que vale es ésta, porque es la que se guarda. Producción programa copiando
 * una columna de su hoja de Excel, y esa columna llega con tabulaciones, comas
 * y cantidades escritas de tres maneras distintas. Si el parseo se equivoca, la
 * semana entera se mide contra un plan que nadie escribió.
 */
class LectorDeProgramacion
{
    /**
     * Las tres formas que salen de pegar de Excel:
     *
     *  - una marca por línea → una pieza
     *  - `PIP-CM1-5 x3` → tres piezas de esa marca
     *  - `PIP-CM1-5 <tab> 3` → lo mismo, cuando se pegan dos columnas
     *
     * El número final de una marca —el `1` de `PIP-CM1-1`— es parte de la marca:
     * la cantidad sólo se separa si va escrita con una `x`. Sin esa regla, media
     * programación se leería como cantidades y el plan saldría inflado. Una
     * marca repetida se suma, y se avisa.
     *
     * @return list<array{marca: string, cantidad: int, repetida: bool}>
     */
    public function marcas(string $texto): array
    {
        $salida = [];
        $posicion = [];

        $apunta = function (string $crudo, int $cantidad) use (&$salida, &$posicion): void {
            $marca = $this->normalizar($crudo);

            if ($marca === '') {
                return;
            }

            if (isset($posicion[$marca])) {
                $salida[$posicion[$marca]]['cantidad'] += $cantidad;
                $salida[$posicion[$marca]]['repetida'] = true;

                return;
            }

            $posicion[$marca] = count($salida);
            $salida[] = ['marca' => $marca, 'cantidad' => $cantidad, 'repetida' => false];
        };

        foreach ($this->lineas($texto) as $linea) {
            $campos = array_values(array_filter(
                array_map('trim', preg_split('/[\t;,]+/u', $linea) ?: []),
                fn (string $campo): bool => $campo !== '',
            ));

            if ($campos === []) {
                continue;
            }

            // «MARCA <tab> 3»: dos columnas y la segunda sólo números.
            if (count($campos) === 2 && preg_match('/^\d+$/', $campos[1]) === 1 && $this->pareceMarca($campos[0])) {
                $apunta($campos[0], max((int) $campos[1], 1));

                continue;
            }

            foreach ($campos as $campo) {
                if (preg_match('/^(.+?)\s*[xX]\s*(\d+)$/u', $campo, $partes) === 1 && $this->pareceMarca($partes[1])) {
                    $apunta($partes[1], max((int) $partes[2], 1));
                } else {
                    $apunta($campo, 1);
                }
            }
        }

        return $salida;
    }

    /**
     * Las bajas, una por línea, como `MARCA: motivo`.
     *
     * Una baja no es un ajuste silencioso del plan: explica por qué la semana
     * trae menos piezas de las programadas. El motivo queda nulo si no se
     * escribió, para que quien guarda pueda negarse. Una marca repetida se
     * queda con su primer motivo.
     *
     * @return list<array{marca: string, motivo: string|null}>
     */
    public function bajas(string $texto): array
    {
        $salida = [];

        foreach ($this->lineas($texto) as $linea) {
            [$crudo, $motivo] = array_pad(explode(':', $linea, 2), 2, '');
            $marca = $this->normalizar($crudo);

            if ($marca === '' || isset($salida[$marca])) {
                continue;
            }

            $motivo = trim($motivo);
            $salida[$marca] = ['marca' => $marca, 'motivo' => $motivo === '' ? null : $motivo];
        }

        return array_values($salida);
    }

    /** Mayúsculas, sin espacios y sin puntuación de cola. */
    public function normalizar(string $texto): string
    {
        $sinEspacios = (string) preg_replace('/\s+/u', '', mb_strtoupper($texto));

        return (string) preg_replace('/[·.]+$/u', '', $sinEspacios);
    }

    /**
     * El tipo de pieza de la marca: las letras del segundo tramo
     * (`PIP-TP2-1` → `TP`). Sólo agrupa el resumen; lo que no encaja se cuenta
     * igual, bajo «—».
     */
    public function tipoDeMarca(string $marca): string
    {
        $tramos = explode('-', mb_strtoupper(trim($marca)));

        if (count($tramos) < 2) {
            return '';
        }

        return preg_match('/^[A-ZÑ]+/u', $tramos[1], $letras) === 1 ? $letras[0] : '';
    }

    /**
     * Basta con una letra y más de un carácter. Es laxo a propósito: rechazar
     * una marca buena por estricto es peor que aceptar una basura que se ve en
     * el resumen.
     */
    private function pareceMarca(string $texto): bool
    {
        return preg_match('/[A-Za-zÑñ]/u', $texto) === 1 && mb_strlen(trim($texto)) > 1;
    }

    /**
     * @return list<string>
     */
    private function lineas(string $texto): array
    {
        return preg_split('/[\r\n]+/u', $texto) ?: [];
    }
}
