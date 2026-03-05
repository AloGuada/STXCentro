import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, StiStatus, StiTicket, StiTicketHistorial } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import { CheckCircleIcon, ClockIcon, MessageSquareTextIcon, XIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Tickets', href: '/admin/sti/tickets' },
];

type TicketWithHistorial = StiTicket & {
    historial?: (StiTicketHistorial & { status: StiStatus })[];
};

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
                    className="badge gap-1 text-white"
                    style={color ? { backgroundColor: color } : undefined}
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
        render: (ticket) =>
            new Date(ticket.created_at).toLocaleString('es-MX', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            }),
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
