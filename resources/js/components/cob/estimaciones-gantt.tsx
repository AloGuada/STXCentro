import { calcularDatosProyecto } from '@/components/cob/calculos';
import { EstadoBadge } from '@/components/cob/estado-badge';
import { formatearMXN } from '@/components/cob/money-display';
import type { CobEstimacion, CobEstimacionEstado, CobEstimacionEstadoHistorial, Obra } from '@/types/models';
import { Link } from '@inertiajs/react';
import { useMemo } from 'react';

const ROW_HEIGHT = 36;
const BAR_HEIGHT = 22;
const WEEK_COL_PX = 70;

const ESTADO_COLORS: Record<CobEstimacionEstado, string> = {
    pendiente: 'bg-gray-300 text-gray-800',
    generada: 'bg-yellow-400 text-black',
    ingresada: 'bg-amber-200 text-black',
    revisada: 'bg-cyan-400 text-black',
    autorizada: 'bg-blue-400 text-white',
    facturada: 'bg-pink-400 text-white',
    pago_parcial: 'bg-orange-400 text-black',
    pagado: 'bg-green-400 text-black',
};

const ESTADO_LABELS: Record<CobEstimacionEstado, string> = {
    pendiente: 'Pendiente',
    generada: 'Generada',
    ingresada: 'Ingresada',
    revisada: 'Revisada',
    autorizada: 'Autorizada',
    facturada: 'Facturada',
    pago_parcial: 'Pago parcial',
    pagado: 'Pagado',
};

function daysBetween(a: Date, b: Date): number {
    return Math.round((b.getTime() - a.getTime()) / 86400000);
}

function formatShort(d: Date): string {
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
}

function formatFull(d: Date): string {
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
}

function getWeekNumber(d: Date): number {
    const temp = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    temp.setUTCDate(temp.getUTCDate() + 4 - (temp.getUTCDay() || 7));
    const yearStart = new Date(Date.UTC(temp.getUTCFullYear(), 0, 1));
    return Math.ceil(((temp.getTime() - yearStart.getTime()) / 86400000 + 1) / 7);
}

type StatePeriod = {
    estado: CobEstimacionEstado;
    start: Date;
    end: Date;
};

function buildStatePeriods(historial: CobEstimacionEstadoHistorial[]): StatePeriod[] {
    if (historial.length === 0) return [];

    const sorted = [...historial].sort(
        (a, b) => new Date(a.fecha_cambio).getTime() - new Date(b.fecha_cambio).getTime(),
    );

    const periods: StatePeriod[] = [];

    for (let i = 0; i < sorted.length; i++) {
        const entry = sorted[i];
        const nextEntry = sorted[i + 1];
        const start = new Date(entry.fecha_cambio);
        const end = nextEntry ? new Date(nextEntry.fecha_cambio) : new Date();

        periods.push({
            estado: entry.estado_nuevo as CobEstimacionEstado,
            start,
            end,
        });
    }

    return periods;
}

type GanttEstimacionRow = {
    estimacion: CobEstimacion;
    periods: StatePeriod[];
};

type GanttObraGroup = {
    obra: Obra;
    rows: GanttEstimacionRow[];
    totalEstimaciones: number;
    totalEstimado: number;
    porEstimar: number;
};

function buildGanttData(obras: Obra[], year?: number): {
    groups: GanttObraGroup[];
    minDate: Date;
    maxDate: Date;
    totalDays: number;
} {
    const yearStart = year ? new Date(year, 0, 1) : null;
    const yearEnd = year ? new Date(year, 11, 31) : null;

    const allDates: number[] = [];

    const groups: GanttObraGroup[] = obras
        .map((obra) => {
            const allEstimaciones = obra.estimaciones ?? [];
            const rows = allEstimaciones
                .filter((e) => e.historial && e.historial.length > 0)
                .map((estimacion) => {
                    let periods = buildStatePeriods(estimacion.historial!);

                    if (yearStart && yearEnd) {
                        periods = periods
                            .filter((p) => p.start <= yearEnd && p.end >= yearStart)
                            .map((p) => ({
                                ...p,
                                start: p.start < yearStart ? yearStart : p.start,
                                end: p.end > yearEnd ? yearEnd : p.end,
                            }));
                    }

                    for (const p of periods) {
                        allDates.push(p.start.getTime(), p.end.getTime());
                    }
                    return { estimacion, periods };
                })
                .filter((r) => r.periods.length > 0)
                .sort((a, b) => a.estimacion.numero_estimacion - b.estimacion.numero_estimacion);

            const datos = calcularDatosProyecto(obra);
            const totalEstimado = allEstimaciones.reduce((sum, e) => sum + Number(e.monto_estimado), 0);

            return {
                obra,
                rows,
                totalEstimaciones: allEstimaciones.length,
                totalEstimado,
                porEstimar: datos.presupuestoFinal - totalEstimado,
            };
        })
        .filter((g) => g.rows.length > 0);

    if (allDates.length === 0) {
        const now = new Date();
        return { groups: [], minDate: now, maxDate: now, totalDays: 1 };
    }

    const minDate = yearStart ?? new Date(Math.min(...allDates) - 7 * 86400000);
    const maxDate = yearEnd ?? new Date(Math.max(...allDates) + 7 * 86400000);
    const totalDays = Math.max(daysBetween(minDate, maxDate), 1);

    return { groups, minDate, maxDate, totalDays };
}

function pct(valor: number, total: number): string {
    if (total <= 0) return '0.00%';
    return ((valor / total) * 100).toFixed(2) + '%';
}

type Props = {
    obras: Obra[];
    year?: number;
};

export function EstimacionesGantt({ obras, year }: Props) {
    const { groups, minDate, totalDays } = useMemo(() => buildGanttData(obras, year), [obras, year]);

    const weekMarkers = useMemo(() => {
        const markers: { date: Date; pct: number; week: number }[] = [];
        const d = new Date(minDate);
        const dow = d.getDay();
        d.setDate(d.getDate() + ((8 - dow) % 7));
        while (d.getTime() <= minDate.getTime() + totalDays * 86400000) {
            markers.push({
                date: new Date(d),
                pct: (daysBetween(minDate, d) / totalDays) * 100,
                week: getWeekNumber(d),
            });
            d.setDate(d.getDate() + 7);
        }
        return markers;
    }, [minDate, totalDays]);

    const monthMarkers = useMemo(() => {
        const markers: { label: string; pct: number; widthPct: number }[] = [];
        const end = new Date(minDate.getTime() + totalDays * 86400000);
        const d = new Date(minDate.getFullYear(), minDate.getMonth(), 1);

        while (d <= end) {
            const monthStart = new Date(Math.max(d.getTime(), minDate.getTime()));
            const nextMonth = new Date(d.getFullYear(), d.getMonth() + 1, 1);
            const monthEnd = new Date(Math.min(nextMonth.getTime(), end.getTime()));
            const leftPct = (daysBetween(minDate, monthStart) / totalDays) * 100;
            const widthPct = (daysBetween(monthStart, monthEnd) / totalDays) * 100;

            markers.push({
                label: d.toLocaleDateString('es-MX', { month: 'short' }),
                pct: leftPct,
                widthPct,
            });

            d.setMonth(d.getMonth() + 1);
        }

        return markers;
    }, [minDate, totalDays]);

    const timelineWidth = Math.max(weekMarkers.length * WEEK_COL_PX, 400);

    if (groups.length === 0) {
        return (
            <div className="text-base-content/60 py-8 text-center">
                No hay estimaciones con historial de estados
            </div>
        );
    }

    const stickyCell = 'sticky z-20 bg-base-100';

    return (
        <div className="flex flex-col gap-3">
            {/* Leyenda */}
            <div className="flex flex-wrap gap-2 text-xs">
                {(Object.entries(ESTADO_COLORS) as [CobEstimacionEstado, string][]).map(([estado, color]) => (
                    <div key={estado} className={`rounded px-2 py-1 ${color}`}>
                        {ESTADO_LABELS[estado]}
                    </div>
                ))}
            </div>

            <div className="overflow-x-auto rounded-box border border-base-300">
                <table className="table table-zebra whitespace-nowrap text-xs">
                    <thead className="sticky top-0 z-30 bg-base-100">
                        <tr>
                            <th className={`${stickyCell} left-0`} rowSpan={2} style={{ minWidth: 120 }}>Cliente</th>
                            <th className={`${stickyCell} left-[120px]`} rowSpan={2} style={{ minWidth: 90 }}>No</th>
                            <th className="text-right" rowSpan={2}>Presupuesto</th>
                            <th className="text-right" rowSpan={2}>Pres. Ejecutar</th>
                            <th className="text-right" rowSpan={2}>Ajuste</th>
                            <th className="text-right" rowSpan={2}>Deductivas</th>
                            <th className="text-right" rowSpan={2}>Pres. Final</th>
                            <th className="text-right" rowSpan={2}>Imp. Cobrado</th>
                            <th className="text-right" rowSpan={2}>Por Cobrar</th>
                            <th className="text-right" rowSpan={2}>% Cobrado</th>
                            <th className="text-right" rowSpan={2}>% Obra</th>
                            <th className="text-right" rowSpan={2}>Total Est.</th>
                            <th className="text-right" rowSpan={2}>Por Estimar</th>
                            <th className="text-right" rowSpan={2}>Fondo Gar.</th>
                            <th className="text-right" rowSpan={2}># Est.</th>
                            <th rowSpan={2}>Estado</th>
                            <th className="text-right" rowSpan={2}>Monto Est.</th>
                            {/* Months header */}
                            <th className="!p-0" style={{ minWidth: timelineWidth }}>
                                <div className="relative h-5 w-full" style={{ minWidth: timelineWidth }}>
                                    {monthMarkers.map((m, i) => (
                                        <div
                                            key={i}
                                            className="absolute top-0 flex h-full items-center overflow-hidden border-l border-base-300/50 px-1 text-[10px] font-semibold"
                                            style={{ left: `${m.pct}%`, width: `${m.widthPct}%` }}
                                        >
                                            {m.label}
                                        </div>
                                    ))}
                                </div>
                            </th>
                        </tr>
                        <tr>
                            {/* Weeks header */}
                            <th className="!p-0" style={{ minWidth: timelineWidth }}>
                                <div className="relative h-4 w-full" style={{ minWidth: timelineWidth }}>
                                    {weekMarkers.map((m, i) => (
                                        <div
                                            key={i}
                                            title={formatShort(m.date)}
                                            className="absolute top-0 flex h-full items-center justify-center border-l border-base-300/40 text-[9px] text-base-content/50"
                                            style={{ left: `${m.pct}%` }}
                                        >
                                            S{m.week}
                                        </div>
                                    ))}
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {groups.map((group) => {
                            const { obra, rows } = group;
                            const datos = calcularDatosProyecto(obra);

                            return rows.map((row, idx) => (
                                <tr key={row.estimacion.id} className="hover">
                                    {/* Sticky: Cliente */}
                                    <td className={`${stickyCell} left-0 border-r`}>
                                        {idx === 0 ? (obra.cliente?.nombre ?? '-') : ''}
                                    </td>
                                    {/* Sticky: No */}
                                    <td className={`${stickyCell} left-[120px] border-r`}>
                                        {idx === 0 ? (
                                            <Link
                                                href={`/admin/cob/obras/${obra.id}`}
                                                className="link link-primary"
                                            >
                                                {obra.no}
                                            </Link>
                                        ) : ''}
                                    </td>
                                    {/* Obra-level financial data (first row only) */}
                                    <td className="text-right">{idx === 0 ? formatearMXN(datos.presupuestoPartidas) : ''}</td>
                                    <td className="text-right">{idx === 0 ? formatearMXN(datos.presupuestoEjecutar) : ''}</td>
                                    <td className="text-right">{idx === 0 ? (datos.tieneComparativos ? formatearMXN(datos.ajustePresupuesto) : '-') : ''}</td>
                                    <td className="text-right">{idx === 0 ? formatearMXN(datos.totalDeducciones) : ''}</td>
                                    <td className="text-right">{idx === 0 ? formatearMXN(datos.presupuestoFinal) : ''}</td>
                                    <td className="text-right">{idx === 0 ? formatearMXN(datos.totalCobrado) : ''}</td>
                                    <td className="text-right">{idx === 0 ? formatearMXN(datos.porCobrar) : ''}</td>
                                    <td className="text-right">{idx === 0 ? pct(datos.totalCobrado, datos.presupuestoFinal) : ''}</td>
                                    <td className="text-right">{idx === 0 ? (obra.porcentaje_obra != null ? `${obra.porcentaje_obra}%` : '-') : ''}</td>
                                    <td className="text-right">{idx === 0 ? group.totalEstimaciones : ''}</td>
                                    <td className="text-right">{idx === 0 ? formatearMXN(group.porEstimar) : ''}</td>
                                    <td className="text-right">{idx === 0 ? formatearMXN(obra.garantia) : ''}</td>
                                    {/* Estimacion-level data */}
                                    <td className="text-right">{row.estimacion.numero_estimacion}</td>
                                    <td><EstadoBadge estado={row.estimacion.estado} /></td>
                                    <td className="text-right">{formatearMXN(row.estimacion.monto_estimado)}</td>
                                    {/* Timeline */}
                                    <td className="!p-0">
                                        <div className="relative w-full" style={{ height: ROW_HEIGHT, minWidth: timelineWidth }}>
                                            {/* Week grid */}
                                            {weekMarkers.map((m, i) => (
                                                <div
                                                    key={i}
                                                    className="absolute top-0 bottom-0 border-l border-base-300/30"
                                                    style={{ left: `${m.pct}%` }}
                                                />
                                            ))}
                                            {/* Bars */}
                                            {row.periods.map((period, pIdx) => {
                                                const offsetDays = daysBetween(minDate, period.start);
                                                const durationDays = Math.max(daysBetween(period.start, period.end), 1);
                                                const leftPct = (offsetDays / totalDays) * 100;
                                                const widthPct = (durationDays / totalDays) * 100;

                                                return (
                                                    <div
                                                        key={pIdx}
                                                        className="group/bar absolute"
                                                        style={{
                                                            left: `${leftPct}%`,
                                                            width: `${widthPct}%`,
                                                            minWidth: 20,
                                                            top: (ROW_HEIGHT - BAR_HEIGHT) / 2,
                                                            height: BAR_HEIGHT,
                                                        }}
                                                    >
                                                        <div
                                                            className={`flex h-full w-full items-center justify-center rounded shadow text-[9px] font-medium ${ESTADO_COLORS[period.estado]}`}
                                                        >
                                                            {widthPct > 5 ? ESTADO_LABELS[period.estado] : ''}
                                                        </div>
                                                        {/* Popover */}
                                                        <div className="pointer-events-none absolute bottom-full left-1/2 z-50 mb-1 hidden -translate-x-1/2 rounded bg-base-300 px-2 py-1 text-[10px] shadow-lg group-hover/bar:block">
                                                            <div className="font-semibold">{ESTADO_LABELS[period.estado]}</div>
                                                            <div className="text-base-content/70">{formatFull(period.start)} → {formatFull(period.end)}</div>
                                                            <div className="text-base-content/50">{durationDays} días</div>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </td>
                                </tr>
                            ));
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
