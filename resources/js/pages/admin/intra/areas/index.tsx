import { DataTable, type Column } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Area, PaginatedData } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Intranet', href: '/admin/intra/secciones' },
    { title: 'Áreas', href: '/admin/intra/areas' },
];

const columns: Column<Area>[] = [
    { key: 'descripcion', label: 'Descripción' },
    {
        key: 'parent_id',
        label: 'Área Padre',
        render: (area) => area.parent?.descripcion ?? '-',
    },
    { key: 'order', label: 'Orden' },
    {
        key: 'activo',
        label: 'Estado',
        render: (area) => (
            <Badge variant={area.activo ? 'success' : 'secondary'}>
                {area.activo ? 'Activo' : 'Inactivo'}
            </Badge>
        ),
    },
];

type Props = {
    areas: PaginatedData<Area>;
    filters: { search?: string };
};

export default function AreasIndex({ areas, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Áreas" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={areas}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar áreas..."
                    createHref="/admin/intra/areas/create"
                    createLabel="Nueva Área"
                    emptyMessage="No hay áreas registradas"
                    getRowHref={(area) => `/admin/intra/areas/${area.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
