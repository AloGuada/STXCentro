<?php

namespace App\Enums\Alm;

/**
 * Por qué el saldo no cuadraba. Es lo único que justifica mover el inventario
 * sin un documento de material detrás, así que se pide siempre.
 */
enum AjusteMotivo: string
{
    case ConteoFisico = 'conteo_fisico';
    case Merma = 'merma';
    case ErrorCaptura = 'error_captura';
    case CargaInicial = 'carga_inicial';
    case Otro = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ConteoFisico => 'Conteo físico',
            self::Merma => 'Merma',
            self::ErrorCaptura => 'Error de captura',
            self::CargaInicial => 'Carga inicial',
            self::Otro => 'Otro',
        };
    }

    /**
     * Los que no se teclean a mano: `conteo_fisico` lo genera el cierre de una
     * hoja de conteo cíclico. Se deja fuera del desplegable para que nadie
     * capture a mano lo que debería nacer de contar.
     */
    public function loGeneraElSistema(): bool
    {
        return $this === self::ConteoFisico;
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Los que se pueden elegir en el alta.
     *
     * @return list<self>
     */
    public static function capturables(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $motivo): bool => ! $motivo->loGeneraElSistema(),
        ));
    }
}
