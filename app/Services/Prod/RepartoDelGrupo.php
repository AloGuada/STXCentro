<?php

namespace App\Services\Prod;

use App\Models\Prod\ConfiguracionProd;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoTrabajo;
use Illuminate\Support\Collection;

/**
 * Reparte lo que gana un grupo en la semana entre sus trabajadores.
 *
 * El pago tiene dos capas:
 *  1. **Sueldo base garantizado** = días pagados × salario mínimo diario. Se
 *     cobra aunque el destajo no alcance, y las faltas lo recortan (por eso la
 *     asistencia es obligatoria para cerrar).
 *  2. **Excedente del destajo** = total del grupo − suma de las bases. Sólo si
 *     es positivo, se prorratea según el `valor` de la categoría de cada quien.
 *
 * Si el excedente sale negativo cada trabajador se queda con su base y no hay
 * reparto: la diferencia la absorbe la empresa.
 */
class RepartoDelGrupo
{
    public function __construct(private AsistenciaDelDestajo $asistencia) {}

    /**
     * @return array{
     *     salario_diario: float,
     *     total_bases: float,
     *     excedente: float,
     *     empleados: list<array{
     *         nombre: string,
     *         no_empleado: string|null,
     *         dias_pagados: int,
     *         categoria_nombre: string|null,
     *         categoria_valor: int,
     *         salario_diario: float,
     *         sueldo_base: float,
     *         monto_destajo: float,
     *         porcentaje: float,
     *         monto_asignado: float
     *     }>
     * }
     */
    public function calcular(Destajo $destajo, ?GrupoTrabajo $grupo, float $totalGrupo): array
    {
        $salarioDiario = (float) ConfiguracionProd::actual()->salario_minimo_diario;
        $diasPagados = $this->asistencia->diasPagadosPorEmpleado($destajo);

        $empleados = ($grupo?->empleados ?? collect())
            ->map(function ($empleado) use ($diasPagados, $salarioDiario) {
                $dias = (int) ($diasPagados[$empleado->id] ?? 0);

                return [
                    'empleado' => $empleado,
                    'dias' => $dias,
                    'base' => round($dias * $salarioDiario, 2),
                    'peso' => (int) ($empleado->categoria?->valor ?? 0),
                ];
            });

        $totalBases = round((float) $empleados->sum('base'), 2);
        $excedente = round(max(0, $totalGrupo - $totalBases), 2);
        $sumaPesos = (int) $empleados->sum('peso');

        return [
            'salario_diario' => $salarioDiario,
            'total_bases' => $totalBases,
            'excedente' => $excedente,
            'empleados' => $this->repartirExcedente($empleados, $excedente, $sumaPesos, $salarioDiario),
        ];
    }

    /**
     * @param  Collection<int, array{empleado: mixed, dias: int, base: float, peso: int}>  $empleados
     * @return list<array<string, mixed>>
     */
    private function repartirExcedente(Collection $empleados, float $excedente, int $sumaPesos, float $salarioDiario): array
    {
        return $empleados->map(function (array $fila) use ($excedente, $sumaPesos, $salarioDiario) {
            $empleado = $fila['empleado'];

            $proporcion = $sumaPesos > 0 ? $fila['peso'] / $sumaPesos : 0.0;
            $montoDestajo = round($excedente * $proporcion, 2);

            return [
                'nombre' => $empleado->nombre,
                'no_empleado' => $empleado->no_empleado,
                'dias_pagados' => $fila['dias'],
                'categoria_nombre' => $empleado->categoria?->nombre,
                'categoria_valor' => $fila['peso'],
                'salario_diario' => $salarioDiario,
                'sueldo_base' => $fila['base'],
                'monto_destajo' => $montoDestajo,
                // Se conserva como referencia de cuánto del excedente le tocó.
                'porcentaje' => round($proporcion * 100, 2),
                'monto_asignado' => round($fila['base'] + $montoDestajo, 2),
            ];
        })->values()->all();
    }
}
