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
     * Los nombres que el documento ya sabe, para imprimirlos arriba de la raya
     * en vez de dejarla en blanco. La llave es el atributo o la relación del
     * modelo; el valor, cómo se lee en la pantalla.
     *
     * @return array<string, string>
     */
    public function fuentes(): array
    {
        return match ($this) {
            self::Pedido => ['solicitante' => 'Quien lo pidió', 'aprobador' => 'Quien lo autorizó'],
            self::Salida => ['entregador' => 'Quien entregó', 'recibe_nombre' => 'Quien recibió'],
            self::Transferencia => ['autorizador' => 'Quien autorizó', 'enviador' => 'Quien despachó', 'receptor' => 'Quien recibió'],
            self::Ajuste => ['autorizador' => 'Quien autorizó'],
            self::Prestamo => ['creador' => 'Quien entregó', 'responsable' => 'Quien lo resguarda', 'autorizador' => 'Quien autorizó'],
            self::Conteo => ['responsable' => 'Quien contó'],
            self::Entrada, self::Devolucion => [],
        };
    }

    /**
     * Con qué arranca un almacén que nunca se configuró. Es lo que hasta hoy
     * venía escrito a mano en cada formato.
     *
     * @return list<array{rotulo: string, fuente: string|null}>
     */
    public function firmasPorDefecto(): array
    {
        return match ($this) {
            self::Pedido => [
                ['rotulo' => 'Solicitó', 'fuente' => 'solicitante'],
                ['rotulo' => 'Autorizó', 'fuente' => 'aprobador'],
                ['rotulo' => 'Surtió - Almacén', 'fuente' => null],
            ],
            self::Salida => [
                ['rotulo' => 'Entregó - Almacén', 'fuente' => 'entregador'],
                ['rotulo' => 'Recibió de conformidad', 'fuente' => 'recibe_nombre'],
            ],
            self::Transferencia => [
                ['rotulo' => 'Autorizó', 'fuente' => 'autorizador'],
                ['rotulo' => 'Despachó - Origen', 'fuente' => 'enviador'],
                ['rotulo' => 'Recibió - Destino', 'fuente' => 'receptor'],
            ],
            self::Ajuste => [
                ['rotulo' => 'Contó', 'fuente' => null],
                ['rotulo' => 'Autorizó', 'fuente' => 'autorizador'],
                ['rotulo' => 'Jefe de almacén', 'fuente' => null],
            ],
            self::Prestamo => [
                ['rotulo' => 'Entregó - Almacén', 'fuente' => 'creador'],
                ['rotulo' => 'Recibió en resguardo', 'fuente' => 'responsable'],
                ['rotulo' => 'Autorizó', 'fuente' => 'autorizador'],
            ],
            self::Conteo => [
                ['rotulo' => 'Contó', 'fuente' => 'responsable'],
                ['rotulo' => 'Revisó', 'fuente' => null],
            ],
            self::Entrada => [
                ['rotulo' => 'Recibió - Almacén', 'fuente' => null],
                ['rotulo' => 'Revisó', 'fuente' => null],
            ],
            self::Devolucion => [
                ['rotulo' => 'Devolvió', 'fuente' => null],
                ['rotulo' => 'Recibió - Almacén', 'fuente' => null],
            ],
        };
    }
}
