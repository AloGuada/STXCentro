import { Head } from '@inertiajs/react';
import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosUsoCfdi, PaginatedData } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/usos-cfdi' },
    { title: 'Usos CFDI', href: '/admin/costos/usos-cfdi' },
];

const columns: Column<CostosUsoCfdi>[] = [
    { key: 'clave', label: 'Clave', sortable: true },
    { key: 'descripcion', label: 'Descripción', sortable: true },
    {
        key: 'activo',
        label: 'Estatus',
        sortable: true,
        render: (u) => (
            <span className={`badge badge-sm ${u.activo ? 'badge-success' : 'badge-ghost'}`}>
                {u.activo ? 'Activo' : 'Inactivo'}
            </span>
        ),
    },
    {
        key: 'requisicion_detalles_count',
        label: 'Partidas',
        sortable: true,
        render: (u) => u.requisicion_detalles_count ?? 0,
    },
];

type Props = {
    usosCfdi: PaginatedData<CostosUsoCfdi>;
    filters: { search?: string };
    sortBy?: string;
    sortDir?: 'asc' | 'desc';
};

export default function UsosCfdiIndex({ usosCfdi, filters, sortBy, sortDir }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Usos CFDI" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={usosCfdi}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por clave o descripción..."
                    createHref="/admin/costos/usos-cfdi/create"
                    createLabel="Nuevo Uso CFDI"
                    emptyMessage="No hay usos de CFDI registrados"
                    getRowHref={(u) => `/admin/costos/usos-cfdi/${u.id}/edit`}
                    sortBy={sortBy}
                    sortDir={sortDir}
                />
            </div>
        </AppLayout>
    );
}
