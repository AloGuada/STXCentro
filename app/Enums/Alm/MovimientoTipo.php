<?php

namespace App\Enums\Alm;

/**
 * Los movimientos que cambian el saldo de un almacén.
 *
 * La devolución no está: es de piezas con número de serie, cierra un resguardo
 * y sólo cambia la custodia. El material a granel que sobra en una obra vuelve
 * al almacén general con una transferencia, porque así es como se mueve saldo
 * entre almacenes.
 *
 * La transferencia son dos casos y no uno porque es un documento en dos tiempos:
 * el origen descarga cuando sale el camión y el destino carga cuando confirma
 * lo que bajó. Entre los dos, el material no es existencia de nadie.
 */
enum MovimientoTipo: string
{
    case Entrada = 'entrada';
    case Salida = 'salida';
    case TransferenciaSalida = 'transferencia_salida';
    case TransferenciaEntrada = 'transferencia_entrada';
    case Ajuste = 'ajuste';
    case Reasignacion = 'reasignacion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::Salida => 'Salida',
            self::TransferenciaSalida => 'Transferencia (salida)',
            self::TransferenciaEntrada => 'Transferencia (entrada)',
            self::Ajuste => 'Ajuste',
            self::Reasignacion => 'Reasignación',
        };
    }

    /**
     * Hacia dónde debe ir la cantidad: 1 suma, -1 resta, 0 acepta ambos.
     *
     * El ledger lo compara contra el signo que le mandaron y rechaza lo que no
     * empate. Es lo que mata la salida capturada en positivo —que sumaría en vez
     * de restar— antes de que llegue a producción.
     */
    public function signo(): int
    {
        return match ($this) {
            self::Entrada, self::TransferenciaEntrada => 1,
            self::Salida, self::TransferenciaSalida => -1,
            self::Ajuste, self::Reasignacion => 0,
        };
    }

    public function carga(): bool
    {
        return $this->signo() === 1;
    }

    public function descarga(): bool
    {
        return $this->signo() === -1;
    }

    /**
     * El ajuste, que lo mismo repone un faltante que baja una merma, y la
     * reasignación, que siempre va en pareja: descarga de una obra y carga a la
     * otra.
     */
    public function admiteAmbosSignos(): bool
    {
        return in_array($this, [self::Ajuste, self::Reasignacion], true);
    }

    /**
     * Cambia de dueño, no de bodega: la pareja de asientos suma cero y el saldo
     * queda igual que antes.
     *
     * Los totales del kardex la excluyen a propósito. Sumarla a «entradas» y
     * «salidas» inflaría los dos lados con material que nunca se movió.
     */
    public function esReasignacion(): bool
    {
        return $this === self::Reasignacion;
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
