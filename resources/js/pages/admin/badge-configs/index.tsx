import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { BadgeConfig, PaginatedData } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Badge Configs', href: '/admin/badge-configs' },
];

const columns: Column<BadgeConfig>[] = [
    { key: 'nombre', label: 'Nombre' },
    { key: 'tabla', label: 'Tabla' },
    {
        key: 'campo_estatus',
        label: 'Condición',
        render: (row) => `${row.campo_estatus} ${row.operador} ${row.valor_estatus}`,
    },
    { key: 'rol', label: 'Rol' },
    { key: 'nav_href', label: 'Nav Item' },
    {
        key: 'activo',
        label: 'Estado',
        render: (row) => (
            <span className={`badge badge-sm ${row.activo ? 'badge-success' : 'badge-ghost'}`}>
                {row.activo ? 'Activo' : 'Inactivo'}
            </span>
        ),
    },
];

type Props = {
    configs: PaginatedData<BadgeConfig>;
    filters: { search?: string };
};

export default function BadgeConfigsIndex({ configs, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Badge Configs" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={configs}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por nombre..."
                    createHref="/admin/badge-configs/create"
                    createLabel="Nueva Config"
                    emptyMessage="No hay badge configs registradas"
                    getRowHref={(row) => `/admin/badge-configs/${row.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
