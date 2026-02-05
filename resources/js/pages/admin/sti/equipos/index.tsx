import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, StiEquipo } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Equipos', href: '/admin/sti/equipos' },
];

const columns: Column<StiEquipo>[] = [
    { key: 'descripcion', label: 'Descripción' },
    { key: 'serie', label: 'Serie', render: (equipo) => equipo.serie ?? '-' },
    { key: 'marca', label: 'Marca', render: (equipo) => equipo.marca ?? '-' },
];

type Props = {
    equipos: PaginatedData<StiEquipo>;
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
