<?php

namespace App\Services\Qal\Formatos;

/**
 * Cómo se escribe una respuesta en la hoja, con la convención del papel:
 * A aceptado, D con defecto, N/A no aplica, — sin registro.
 *
 * «Limpia» es la regla del dosier: una pieza liberada no se entrega con una D
 * al lado —el defecto se corrigió antes de liberar—, así que ahí la D pasa a
 * A. Lo que nunca hace es inventar: un N/A sigue siendo N/A y un criterio sin
 * registrar sigue saliendo «—».
 */
final class Celda
{
    /**
     * @param  array{resultado: string|null, valor: string|null}|null  $punto
     * @return array{texto: string, clase: string}
     */
    public static function punto(?array $punto, bool $limpia = false): array
    {
        if ($punto === null) {
            return self::sinRegistro();
        }

        return match ($punto['resultado']) {
            'ok' => self::aceptado(),
            'no_ok' => $limpia ? self::aceptado() : self::defecto(),
            'no_aplica' => ['texto' => 'N/A', 'clase' => ''],
            default => $punto['valor'] === null || $punto['valor'] === '' ? self::sinRegistro() : ['texto' => $punto['valor'], 'clase' => ''],
        };
    }

    /**
     * El resultado de la pieza. En armado y vestido «pendiente» es salir bien:
     * la pieza pasa a soldado, no se queda esperando nada.
     *
     * @return array{texto: string, clase: string}
     */
    public static function estatus(string $estatus, bool $armado = false): array
    {
        return match (true) {
            $estatus === 'liberado' => ['texto' => 'Liberado', 'clase' => 'a-ok'],
            $estatus === 'rechazado' => ['texto' => 'Rechazado', 'clase' => 'a-def'],
            $armado => ['texto' => 'Aceptado', 'clase' => 'a-ok'],
            default => ['texto' => 'Pendiente', 'clase' => 'a-pen'],
        };
    }

    /** @return array{texto: string, clase: string} */
    public static function aceptado(): array
    {
        return ['texto' => 'A', 'clase' => 'a-ok'];
    }

    /** @return array{texto: string, clase: string} */
    public static function defecto(string $texto = 'D'): array
    {
        return ['texto' => $texto, 'clase' => 'a-def'];
    }

    /** @return array{texto: string, clase: string} */
    public static function sinRegistro(): array
    {
        return ['texto' => '—', 'clase' => 'nulo'];
    }
}
