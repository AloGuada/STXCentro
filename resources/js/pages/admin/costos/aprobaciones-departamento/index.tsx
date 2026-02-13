import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosAprobacionDepartamento, PaginatedData } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/aprobaciones-departamento' },
    { title: 'Aprobadores', href: '/admin/costos/aprobaciones-departamento' },
];

const columns: Column<CostosAprobacionDepartamento>[] = [
    {
        key: 'departamento',
        label: 'Departamento',
        render: (row) => row.departamento?.descripcion ?? '-',
    },
    { key: 'nivel', label: 'Nivel' },
    { key: 'nombre_nivel', label: 'Nombre Nivel' },
    {
        key: 'aprobador',
        label: 'Aprobador',
        render: (row) => row.aprobador?.name ?? 'Sin asignar',
    },
    {
        key: 'activo',
        label: 'Estado',
        render: (row) => (
            <span className={`badge ${row.activo ? 'badge-success' : 'badge-error'}`}>
                {row.activo ? 'Activo' : 'Inactivo'}
            </span>
        ),
    },
];

type Props = {
    aprobaciones: PaginatedData<CostosAprobacionDepartamento>;
    filters: { search?: string };
};

export default function AprobacionesDepartamentoIndex({ aprobaciones, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Aprobadores por Departamento" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={aprobaciones}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar aprobadores..."
                    createHref="/admin/costos/aprobaciones-departamento/create"
                    createLabel="Nuevo Aprobador"
                    emptyMessage="No hay aprobadores configurados"
                    getRowHref={(row) => `/admin/costos/aprobaciones-departamento/${row.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
