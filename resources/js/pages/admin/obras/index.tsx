import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra, PaginatedData } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Obras', href: '/admin/obras' },
];

const columns: Column<Obra>[] = [
    { key: 'no', label: 'Número' },
    { key: 'descripcion', label: 'Descripción' },
    {
        key: 'created_at',
        label: 'Creado',
        render: (obra) => new Date(obra.created_at).toLocaleDateString(),
    },
];

type Props = {
    obras: PaginatedData<Obra>;
    filters: { search?: string };
};

export default function ObrasIndex({ obras, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Obras" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={obras}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar obras..."
                    createHref="/admin/obras/create"
                    createLabel="Nueva Obra"
                    emptyMessage="No hay obras registradas"
                    getRowHref={(obra) => `/admin/obras/${obra.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
