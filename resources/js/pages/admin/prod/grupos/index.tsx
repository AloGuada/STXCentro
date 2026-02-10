import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, ProdGrupo } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/destajos' },
    { title: 'Grupos', href: '/admin/prod/grupos' },
];

type GrupoWithCounts = ProdGrupo & {
    empleados_count: number;
    fabricados_count: number;
};

const columns: Column<GrupoWithCounts>[] = [
    { key: 'descripcion', label: 'Descripcion' },
    {
        key: 'empleados_count',
        label: 'Empleados',
        render: (grupo) => <span className="font-mono text-sm">{grupo.empleados_count ?? 0}</span>,
    },
    {
        key: 'fabricados_count',
        label: 'Fabricados',
        render: (grupo) => <span className="font-mono text-sm">{grupo.fabricados_count ?? 0}</span>,
    },
];

type Props = {
    grupos: PaginatedData<GrupoWithCounts>;
    filters: { search?: string };
};

export default function GruposIndex({ grupos, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Grupos de Trabajo" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={grupos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar grupos..."
                    createHref="/admin/prod/grupos/create"
                    createLabel="Nuevo Grupo"
                    emptyMessage="No hay grupos registrados"
                    getRowHref={(grupo) => `/admin/prod/grupos/${grupo.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
