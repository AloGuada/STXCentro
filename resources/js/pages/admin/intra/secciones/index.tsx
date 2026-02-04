import { DataTable, type Column } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, SeccionEstatica } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Intranet', href: '/admin/intra/secciones' },
    { title: 'Secciones', href: '/admin/intra/secciones' },
];

const columns: Column<SeccionEstatica>[] = [
    { key: 'titulo', label: 'Título' },
    { key: 'slug', label: 'Slug' },
    { key: 'boton', label: 'Botón' },
    { key: 'order', label: 'Orden' },
    {
        key: 'activo',
        label: 'Estado',
        render: (seccion) => (
            <Badge variant={seccion.activo ? 'success' : 'secondary'}>
                {seccion.activo ? 'Activo' : 'Inactivo'}
            </Badge>
        ),
    },
];

type Props = {
    secciones: PaginatedData<SeccionEstatica>;
    filters: { search?: string };
};

export default function SeccionesIndex({ secciones, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Secciones Estáticas" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={secciones}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar secciones..."
                    createHref="/admin/intra/secciones/create"
                    createLabel="Nueva Sección"
                    emptyMessage="No hay secciones registradas"
                    getRowHref={(seccion) => `/admin/intra/secciones/${seccion.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
