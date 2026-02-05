import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, StiTicket } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Tickets', href: '/admin/sti/tickets' },
];

const columns: Column<StiTicket>[] = [
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
        key: 'equipo_id',
        label: 'Equipo',
        render: (ticket) => ticket.equipo?.descripcion ?? '-',
    },
    {
        key: 'created_at',
        label: 'Fecha',
        render: (ticket) => new Date(ticket.created_at).toLocaleDateString('es-MX'),
    },
];

type Props = {
    tickets: PaginatedData<StiTicket>;
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
