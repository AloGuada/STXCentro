import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, StiStatus, StiTicket, StiTicketHistorial } from '@/types/models';
import { Head } from '@inertiajs/react';
import { CheckCircleIcon, ClockIcon } from 'lucide-react';

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
            return (
                <span className={`badge gap-1 ${isCompleted ? 'badge-success' : 'badge-info'}`}>
                    {isCompleted ? <CheckCircleIcon className="size-3" /> : <ClockIcon className="size-3" />}
                    {currentStatus?.descripcion ?? 'Sin estado'}
                </span>
            );
        },
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

type Props = {
    tickets: PaginatedData<TicketWithHistorial>;
    filters: { search?: string };
};

export default function TicketsIndex({ tickets, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tickets" />

            <div className="p-6">
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
