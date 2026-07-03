import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosTipoRubro, PaginatedData } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/tipo-rubros' },
    { title: 'Tipos de Centro de Costos', href: '/admin/costos/tipo-rubros' },
];

const columns: Column<CostosTipoRubro>[] = [
    { key: 'descripcion', label: 'Descripción', sortable: true },
    {
        key: 'rubros_count',
        label: 'Centros de Costos',
        sortable: true,
        render: (tr) => tr.rubros_count ?? 0,
    },
    {
        key: 'created_at',
        label: 'Creado',
        sortable: true,
        render: (tr) => new Date(tr.created_at).toLocaleDateString(),
    },
];

type Props = {
    tipoRubros: PaginatedData<CostosTipoRubro>;
    filters: { search?: string };
    sortBy?: string;
    sortDir?: 'asc' | 'desc';
};

export default function TipoRubrosIndex({ tipoRubros, filters, sortBy, sortDir }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tipos de Centro de Costos" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={tipoRubros}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar tipos de centro de costos..."
                    createHref="/admin/costos/tipo-rubros/create"
                    createLabel="Nuevo Tipo"
                    emptyMessage="No hay tipos de centro de costos registrados"
                    getRowHref={(tr) => `/admin/costos/tipo-rubros/${tr.id}/edit`}
                    sortBy={sortBy}
                    sortDir={sortDir}
                />
            </div>
        </AppLayout>
    );
}
