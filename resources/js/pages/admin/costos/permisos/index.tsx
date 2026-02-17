import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosPermiso, PaginatedData } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/permisos' },
    { title: 'Niveles Aprobacion', href: '/admin/costos/permisos' },
];

const columns: Column<CostosPermiso>[] = [
    { key: 'descripcion', label: 'Descripcion' },
    { key: 'nivel', label: 'Nivel' },
];

type Props = {
    permisos: PaginatedData<CostosPermiso>;
    filters: { search?: string };
};

export default function PermisosIndex({ permisos, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Niveles Aprobacion" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={permisos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar niveles..."
                    createHref="/admin/costos/permisos/create"
                    createLabel="Nuevo Nivel"
                    emptyMessage="No hay niveles de aprobacion configurados"
                    getRowHref={(row) => `/admin/costos/permisos/${row.id}`}
                />
            </div>
        </AppLayout>
    );
}
