import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, Proveedor } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Proveedores', href: '/admin/proveedores' },
];

const columns: Column<Proveedor>[] = [
    { key: 'codigo', label: 'Código' },
    { key: 'razon_social', label: 'Razón Social' },
    { key: 'rfc', label: 'RFC' },
    {
        key: 'departamento',
        label: 'Departamento',
        render: (p) => p.departamento?.descripcion ?? '-',
    },
    {
        key: 'activo',
        label: 'Activo',
        render: (p) => (
            <span className={`badge badge-sm ${p.activo ? 'badge-success' : 'badge-ghost'}`}>
                {p.activo ? 'Sí' : 'No'}
            </span>
        ),
    },
];

type Props = {
    proveedores: PaginatedData<Proveedor>;
    filters: { search?: string };
};

export default function ProveedoresIndex({ proveedores, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Proveedores" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={proveedores}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar proveedores..."
                    createHref="/admin/proveedores/create"
                    createLabel="Nuevo Proveedor"
                    emptyMessage="No hay proveedores registrados"
                    getRowHref={(p) => `/admin/proveedores/${p.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
