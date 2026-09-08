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
     * La semana paga siete días repartidos en seis de trabajo (el séptimo día
     * de descanso se prorratea), así que cada día cubierto vale 7/6.
     */
    public const FACTOR_SEPTIMO_DIA = 7 / 6;

    /**
     * Días capturables del destajo, con su etiqueta corta para la tabla. El
     * domingo queda fuera: es el día de descanso y se paga prorrateado en los
     * otros seis, no se marca asistencia.
     *
     * @return array<int, array{fecha: string, label: string}>
     */
    public function dias(Destajo $destajo): array
    {
        $diasSemana = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

        $dias = [];
        $cursor = $destajo->fecha_inicio->copy();

        while ($cursor->lte($destajo->fecha_fin)) {
            if ($cursor->dayOfWeekIso !== 7) {
                $dias[] = [
                    'fecha' => $cursor->format('Y-m-d'),
                    'label' => $diasSemana[$cursor->dayOfWeekIso - 1].' '.$cursor->format('d/m'),
                ];
            }

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
        return GrupoTrabajo::query()
            ->with('empleados')
            ->whereIn('id', $this->idsParticipantes($destajo))
            ->orderBy('descripcion')
            ->get();
    }

    /**
     * @return Collection<int, int>
     */
    private function idsParticipantes(Destajo $destajo): Collection
    {
        return Registro::query()
            ->whereBetween('fecha', [$destajo->fecha_inicio, $destajo->fecha_fin])
            ->distinct()
            ->pluck('grupo_trabajo_id')
            ->merge(PagoExtra::where('destajo_id', $destajo->id)->distinct()->pluck('grupo_trabajo_id'))
            ->merge($destajo->liquidaciones()->pluck('grupo_trabajo_id'))
            ->unique()
            ->values();
    }

    /**
     * Grupos que se pueden capturar en la cuadrícula: todos los activos con
     * gente, participen o no. Un grupo puede haber trabajado sin que su
     * producción esté capturada todavía —o haber estado parado— y aun así hay
     * que registrarle la asistencia.
     *
     * Se agregan los participantes aunque estén inactivos, para no dejar sin
     * captura a un grupo que sí generó producción esta semana.
     *
     * @return Collection<int, GrupoTrabajo>
     */
    public function gruposCapturables(Destajo $destajo): Collection
    {
        $participantes = $this->idsParticipantes($destajo);

        return GrupoTrabajo::query()
            ->with('empleados')
            ->where(fn ($q) => $q->where('activo', true)->orWhereIn('id', $participantes))
            ->has('empleados')
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
     * Días que se le pagan a cada empleado en la semana, ya con el séptimo día
     * prorrateado: seis días cubiertos dan siete pagados. Asistencia,
     * vacaciones e incapacidad cuentan; la falta no (y de paso se lleva su
     * parte del séptimo día).
     *
     * Un solo "no aplica" en la semana deja al trabajador en cero: esa semana
     * no cobra sueldo base, sólo el destajo que le toque.
     *
     * @return array<int, float>
     */
    public function diasPagadosPorEmpleado(Destajo $destajo): array
    {
        return $this->marcasPorEmpleado($destajo)
            ->map(function (Collection $marcas): float {
                if ($marcas->contains(fn (Asistencia $a) => $a->estado->anulaSueldoBase())) {
                    return 0.0;
                }

                return $this->diasCubiertos($marcas);
            })
            ->all();
    }

    /**
     * Días trabajados de cada empleado, ya con el séptimo día prorrateado,
     * SIN el candado de "no aplica". Es lo que pesa en el reparto del
     * excedente: quien tiene "no aplica" no cobra base, pero sí trabajó y le
     * toca destajo según los días que estuvo.
     *
     * @return array<int, float>
     */
    public function diasTrabajadosPorEmpleado(Destajo $destajo): array
    {
        return $this->marcasPorEmpleado($destajo)
            ->map(fn (Collection $marcas): float => $this->diasCubiertos($marcas))
            ->all();
    }

    /**
     * @param  Collection<int, Asistencia>  $marcas
     */
    private function diasCubiertos(Collection $marcas): float
    {
        $cubiertos = $marcas->sum(fn (Asistencia $a) => $a->estado->valorEnDias());

        // Sin redondear: 5 días son 5.8333... y a $300 diarios eso da
        // $1,750 exactos. Redondear aquí se comería un centavo.
        return $cubiertos * self::FACTOR_SEPTIMO_DIA;
    }

    /**
     * Las marcas de la semana por empleado, sin el domingo: ya va prorrateado
     * en los otros seis días aunque haya quedado capturado de antes.
     *
     * @return Collection<int, Collection<int, Asistencia>>
     */
    private function marcasPorEmpleado(Destajo $destajo): Collection
    {
        return Asistencia::query()
            ->where('destajo_id', $destajo->id)
            ->get(['grupo_empleado_id', 'fecha', 'estado'])
            ->reject(fn (Asistencia $a) => $a->fecha->dayOfWeekIso === 7)
            ->groupBy('grupo_empleado_id');
    }

    /**
     * Empleados que esa semana no pisaron la línea: todos sus días marcados
     * son falta o incapacidad. No aportaron al destajo del grupo y por eso no
     * les toca parte del excedente; el sueldo base ya les salió en cero por
     * los días. Quien tiene "no aplica" sí participa: cobra sólo destajo.
     *
     * @return array<int, bool>
     */
    public function ausentesTodaLaSemana(Destajo $destajo): array
    {
        return $this->marcasPorEmpleado($destajo)
            ->map(fn (Collection $marcas): bool => $marcas->every(fn (Asistencia $a) => $a->estado->esAusencia()))
            ->all();
    }
}
