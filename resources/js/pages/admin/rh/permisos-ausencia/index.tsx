import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, RhPermisoAusencia } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'RH', href: '/admin/rh/skills' },
    { title: 'Permisos de Ausencia', href: '/admin/rh/permisos-ausencia' },
];

const columns: Column<RhPermisoAusencia>[] = [
    { key: 'folio', label: 'Folio' },
    { key: 'nombres', label: 'Nombres' },
    { key: 'apellidos', label: 'Apellidos' },
    { key: 'tipo', label: 'Tipo' },
    { key: 'modalidad', label: 'Modalidad' },
    {
        key: 'fecha_permiso',
        label: 'Fecha Permiso',
        render: (permiso) => permiso.fecha_permiso ? new Date(permiso.fecha_permiso).toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric', timeZone: 'UTC' }) : '-',
    },
];

type Props = {
    permisos: PaginatedData<RhPermisoAusencia>;
    filters: { search?: string };
};

export default function PermisosAusenciaIndex({ permisos, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Permisos de Ausencia" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={permisos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar permisos..."
                    createHref="/admin/rh/permisos-ausencia/create"
                    createLabel="Nuevo Permiso"
                    emptyMessage="No hay permisos de ausencia registrados"
                    getRowHref={(permiso) => `/admin/rh/permisos-ausencia/${permiso.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
