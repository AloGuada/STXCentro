import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, ProdGrupoTrabajo } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/cortes' },
    { title: 'Grupos Trabajo', href: '/admin/prod/grupos-trabajo' },
];

type GrupoTrabajoRow = ProdGrupoTrabajo & { empleados_count: number };

const columns: Column<GrupoTrabajoRow>[] = [
    { key: 'descripcion', label: 'Descripcion' },
    {
        key: 'linea',
        label: 'Linea',
        render: (g) => <span className="font-mono text-sm">{g.linea}</span>,
    },
    {
        key: 'modulo',
        label: 'Modulo',
        render: (g) => <span className="font-mono text-sm">{g.modulo}</span>,
    },
    {
        key: 'activo',
        label: 'Estado',
        render: (g) => (
            <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${g.activo ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'}`}>
                {g.activo ? 'Activo' : 'Inactivo'}
            </span>
        ),
    },
    {
        key: 'empleados_count',
        label: 'Empleados',
        render: (g) => <span className="font-mono text-sm">{g.empleados_count}</span>,
    },
];

type Props = {
    grupos: PaginatedData<GrupoTrabajoRow>;
    filters: { search?: string };
};

export default function GruposTrabajoIndex({ grupos, filters }: Props) {
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
                    createHref="/admin/prod/grupos-trabajo/create"
                    createLabel="Nuevo Grupo"
                    emptyMessage="No hay grupos de trabajo registrados"
                    getRowHref={(g) => `/admin/prod/grupos-trabajo/${g.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
