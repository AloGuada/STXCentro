import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosRubro, PaginatedData } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/rubros' },
    { title: 'Centros de Costos', href: '/admin/costos/rubros' },
];

const columns: Column<CostosRubro>[] = [
    { key: 'codigo', label: 'Código', sortable: true },
    { key: 'descripcion', label: 'Descripción', sortable: true },
    {
        key: 'ambito',
        label: 'Ámbito',
        sortable: true,
        render: (r) => (
            <span className={`badge badge-sm ${r.ambito === 'planta' ? 'badge-info' : 'badge-ghost'}`}>
                {r.ambito === 'planta' ? 'Planta' : 'Obras'}
            </span>
        ),
    },
    {
        key: 'tipo_rubro',
        label: 'Tipo de Centro de Costos',
        sortable: true,
        render: (r) => r.tipo_rubro?.descripcion ?? '-',
    },
    {
        key: 'departamento',
        label: 'Departamento',
        sortable: true,
        render: (r) => r.departamento?.descripcion ?? '-',
    },
];

type Props = {
    rubros: PaginatedData<CostosRubro>;
    filters: { search?: string };
    sortBy?: string;
    sortDir?: 'asc' | 'desc';
};

export default function RubrosIndex({ rubros, filters, sortBy, sortDir }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Centros de Costos" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={rubros}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar centros de costos..."
                    createHref="/admin/costos/rubros/create"
                    createLabel="Nuevo Centro de Costos"
                    emptyMessage="No hay centros de costos registrados"
                    getRowHref={(r) => `/admin/costos/rubros/${r.id}/edit`}
                    sortBy={sortBy}
                    sortDir={sortDir}
                />
            </div>
        </AppLayout>
    );
}
