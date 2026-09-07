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
 *     asistencia es obligatoria para cerrar). Los días vienen con el séptimo
 *     día ya prorrateado, y valen cero si la semana trae algún "no aplica".
 *  2. **Excedente del destajo** = total del grupo − suma de las bases. Sólo si
 *     es positivo, se prorratea por `valor` de la categoría × días trabajados
 *     (corrección 2026-09-08 contra la nómina real: dos oficiales iguales con
 *     7 y 5.83 días no se llevan lo mismo). Quien no pisó la línea en toda la
 *     semana —falta o incapacidad de lunes a sábado— pesa cero.
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
     *         dias_pagados: float,
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
        $diasTrabajados = $this->asistencia->diasTrabajadosPorEmpleado($destajo);
        $ausentes = $this->asistencia->ausentesTodaLaSemana($destajo);

        $empleados = ($grupo?->empleados ?? collect())
            ->map(function ($empleado) use ($diasPagados, $diasTrabajados, $ausentes, $salarioDiario) {
                $dias = (float) ($diasPagados[$empleado->id] ?? 0);
                $valor = (int) ($empleado->categoria?->valor ?? 0);

                // Sin asistencia capturada se asume la semana completa, igual
                // que la cuadricula (celda no tocada = asistencia).
                $trabajados = (float) ($diasTrabajados[$empleado->id] ?? AsistenciaDelDestajo::FACTOR_SEPTIMO_DIA * 6);

                return [
                    'empleado' => $empleado,
                    'dias' => $dias,
                    'base' => round($dias * $salarioDiario, 2),
                    'valor' => $valor,
                    // Peso en el reparto: categoria x dias trabajados. Quien
                    // falto o estuvo incapacitado toda la semana pesa cero.
                    'peso' => ($ausentes[$empleado->id] ?? false) ? 0.0 : $valor * $trabajados,
                ];
            });

        $totalBases = round((float) $empleados->sum('base'), 2);
        $excedente = round(max(0, $totalGrupo - $totalBases), 2);
        $sumaPesos = (float) $empleados->sum('peso');

        return [
            'salario_diario' => $salarioDiario,
            'total_bases' => $totalBases,
            'excedente' => $excedente,
            'empleados' => $this->repartirExcedente($empleados, $excedente, $sumaPesos, $salarioDiario),
        ];
    }

    /**
     * @param  Collection<int, array{empleado: mixed, dias: float, base: float, valor: int, peso: float}>  $empleados
     * @return list<array<string, mixed>>
     */
    private function repartirExcedente(Collection $empleados, float $excedente, float $sumaPesos, float $salarioDiario): array
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
                'categoria_valor' => $fila['valor'],
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
