import { DataTable, type Column } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, StiStatus } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Estados', href: '/admin/sti/status' },
];

const columns: Column<StiStatus>[] = [
    { key: 'descripcion', label: 'Descripción' },
    { key: 'orden', label: 'Orden' },
    {
        key: 'detiene_tiempo',
        label: 'Detiene Tiempo',
        render: (status) => (
            <Badge variant={status.detiene_tiempo ? 'warning' : 'secondary'}>
                {status.detiene_tiempo ? 'Sí' : 'No'}
            </Badge>
        ),
    },
];

type Props = {
    statuses: PaginatedData<StiStatus>;
    filters: { search?: string };
};

export default function StatusIndex({ statuses, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Estados" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={statuses}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar estados..."
                    createHref="/admin/sti/status/create"
                    createLabel="Nuevo Estado"
                    emptyMessage="No hay estados registrados"
                    getRowHref={(status) => `/admin/sti/status/${status.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
