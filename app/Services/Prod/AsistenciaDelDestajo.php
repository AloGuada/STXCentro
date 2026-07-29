<?php

namespace App\Services\Prod;

use App\Models\Prod\Asistencia;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\PagoExtra;
use App\Models\Prod\Registro;
use Illuminate\Support\Collection;

/**
 * Asistencia de un destajo: qué grupos participan, qué días cubre la semana y
 * a quién le falta captura.
 *
 * La asistencia es obligatoria porque parte del pago del trabajador va a
 * salario base y depende de los días efectivamente trabajados, así que el
 * destajo no se puede cerrar con huecos.
 */
class AsistenciaDelDestajo
{
    /**
     * Días del periodo del destajo, con su etiqueta corta para la tabla.
     *
     * @return array<int, array{fecha: string, label: string}>
     */
    public function dias(Destajo $destajo): array
    {
        $diasSemana = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

        $dias = [];
        $cursor = $destajo->fecha_inicio->copy();

        while ($cursor->lte($destajo->fecha_fin)) {
            $dias[] = [
                'fecha' => $cursor->format('Y-m-d'),
                'label' => $diasSemana[$cursor->dayOfWeekIso - 1].' '.$cursor->format('d/m'),
            ];
            $cursor = $cursor->addDay();
        }

        return $dias;
    }

    /**
     * Grupos que participan en el destajo (por producción, pagos extra o
     * liquidaciones ya generadas), con sus empleados.
     *
     * @return Collection<int, GrupoTrabajo>
     */
    public function gruposParticipantes(Destajo $destajo): Collection
    {
        $grupoIds = Registro::query()
            ->whereBetween('fecha', [$destajo->fecha_inicio, $destajo->fecha_fin])
            ->distinct()
            ->pluck('grupo_trabajo_id')
            ->merge(PagoExtra::where('destajo_id', $destajo->id)->distinct()->pluck('grupo_trabajo_id'))
            ->merge($destajo->liquidaciones()->pluck('grupo_trabajo_id'))
            ->unique()
            ->values();

        return GrupoTrabajo::query()
            ->with('empleados')
            ->whereIn('id', $grupoIds)
            ->orderBy('descripcion')
            ->get();
    }

    /**
     * Marcas guardadas, indexadas por "empleadoId:fecha" para pintarlas en la
     * cuadrícula sin buscar fila por fila.
     *
     * @return array<string, string>
     */
    public function marcas(Destajo $destajo): array
    {
        return Asistencia::query()
            ->where('destajo_id', $destajo->id)
            ->get(['grupo_empleado_id', 'fecha', 'estado'])
            ->mapWithKeys(fn (Asistencia $a) => [
                $a->grupo_empleado_id.':'.$a->fecha->format('Y-m-d') => $a->estado->value,
            ])
            ->all();
    }

    /**
     * Grupos con empleados a los que les falta algún día por capturar.
     *
     * @return Collection<int, array{grupo: string, empleados: array<int, string>}>
     */
    public function faltantes(Destajo $destajo): Collection
    {
        $dias = collect($this->dias($destajo))->pluck('fecha');
        $marcas = $this->marcas($destajo);

        return $this->gruposParticipantes($destajo)
            ->map(function (GrupoTrabajo $grupo) use ($dias, $marcas) {
                $incompletos = $grupo->empleados
                    ->filter(fn ($empleado) => $dias->contains(
                        fn (string $fecha) => ! isset($marcas[$empleado->id.':'.$fecha])
                    ))
                    ->pluck('nombre')
                    ->values()
                    ->all();

                return $incompletos === []
                    ? null
                    : ['grupo' => $grupo->descripcion, 'empleados' => $incompletos];
            })
            ->filter()
            ->values();
    }

    /** ¿La semana tiene la asistencia completa de todos los participantes? */
    public function estaCompleta(Destajo $destajo): bool
    {
        return $this->faltantes($destajo)->isEmpty();
    }

    /**
     * Días que se le pagan a cada empleado en la semana: asistencia y
     * vacaciones cuentan, falta y "no aplica" no. Base del sueldo garantizado.
     *
     * @return array<int, int>
     */
    public function diasPagadosPorEmpleado(Destajo $destajo): array
    {
        return Asistencia::query()
            ->where('destajo_id', $destajo->id)
            ->get(['grupo_empleado_id', 'estado'])
            ->filter(fn (Asistencia $a) => $a->estado->cuentaComoPagado())
            ->countBy('grupo_empleado_id')
            ->all();
    }
}
