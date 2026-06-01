import { Head } from '@inertiajs/react';
import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, Proveedor } from '@/types/models';
import { PROVEEDOR_ESTATUS_COLORS, PROVEEDOR_ESTATUS_LABELS } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Proveedores', href: '/admin/proveedores' },
];

const columns: Column<Proveedor>[] = [
    { key: 'codigo', label: 'Código' },
    { key: 'razon_social', label: 'Razón Social' },
    { key: 'rfc', label: 'RFC' },
    {
        key: 'estatus',
        label: 'Estatus',
        render: (p) => (
            <div className="flex flex-wrap items-center gap-1">
                <span className={`badge badge-sm ${PROVEEDOR_ESTATUS_COLORS[p.estatus] ?? 'badge-ghost'}`}>
                    {PROVEEDOR_ESTATUS_LABELS[p.estatus] ?? p.estatus}
                </span>
                {p.bloqueado_complemento && (
                    <span className="badge badge-sm badge-error">Bloqueado: complemento pendiente</span>
                )}
            </div>
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
