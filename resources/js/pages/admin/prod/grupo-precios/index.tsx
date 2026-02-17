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

type ObraRow = Obra & { conceptos_count: number; conceptos_sin_precio_count: number };

const columns: Column<ObraRow>[] = [
    { key: 'no', label: 'No. Obra' },
    { key: 'descripcion', label: 'Descripcion' },
    {
        key: 'id',
        label: 'Sin Precio',
        render: (obra) => (
            <span className={`badge ${obra.conceptos_sin_precio_count > 0 ? 'badge-warning' : 'badge-success'}`}>
                {obra.conceptos_sin_precio_count}
            </span>
        ),
    },
    {
        key: 'estatus',
        label: 'Total Piezas',
        render: (obra) => <span className="font-mono text-sm">{obra.conceptos_count}</span>,
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
                    searchPlaceholder="Buscar obra..."
                    emptyMessage="No hay obras registradas"
                    getRowHref={(obra) => `/admin/prod/grupo-precios/obra/${obra.id}`}
                />
            </div>
        </AppLayout>
    );
}
