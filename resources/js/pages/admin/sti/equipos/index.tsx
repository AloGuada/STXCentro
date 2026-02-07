import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { CRITICIDAD_COLORS, CRITICIDAD_LABELS, type PaginatedData, type StiCriticidad, type StiEquipo } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Equipos', href: '/admin/sti/equipos' },
];

type EquipoWithCounts = StiEquipo & {
    tickets_count: number;
    mantenimientos_count: number;
    total_costos: number;
};

const columns: Column<EquipoWithCounts>[] = [
    { key: 'descripcion', label: 'Descripcion' },
    { key: 'serie', label: 'Serie', render: (equipo) => equipo.serie ?? '-' },
    { key: 'marca', label: 'Marca', render: (equipo) => equipo.marca ?? '-' },
    {
        key: 'factor_criticidad',
        label: 'Criticidad',
        render: (equipo) => {
            const criticidad = equipo.factor_criticidad as StiCriticidad;
            return (
                <span className={`badge badge-sm ${CRITICIDAD_COLORS[criticidad] ?? ''}`}>
                    {CRITICIDAD_LABELS[criticidad] ?? criticidad}
                </span>
            );
        },
    },
    {
        key: 'tickets_count',
        label: 'Tickets',
        render: (equipo) => (
            <span className="font-mono text-sm">{equipo.tickets_count ?? 0}</span>
        ),
    },
    {
        key: 'mantenimientos_count',
        label: 'Mantenimientos',
        render: (equipo) => (
            <span className="font-mono text-sm">{equipo.mantenimientos_count ?? 0}</span>
        ),
    },
    {
        key: 'total_costos',
        label: 'Total Costos',
        render: (equipo) => (
            <span className="font-mono text-sm">
                ${(equipo.total_costos ?? 0).toLocaleString('es-MX', { minimumFractionDigits: 2 })}
            </span>
        ),
    },
];

type Props = {
    equipos: PaginatedData<EquipoWithCounts>;
    filters: { search?: string };
};

export default function EquiposIndex({ equipos, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Equipos" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={equipos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar equipos..."
                    createHref="/admin/sti/equipos/create"
                    createLabel="Nuevo Equipo"
                    emptyMessage="No hay equipos registrados"
                    getRowHref={(equipo) => `/admin/sti/equipos/${equipo.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
