import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, StiStatus, StiTicket, StiTicketHistorial } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import { CheckCircleIcon, ClockIcon, MessageSquareTextIcon, PauseCircleIcon, PlayCircleIcon, TimerIcon, XIcon } from 'lucide-react';
import { useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Tickets', href: '/admin/sti/tickets' },
];

type TicketWithHistorial = StiTicket & {
    historial?: (StiTicketHistorial & { status: StiStatus })[];
};

type TiempoDesglose = {
    totalMin: number;
    activoMin: number;
    detenidoMin: number;
    detalle: { estado: string; minutos: number; color: string; detuvo: boolean }[];
};

function calcularTiempos(ticket: TicketWithHistorial): TiempoDesglose | null {
    const entries = ticket.historial?.slice().sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime());
    if (!entries || entries.length === 0) return null;

    let totalMin = 0;
    let detenidoMin = 0;
    const detalle: TiempoDesglose['detalle'] = [];

    for (let i = 0; i < entries.length; i++) {
        const current = entries[i];
        const next = entries[i + 1] ?? null;

        const inicio = new Date(current.created_at).getTime();
        const fin = next ? new Date(next.created_at).getTime() : Date.now();

        const minutos = Math.max(0, (fin - inicio) / 60000);
        totalMin += minutos;

        const detuvo = current.status?.detiene_tiempo ?? false;
        if (detuvo) detenidoMin += minutos;

        detalle.push({
            estado: current.status?.descripcion ?? 'Desconocido',
            minutos,
            color: current.status?.color ?? '#6b7280',
            detuvo,
        });
    }

    return { totalMin, activoMin: totalMin - detenidoMin, detenidoMin, detalle };
}

function formatDuracion(minutos: number): string {
    if (minutos < 60) return `${Math.round(minutos)}m`;
    const h = Math.floor(minutos / 60);
    const m = Math.round(minutos % 60);
    if (h < 24) return m > 0 ? `${h}h ${m}m` : `${h}h`;
    const d = Math.floor(h / 24);
    const rh = h % 24;
    return rh > 0 ? `${d}d ${rh}h` : `${d}d`;
}

function TiempoPopover({ ticket }: { ticket: TicketWithHistorial }) {
    const [open, setOpen] = useState(false);
    const timeoutRef = useRef<ReturnType<typeof setTimeout>>(null);
    const tiempos = calcularTiempos(ticket);

    const fecha = new Date(ticket.created_at).toLocaleString('es-MX', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

    if (!tiempos) return <span>{fecha}</span>;

    const handleEnter = () => {
        if (timeoutRef.current) clearTimeout(timeoutRef.current);
        setOpen(true);
    };

    const handleLeave = () => {
        timeoutRef.current = setTimeout(() => setOpen(false), 150);
    };

    return (
        <div className="relative inline-block" onMouseEnter={handleEnter} onMouseLeave={handleLeave}>
            <button type="button" className="flex items-center gap-1.5 text-left cursor-default" onClick={(e) => { e.preventDefault(); e.stopPropagation(); setOpen(!open); }}>
                <TimerIcon className="size-3.5 text-base-content/50" />
                <span>{fecha}</span>
            </button>

            {open && (
                <div className="absolute right-0 bottom-full z-50 mb-2 w-72 rounded-lg border border-base-300 bg-base-100 p-3 shadow-lg" onMouseEnter={handleEnter} onMouseLeave={handleLeave}>
                    <div className="mb-3 grid grid-cols-3 gap-2 text-center">
                        <div>
                            <div className="text-xs text-base-content/50">Total</div>
                            <div className="text-sm font-semibold">{formatDuracion(tiempos.totalMin)}</div>
                        </div>
                        <div>
                            <div className="flex items-center justify-center gap-1 text-xs text-success"><PlayCircleIcon className="size-3" />Activo</div>
                            <div className="text-sm font-semibold text-success">{formatDuracion(tiempos.activoMin)}</div>
                        </div>
                        <div>
                            <div className="flex items-center justify-center gap-1 text-xs text-warning"><PauseCircleIcon className="size-3" />Espera</div>
                            <div className="text-sm font-semibold text-warning">{formatDuracion(tiempos.detenidoMin)}</div>
                        </div>
                    </div>

                    {tiempos.totalMin > 0 && (
                        <div className="mb-3 flex h-2 overflow-hidden rounded-full bg-base-200">
                            {tiempos.detalle.map((d, i) => {
                                const pct = (d.minutos / tiempos.totalMin) * 100;
                                if (pct < 0.5) return null;
                                return (
                                    <div
                                        key={i}
                                        className="h-full transition-all"
                                        style={{ width: `${pct}%`, backgroundColor: d.color, opacity: d.detuvo ? 0.5 : 1 }}
                                        title={`${d.estado}: ${formatDuracion(d.minutos)}`}
                                    />
                                );
                            })}
                        </div>
                    )}

                    <div className="space-y-1">
                        {tiempos.detalle.map((d, i) => (
                            <div key={i} className="flex items-center justify-between text-xs">
                                <div className="flex items-center gap-1.5">
                                    <span className="inline-block size-2 rounded-full" style={{ backgroundColor: d.color }} />
                                    <span className={d.detuvo ? 'text-base-content/50 italic' : ''}>{d.estado}</span>
                                </div>
                                <span className={`font-mono ${d.detuvo ? 'text-warning' : 'text-base-content/70'}`}>{formatDuracion(d.minutos)}</span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

const columns: Column<TicketWithHistorial>[] = [
    { key: 'id', label: '#' },
    { key: 'nombre_solicitante', label: 'Solicitante' },
    {
        key: 'comentario',
        label: 'Problema',
        render: (ticket) => (
            <div className="tooltip tooltip-right max-w-xs" data-tip={ticket.comentario || 'Sin descripción'}>
                <MessageSquareTextIcon className="text-base-content/50 size-4" />
            </div>
        ),
    },
    {
        key: 'departamento_id',
        label: 'Departamento',
        render: (ticket) => ticket.departamento?.descripcion ?? '-',
    },
    {
        key: 'tecnico_id',
        label: 'Técnico',
        render: (ticket) => ticket.tecnico?.descripcion ?? 'Sin asignar',
    },
    {
        key: 'historial',
        label: 'Estado',
        render: (ticket) => {
            const currentStatus = ticket.historial?.[0]?.status;
            const isCompleted = currentStatus && currentStatus.orden >= 8;
            const color = currentStatus?.color;
            return (
                <span
                    className="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
                    style={{ backgroundColor: color ?? '#6b7280' }}
                >
                    {isCompleted ? <CheckCircleIcon className="size-3" /> : <ClockIcon className="size-3" />}
                    {currentStatus?.descripcion ?? 'Sin estado'}
                </span>
            );
        },
    },
    {
        key: 'calificacion',
        label: 'Calificación',
        render: (ticket) =>
            ticket.calificacion ? (
                <span className="text-warning">{Array.from({ length: 5 }, (_, i) => (i < ticket.calificacion! ? '★' : '☆')).join('')}</span>
            ) : (
                <span className="text-base-content/40">—</span>
            ),
    },
    {
        key: 'created_at',
        label: 'Fecha',
        render: (ticket) => <TiempoPopover ticket={ticket} />,
    },
];

type Tecnico = { id: number; descripcion: string };
type Departamento = { id: number; descripcion: string };

type Filters = {
    search?: string;
    tecnico_id?: string;
    departamento_id?: string;
    calificacion?: string;
    estado?: string;
    periodo?: string;
};

type Props = {
    tickets: PaginatedData<TicketWithHistorial>;
    filters: Filters;
    tecnicos: Tecnico[];
    departamentos: Departamento[];
};

const estadoOptions = [
    { value: '', label: 'Todos los estados' },
    { value: 'sin_asignar', label: 'Sin asignar' },
    { value: 'en_proceso', label: 'En proceso' },
    { value: 'completados', label: 'Completados' },
];

const calificacionOptions = [
    { value: '', label: 'Todas las calificaciones' },
    { value: '1', label: '★☆☆☆☆ (1)' },
    { value: '2', label: '★★☆☆☆ (2)' },
    { value: '3', label: '★★★☆☆ (3)' },
    { value: '4', label: '★★★★☆ (4)' },
    { value: '5', label: '★★★★★ (5)' },
];

function hasActiveFilters(filters: Filters): boolean {
    return !!(filters.tecnico_id || filters.departamento_id || filters.calificacion || filters.estado || filters.periodo);
}

export default function TicketsIndex({ tickets, filters, tecnicos, departamentos }: Props) {
    const applyFilter = (key: string, value: string) => {
        router.get('/admin/sti/tickets', { ...filters, [key]: value || undefined }, { preserveState: true });
    };

    const clearFilters = () => {
        router.get('/admin/sti/tickets', { search: filters.search || undefined }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tickets" />

            <div className="p-6">
                <div className="mb-4 flex flex-wrap items-center gap-3">
                    <select className="select select-bordered select-sm" value={filters.tecnico_id ?? ''} onChange={(e) => applyFilter('tecnico_id', e.target.value)}>
                        <option value="">Todos los técnicos</option>
                        {tecnicos.map((t) => (
                            <option key={t.id} value={t.id}>{t.descripcion}</option>
                        ))}
                    </select>

                    <select className="select select-bordered select-sm" value={filters.departamento_id ?? ''} onChange={(e) => applyFilter('departamento_id', e.target.value)}>
                        <option value="">Todos los departamentos</option>
                        {departamentos.map((d) => (
                            <option key={d.id} value={d.id}>{d.descripcion}</option>
                        ))}
                    </select>

                    <select className="select select-bordered select-sm" value={filters.estado ?? ''} onChange={(e) => applyFilter('estado', e.target.value)}>
                        {estadoOptions.map((opt) => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                    </select>

                    <select className="select select-bordered select-sm" value={filters.calificacion ?? ''} onChange={(e) => applyFilter('calificacion', e.target.value)}>
                        {calificacionOptions.map((opt) => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                    </select>

                    {filters.periodo && (
                        <span className="badge badge-outline gap-1">
                            Periodo: {filters.periodo}
                            <button type="button" onClick={() => applyFilter('periodo', '')}><XIcon className="size-3" /></button>
                        </span>
                    )}

                    {hasActiveFilters(filters) && (
                        <button type="button" className="btn btn-ghost btn-sm" onClick={clearFilters}>
                            Limpiar filtros
                        </button>
                    )}
                </div>

                <DataTable
                    columns={columns}
                    data={tickets}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar tickets..."
                    createHref="/admin/sti/tickets/create"
                    createLabel="Nuevo Ticket"
                    emptyMessage="No hay tickets registrados"
                    getRowHref={(ticket) => `/admin/sti/tickets/${ticket.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
