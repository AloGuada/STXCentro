import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra, PaginatedData } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/cortes' },
    { title: 'Grupo Precios', href: '/admin/prod/grupo-precios' },
];

type ObraRow = Obra & { conceptos_count: number };

const columns: Column<ObraRow>[] = [
    { key: 'no', label: 'No. Obra' },
    { key: 'descripcion', label: 'Descripcion' },
    {
        key: 'conceptos_count',
        label: 'Conceptos',
        render: (o) => <span className="font-mono text-sm">{o.conceptos_count}</span>,
    },
];

type Props = {
    obras: PaginatedData<ObraRow>;
    filters: { search?: string };
};

export default function GrupoPreciosIndex({ obras, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Grupo de Precios" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={obras}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar obras..."
                    createHref="/admin/prod/grupo-precios/create"
                    createLabel="Nuevo Grupo Precio"
                    emptyMessage="No hay obras registradas"
                    getRowHref={(o) => `/admin/prod/grupo-precios/obra/${o.id}`}
                />
            </div>
        </AppLayout>
    );
}
