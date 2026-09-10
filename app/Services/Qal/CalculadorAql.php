<?php

namespace App\Services\Qal;

use App\Enums\Qal\NivelAql;
use App\Enums\Qal\VeredictoLote;

/**
 * El muestreo AQL 10 con el que se acepta o se rechaza un lote de piezas
 * iguales.
 *
 * La tabla es norma de la empresa y se porta tal cual de la captura anterior:
 * por tamaño de lote y nivel, cuántas piezas se miran, cuántas rechazadas
 * todavía aceptan el lote y a partir de cuántas se rechaza. El formulario la
 * adelanta para que el inspector vea el veredicto mientras captura
 * (`components/qal/captura/reglas.ts`); lo que se guarda sale de aquí.
 */
class CalculadorAql
{
    /**
     * Cada tramo: el tope de piezas del lote y, por nivel, [muestra, aceptación,
     * rechazo]. Por encima del último tope rige el último tramo.
     *
     * @var list<array{0: int, 1: array<string, array{0: int, 1: int, 2: int}>}>
     */
    private const TABLA = [
        [8, ['I' => [2, 0, 2], 'II' => [2, 1, 2], 'III' => [3, 1, 2]]],
        [15, ['I' => [2, 0, 2], 'II' => [3, 1, 2], 'III' => [5, 1, 2]]],
        [25, ['I' => [2, 0, 2], 'II' => [5, 1, 2], 'III' => [8, 1, 2]]],
        [50, ['I' => [2, 0, 2], 'II' => [8, 2, 3], 'III' => [13, 2, 3]]],
        [90, ['I' => [2, 0, 1], 'II' => [13, 3, 4], 'III' => [20, 3, 4]]],
        [150, ['I' => [3, 1, 3], 'II' => [20, 5, 6], 'III' => [32, 5, 6]]],
        [280, ['I' => [5, 1, 4], 'II' => [32, 7, 8], 'III' => [50, 8, 9]]],
        [500, ['I' => [8, 2, 5], 'II' => [50, 10, 11], 'III' => [80, 12, 13]]],
    ];

    /**
     * Cuántas piezas mirar y con cuántas rechazadas se decide el lote.
     *
     * `tope` recorta la muestra a lo que trae la entrega: un sublote de
     * accesorios nunca inspecciona más unidades de las que llegaron.
     *
     * @return array{muestra: int, aceptacion: int, rechazo: int}|null
     */
    public function plan(int $lote, NivelAql $nivel, ?int $tope = null): ?array
    {
        if ($lote < 1) {
            return null;
        }

        $tramo = collect(self::TABLA)->first(fn (array $fila): bool => $lote <= $fila[0])
            ?? self::TABLA[array_key_last(self::TABLA)];

        [$muestra, $aceptacion, $rechazo] = $tramo[1][$nivel->value];

        return [
            'muestra' => $tope !== null ? min($muestra, $tope) : $muestra,
            'aceptacion' => $aceptacion,
            'rechazo' => $rechazo,
        ];
    }

    /**
     * El veredicto con lo mirado hasta ahora. Nulo mientras la muestra no está
     * completa y las rechazadas todavía no alcanzan el rechazo: un lote en
     * curso no está aceptado.
     *
     * @param  array{muestra: int, aceptacion: int, rechazo: int}  $plan
     */
    public function veredicto(array $plan, int $conformes, int $rechazadas): ?VeredictoLote
    {
        if ($rechazadas >= $plan['rechazo']) {
            return VeredictoLote::Rechazado;
        }

        if ($conformes + $rechazadas < $plan['muestra']) {
            return null;
        }

        return $rechazadas <= $plan['aceptacion'] ? VeredictoLote::Aceptado : VeredictoLote::Rechazado;
    }
}
