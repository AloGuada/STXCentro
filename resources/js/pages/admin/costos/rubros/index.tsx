import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosRubro, PaginatedData } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/rubros' },
    { title: 'Rubros', href: '/admin/costos/rubros' },
];

const columns: Column<CostosRubro>[] = [
    { key: 'codigo', label: 'Código' },
    { key: 'descripcion', label: 'Descripción' },
    {
        key: 'tipo_rubro',
        label: 'Tipo Rubro',
        render: (r) => r.tipo_rubro?.descripcion ?? '-',
    },
    {
        key: 'departamento',
        label: 'Departamento',
        render: (r) => r.departamento?.descripcion ?? '-',
    },
];

type Props = {
    rubros: PaginatedData<CostosRubro>;
    filters: { search?: string };
};

export default function RubrosIndex({ rubros, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Rubros" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={rubros}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar rubros..."
                    createHref="/admin/costos/rubros/create"
                    createLabel="Nuevo Rubro"
                    emptyMessage="No hay rubros registrados"
                    getRowHref={(r) => `/admin/costos/rubros/${r.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
