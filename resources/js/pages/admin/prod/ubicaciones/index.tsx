import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, ProdUbicacion } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/destajos' },
    { title: 'Ubicaciones', href: '/admin/prod/ubicaciones' },
];

const columns: Column<ProdUbicacion>[] = [
    { key: 'nombre', label: 'Nombre', render: (u) => <span className="font-medium">{u.nombre}</span> },
    {
        key: 'grupos_trabajo_count',
        label: 'Grupos',
        className: 'text-right',
        render: (u) => <span className="font-mono text-sm">{u.grupos_trabajo_count ?? 0}</span>,
    },
    {
        key: 'activo',
        label: 'Estado',
        className: 'text-center',
        render: (u) => (
            <span className={`badge badge-sm ${u.activo ? 'badge-success' : 'badge-ghost'}`}>
                {u.activo ? 'Activa' : 'Inactiva'}
            </span>
        ),
    },
];

type Props = {
    ubicaciones: PaginatedData<ProdUbicacion>;
    filters: { search?: string };
};

export default function UbicacionesIndex({ ubicaciones, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ubicaciones" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Ubicaciones</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Dónde trabajan los grupos (líneas, módulos, naves). Un grupo puede usar varias.
                    </p>
                </div>

                <DataTable
                    columns={columns}
                    data={ubicaciones}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar ubicacion..."
                    createHref="/admin/prod/ubicaciones/create"
                    createLabel="Nueva ubicacion"
                    emptyMessage="No hay ubicaciones registradas"
                    getRowHref={(u) => `/admin/prod/ubicaciones/${u.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
