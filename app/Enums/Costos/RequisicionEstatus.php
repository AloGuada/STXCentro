<?php

namespace App\Enums\Costos;

use App\Enums\Contracts\HasStateTransitions;

enum RequisicionEstatus: string implements HasStateTransitions
{
    case Borrador = 'borrador';
    case PendienteAprobacionInterna = 'pendiente_aprobacion_interno';
    case AprobadaInterna = 'aprobada_interna';
    case PendienteAprobacion = 'pendiente_aprobacion';
    case Aprobada = 'aprobada';
    case Rechazada = 'rechazada';
    case Liberada = 'liberada';
    case Cancelada = 'cancelada';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Borrador => [self::PendienteAprobacionInterna, self::Cancelada],
            // Etapa interna: solo el control gerencial (aprueba → interna, rechaza → borrador).
            self::PendienteAprobacionInterna => [self::AprobadaInterna, self::Borrador, self::Rechazada, self::Cancelada],
            // Con la aprobación interna dada: se manda a la aprobación formal (cadena
            // de firmas) o, en dedazo, se convierte directo a OC (→ Aprobada).
            self::AprobadaInterna => [self::PendienteAprobacion, self::Aprobada, self::Rechazada, self::Cancelada],
            self::PendienteAprobacion => [self::Aprobada, self::Rechazada, self::Cancelada],
            self::Aprobada => [self::Liberada, self::Cancelada],
            self::Rechazada => [self::Borrador, self::Cancelada],
            self::Liberada,
            self::Cancelada => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::PendienteAprobacionInterna => 'Pendiente de aprobación interna',
            self::AprobadaInterna => 'Aprobada interna',
            self::PendienteAprobacion => 'Pendiente de aprobación',
            self::Aprobada => 'Aprobada',
            self::Rechazada => 'Rechazada',
            self::Liberada => 'Liberada',
            self::Cancelada => 'Cancelada',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
