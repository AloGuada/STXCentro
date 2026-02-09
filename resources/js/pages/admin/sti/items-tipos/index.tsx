import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, StiItemTipo } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Tipos de Item', href: '/admin/sti/items-tipos' },
];

const columns: Column<StiItemTipo>[] = [
    { key: 'descripcion', label: 'Descripción' },
];

type Props = {
    tipos: PaginatedData<StiItemTipo>;
    filters: { search?: string };
};

export default function ItemsTiposIndex({ tipos, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tipos de Item" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={tipos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar tipos..."
                    createHref="/admin/sti/items-tipos/create"
                    createLabel="Nuevo Tipo"
                    emptyMessage="No hay tipos de item registrados"
                    getRowHref={(tipo) => `/admin/sti/items-tipos/${tipo.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
