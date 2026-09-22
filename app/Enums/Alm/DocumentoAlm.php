<?php

namespace App\Enums\Alm;

/**
 * Los documentos de Almacén que llevan firmas al pie de su formato impreso.
 *
 * No hay flujo de aprobación en el módulo: nadie autoriza nada en pantalla.
 * Esto es sólo qué rayas trae la hoja y en qué orden, que es como se autoriza
 * hoy. Entrada y devolución se configuran aunque todavía no tengan formato,
 * para no volver a esta pantalla cuando se hagan.
 */
enum DocumentoAlm: string
{
    case Pedido = 'pedido';
    case Entrada = 'entrada';
    case Salida = 'salida';
    case Transferencia = 'transferencia';
    case Devolucion = 'devolucion';
    case Ajuste = 'ajuste';
    case Prestamo = 'prestamo';
    case Conteo = 'conteo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pedido => 'Pedido',
            self::Entrada => 'Entrada',
            self::Salida => 'Salida',
            self::Transferencia => 'Transferencia',
            self::Devolucion => 'Devolución',
            self::Ajuste => 'Ajuste',
            self::Prestamo => 'Préstamo',
            self::Conteo => 'Conteo',
        };
    }

    /** Si hoy existe el formato imprimible. Lo que se configure en los que no, espera. */
    public function tieneFormato(): bool
    {
        return ! in_array($this, [self::Entrada, self::Devolucion], true);
    }

    /** Qué se está pidiendo con la firma, cuando no es evidente. */
    public function ayuda(): ?string
    {
        return match ($this) {
            self::Ajuste => 'El único movimiento que cambia la existencia sin un documento que lo respalde.',
            self::Prestamo => 'Aparte del resguardo que firma quien se la lleva: esto es quién autoriza que salga del almacén.',
            self::Devolucion => 'Cierra el resguardo de una pieza. No mueve existencia, pero deja constancia de cómo volvió.',
            self::Entrada => 'Todavía no tiene formato impreso: lo que configures aquí espera a que exista.',
            default => null,
        };
    }

    /**
     * Con qué arranca un almacén que nunca se configuró: los rótulos que hasta
     * hoy venían escritos a mano en cada formato, con la raya en blanco.
     *
     * @return list<array{rotulo: string, nombre: string|null}>
     */
    public function firmasPorDefecto(): array
    {
        return match ($this) {
            self::Pedido => [
                ['rotulo' => 'Solicitó', 'nombre' => null],
                ['rotulo' => 'Autorizó', 'nombre' => null],
                ['rotulo' => 'Surtió - Almacén', 'nombre' => null],
            ],
            self::Salida => [
                ['rotulo' => 'Entregó - Almacén', 'nombre' => null],
                ['rotulo' => 'Recibió de conformidad', 'nombre' => null],
            ],
            self::Transferencia => [
                ['rotulo' => 'Autorizó', 'nombre' => null],
                ['rotulo' => 'Despachó - Origen', 'nombre' => null],
                ['rotulo' => 'Recibió - Destino', 'nombre' => null],
            ],
            self::Ajuste => [
                ['rotulo' => 'Contó', 'nombre' => null],
                ['rotulo' => 'Autorizó', 'nombre' => null],
                ['rotulo' => 'Jefe de almacén', 'nombre' => null],
            ],
            self::Prestamo => [
                ['rotulo' => 'Entregó - Almacén', 'nombre' => null],
                ['rotulo' => 'Recibió en resguardo', 'nombre' => null],
                ['rotulo' => 'Autorizó', 'nombre' => null],
            ],
            self::Conteo => [
                ['rotulo' => 'Contó', 'nombre' => null],
                ['rotulo' => 'Revisó', 'nombre' => null],
            ],
            self::Entrada => [
                ['rotulo' => 'Recibió - Almacén', 'nombre' => null],
                ['rotulo' => 'Revisó', 'nombre' => null],
            ],
            self::Devolucion => [
                ['rotulo' => 'Devolvió', 'nombre' => null],
                ['rotulo' => 'Recibió - Almacén', 'nombre' => null],
            ],
        };
    }
}
