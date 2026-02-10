import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra, PaginatedData } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/destajos' },
    { title: 'Grupo Precios', href: '/admin/prod/grupo-precios' },
];

type ObraWithCounts = Obra & {
    piezas_count: number;
};

const columns: Column<ObraWithCounts>[] = [
    { key: 'no', label: 'No. Obra' },
    { key: 'descripcion', label: 'Descripcion' },
    {
        key: 'piezas_count',
        label: 'Piezas',
        render: (obra) => <span className="font-mono text-sm">{obra.piezas_count ?? 0}</span>,
    },
];

type Props = {
    obras: PaginatedData<ObraWithCounts>;
    filters: { search?: string };
};

export default function GrupoPreciosIndex({ obras, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Grupo Precios - Obras" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={obras}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar obra..."
                    createHref="/admin/prod/grupo-precios/create"
                    createLabel="Nuevo Grupo Precio"
                    emptyMessage="No hay obras registradas"
                    getRowHref={(obra) => `/admin/prod/grupo-precios/obra/${obra.id}`}
                />
            </div>
        </AppLayout>
    );
}
