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

type ObraRow = Obra & { conceptos_count: number; conceptos_sin_precio_count: number };

const columns: Column<ObraRow>[] = [
    { key: 'no', label: 'No. Obra' },
    { key: 'descripcion', label: 'Descripcion' },
    {
        key: 'conceptos_sin_precio_count',
        label: 'Sin Precio',
        className: 'text-right',
        render: (obra) => (
            <span className={`badge badge-sm ${obra.conceptos_sin_precio_count > 0 ? 'badge-warning' : 'badge-success'}`}>
                {obra.conceptos_sin_precio_count}
            </span>
        ),
    },
    {
        key: 'conceptos_count',
        label: 'Total Activas',
        className: 'text-right',
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
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Grupo de precios</h1>
                    <p className="mt-1 text-sm text-base-content/60">Precio por kilo de las piezas de cada obra.</p>
                </div>

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
