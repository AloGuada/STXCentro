<?php

namespace App\Enums;

enum TipoDocumento: string
{
    case ManualOperativo = 'manual_operativo';
    case FormatoProceso = 'formato_proceso';
    case Protocolo = 'protocolo';
    case Politica = 'politica';
    case InstructivoTrabajo = 'instructivo_trabajo';
    case ProcedimientoEspecifico = 'procedimiento_especifico';
    case ProcedimientoGeneral = 'procedimiento_general';
    case PlanCalidad = 'plan_calidad';
    case ManualGestion = 'manual_gestion';

    public function label(): string
    {
        return match ($this) {
            self::ManualOperativo => 'Manual de uso operativo',
            self::FormatoProceso => 'Formatos de procesos',
            self::Protocolo => 'Protocolo',
            self::Politica => 'Políticas',
            self::InstructivoTrabajo => 'Instructivos de trabajo',
            self::ProcedimientoEspecifico => 'Procedimiento específico',
            self::ProcedimientoGeneral => 'Procedimiento general',
            self::PlanCalidad => 'Planes de calidad',
            self::ManualGestion => 'Manual de gestión de calidad',
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::ManualOperativo => 1,
            self::FormatoProceso => 2,
            self::Protocolo => 3,
            self::Politica => 4,
            self::InstructivoTrabajo => 5,
            self::ProcedimientoEspecifico => 6,
            self::ProcedimientoGeneral => 7,
            self::PlanCalidad => 8,
            self::ManualGestion => 9,
        };
    }

    /**
     * Get options for dropdowns (value => label).
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->sortBy(fn (self $case) => $case->order())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /**
     * Get options with numbered labels for dropdowns (value => "N.- Label").
     *
     * @return array<string, string>
     */
    public static function optionsWithNumbers(): array
    {
        return collect(self::cases())
            ->sortBy(fn (self $case) => $case->order())
            ->mapWithKeys(fn (self $case) => [
                $case->value => $case->order().'.- '.$case->label(),
            ])
            ->all();
    }
}
