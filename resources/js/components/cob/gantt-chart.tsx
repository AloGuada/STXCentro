import type { CobEvento } from '@/types/models';
import { useMemo, useState } from 'react';

type ViewMode = 'dias' | 'semanas' | 'meses';

type Props = {
    eventos: CobEvento[];
};

function getDateRange(eventos: CobEvento[]): { minDate: Date; maxDate: Date } {
    const dates = eventos.flatMap((e) => [e.inicio, e.fin].filter(Boolean)).map((d) => new Date(d!));

    if (dates.length === 0) {
        const now = new Date();
        return { minDate: now, maxDate: new Date(now.getTime() + 30 * 86400000) };
    }

    return {
        minDate: new Date(Math.min(...dates.map((d) => d.getTime()))),
        maxDate: new Date(Math.max(...dates.map((d) => d.getTime()))),
    };
}

function daysBetween(a: Date, b: Date): number {
    return Math.ceil((b.getTime() - a.getTime()) / 86400000);
}

function formatDate(d: Date): string {
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
}

export function GanttChart({ eventos }: Props) {
    const [viewMode, setViewMode] = useState<ViewMode>('semanas');

    const { minDate, maxDate, totalDays } = useMemo(() => {
        const range = getDateRange(eventos);
        const padding = 7;
        const min = new Date(range.minDate.getTime() - padding * 86400000);
        const max = new Date(range.maxDate.getTime() + padding * 86400000);

        return { minDate: min, maxDate: max, totalDays: daysBetween(min, max) };
    }, [eventos]);

    const parentEvents = eventos.filter((e) => e.parent_id === null);

    if (eventos.length === 0) {
        return <div className="text-center py-8 opacity-50">No hay eventos registrados</div>;
    }

    const colWidth = viewMode === 'dias' ? 30 : viewMode === 'semanas' ? 20 : 8;
    const chartWidth = totalDays * colWidth;

    return (
        <div>
            <div className="flex gap-2 mb-4">
                {(['dias', 'semanas', 'meses'] as ViewMode[]).map((mode) => (
                    <button
                        key={mode}
                        className={`btn btn-xs ${viewMode === mode ? 'btn-primary' : 'btn-ghost'}`}
                        onClick={() => setViewMode(mode)}
                    >
                        {mode.charAt(0).toUpperCase() + mode.slice(1)}
                    </button>
                ))}
            </div>

            <div className="overflow-x-auto border rounded-lg">
                <div style={{ minWidth: `${chartWidth + 250}px` }}>
                    {/* Header */}
                    <div className="flex border-b bg-base-200">
                        <div className="w-[250px] shrink-0 p-2 font-semibold text-sm border-r">Evento</div>
                        <div className="flex-1 flex">
                            {Array.from({ length: Math.ceil(totalDays / (viewMode === 'meses' ? 30 : viewMode === 'semanas' ? 7 : 1)) }, (_, i) => {
                                const days = viewMode === 'meses' ? 30 : viewMode === 'semanas' ? 7 : 1;
                                const d = new Date(minDate.getTime() + i * days * 86400000);

                                return (
                                    <div
                                        key={i}
                                        className="text-xs text-center border-r p-1 opacity-70"
                                        style={{ width: `${days * colWidth}px` }}
                                    >
                                        {formatDate(d)}
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Rows */}
                    {parentEvents.map((evento) => (
                        <GanttRow
                            key={evento.id}
                            evento={evento}
                            minDate={minDate}
                            colWidth={colWidth}
                            chartWidth={chartWidth}
                            depth={0}
                        />
                    ))}
                </div>
            </div>
        </div>
    );
}

function GanttRow({
    evento,
    minDate,
    colWidth,
    chartWidth,
    depth,
}: {
    evento: CobEvento;
    minDate: Date;
    colWidth: number;
    chartWidth: number;
    depth: number;
}) {
    const startOffset = evento.inicio
        ? daysBetween(minDate, new Date(evento.inicio)) * colWidth
        : 0;

    const duration = evento.inicio && evento.fin
        ? Math.max(daysBetween(new Date(evento.inicio), new Date(evento.fin)), 1) * colWidth
        : 0;

    return (
        <>
            <div className="flex border-b hover:bg-base-200/50">
                <div
                    className="w-[250px] shrink-0 p-2 text-sm border-r truncate"
                    style={{ paddingLeft: `${8 + depth * 16}px` }}
                >
                    <span className={evento.marcado ? 'line-through opacity-50' : ''}>
                        {evento.nombre}
                    </span>
                </div>
                <div className="flex-1 relative" style={{ minWidth: `${chartWidth}px` }}>
                    {duration > 0 && (
                        <div
                            className={`absolute top-1/2 -translate-y-1/2 h-5 rounded ${
                                evento.marcado ? 'bg-success/60' : depth > 0 ? 'bg-info/60' : 'bg-primary/60'
                            }`}
                            style={{ left: `${startOffset}px`, width: `${duration}px` }}
                        />
                    )}
                </div>
            </div>

            {evento.children?.map((child) => (
                <GanttRow
                    key={child.id}
                    evento={child}
                    minDate={minDate}
                    colWidth={colWidth}
                    chartWidth={chartWidth}
                    depth={depth + 1}
                />
            ))}
        </>
    );
}
