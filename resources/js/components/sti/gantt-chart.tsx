import { cn } from '@/lib/utils';
import type { StiMantenimiento } from '@/types/models';
import { useMemo } from 'react';

type EquipoInfo = {
    id: number;
    descripcion: string;
    asignado_a: string;
    departamento: string;
};

type GanttChartProps = {
    mantenimientos: StiMantenimiento[];
    mes: string; // formato YYYY-MM
    onItemClick: (id: number) => void;
};

export function GanttChart({ mantenimientos, mes, onItemClick }: GanttChartProps) {
    const { year, month, daysInMonth, equipos, mantenimientosByEquipo } = useMemo(() => {
        const [y, m] = mes.split('-').map(Number);
        const days = new Date(y, m, 0).getDate();

        // Agrupar por equipo
        const equipoMap = new Map<number, EquipoInfo>();
        const mantMap = new Map<number, StiMantenimiento[]>();

        mantenimientos.forEach((m) => {
            if (m.equipo) {
                const asignacion = m.equipo.asignaciones?.[0];
                equipoMap.set(m.equipo.id, {
                    id: m.equipo.id,
                    descripcion: m.equipo.descripcion,
                    asignado_a: asignacion?.empleado ?? '-',
                    departamento: asignacion?.departamento?.descripcion ?? '-',
                });
                const list = mantMap.get(m.equipo.id) || [];
                list.push(m);
                mantMap.set(m.equipo.id, list);
            }
        });

        return {
            year: y,
            month: m,
            daysInMonth: days,
            equipos: Array.from(equipoMap.values()),
            mantenimientosByEquipo: mantMap,
        };
    }, [mantenimientos, mes]);

    const days = Array.from({ length: daysInMonth }, (_, i) => i + 1);

    if (equipos.length === 0) {
        return (
            <div className="rounded-lg border p-8 text-center text-gray-500">
                No hay mantenimientos programados para este mes.
            </div>
        );
    }

    return (
        <div className="overflow-x-auto rounded-lg border">
            <div className="min-w-max">
                {/* Header con días */}
                <div className="flex border-b bg-gray-50 dark:bg-gray-800">
                    <div className="w-48 flex-shrink-0 border-r p-2 font-medium">Equipo</div>
                    <div className="w-36 flex-shrink-0 border-r p-2 text-sm font-medium">Asignado a</div>
                    <div className="w-32 flex-shrink-0 border-r p-2 text-sm font-medium">Departamento</div>
                    <div className="flex">
                        {days.map((day) => {
                            const date = new Date(year, month - 1, day);
                            const isWeekend = date.getDay() === 0 || date.getDay() === 6;
                            return (
                                <div
                                    key={day}
                                    className={cn(
                                        'w-8 flex-shrink-0 border-r p-1 text-center text-xs',
                                        isWeekend && 'bg-gray-100 dark:bg-gray-700'
                                    )}
                                >
                                    {day}
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Filas por equipo */}
                {equipos.map((equipo) => {
                    const mants = mantenimientosByEquipo.get(equipo.id) || [];

                    return (
                        <div key={equipo.id} className="flex border-b last:border-b-0">
                            <div className="w-48 flex-shrink-0 border-r p-2 text-sm">
                                <span className="truncate">{equipo.descripcion}</span>
                            </div>
                            <div className="w-36 flex-shrink-0 border-r p-2 text-xs">{equipo.asignado_a}</div>
                            <div className="w-32 flex-shrink-0 border-r p-2 text-xs">{equipo.departamento}</div>
                            <div className="relative flex h-10">
                                {days.map((day) => {
                                    const date = new Date(year, month - 1, day);
                                    const isWeekend = date.getDay() === 0 || date.getDay() === 6;
                                    return (
                                        <div
                                            key={day}
                                            className={cn(
                                                'w-8 flex-shrink-0 border-r',
                                                isWeekend && 'bg-gray-50 dark:bg-gray-800/50'
                                            )}
                                        />
                                    );
                                })}

                                {/* Marcadores de mantenimiento */}
                                {mants.map((m) => {
                                    const day = parseInt(m.fecha_programada.slice(8, 10), 10);
                                    const left = (day - 1) * 32 + 4; // 32px por día + 4px padding

                                    return (
                                        <button
                                            key={m.id}
                                            type="button"
                                            onClick={() => onItemClick(m.id)}
                                            className={cn(
                                                'absolute top-1 h-8 w-6 rounded text-xs font-bold text-white transition-transform hover:scale-110',
                                                m.status === 'realizado'
                                                    ? 'bg-success'
                                                    : 'bg-warning'
                                            )}
                                            style={{ left: `${left}px` }}
                                            title={`${m.descripcion || 'Mantenimiento'} - ${m.status}`}
                                        >
                                            {m.status === 'realizado' ? '✓' : '•'}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* Leyenda */}
            <div className="flex items-center gap-4 border-t bg-gray-50 p-2 dark:bg-gray-800">
                <div className="flex items-center gap-1 text-xs">
                    <span className="size-4 rounded bg-warning"></span>
                    <span>Pendiente</span>
                </div>
                <div className="flex items-center gap-1 text-xs">
                    <span className="size-4 rounded bg-success"></span>
                    <span>Realizado</span>
                </div>
            </div>
        </div>
    );
}
