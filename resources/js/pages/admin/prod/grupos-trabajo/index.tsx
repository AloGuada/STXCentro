import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, ProdGrupoTrabajo } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/destajos' },
    { title: 'Grupos Trabajo', href: '/admin/prod/grupos-trabajo' },
];

type GrupoTrabajoRow = ProdGrupoTrabajo & { empleados_count: number };

const columns: Column<GrupoTrabajoRow>[] = [
    { key: 'descripcion', label: 'Descripcion' },
    {
        key: 'linea',
        label: 'Linea',
        className: 'text-right',
        render: (g) => <span className="font-mono text-sm">{g.linea}</span>,
    },
    {
        key: 'modulo',
        label: 'Modulo',
        className: 'text-right',
        render: (g) => <span className="font-mono text-sm">{g.modulo}</span>,
    },
    {
        key: 'activo',
        label: 'Estado',
        render: (g) => (
            <span className={`badge badge-sm ${g.activo ? 'badge-success' : 'badge-ghost'}`}>
                {g.activo ? 'Activo' : 'Inactivo'}
            </span>
        ),
    },
    {
        key: 'empleados_count',
        label: 'Empleados',
        className: 'text-right',
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
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Grupos de trabajo</h1>
                    <p className="mt-1 text-sm text-base-content/60">Equipos de producción y sus integrantes.</p>
                </div>

                <DataTable
                    columns={columns}
                    data={grupos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar grupos..."
                    createHref="/admin/prod/grupos-trabajo/create"
                    createLabel="Nuevo grupo"
                    emptyMessage="No hay grupos de trabajo registrados"
                    getRowHref={(g) => `/admin/prod/grupos-trabajo/${g.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
