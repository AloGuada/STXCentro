import { DataTable, type Column } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, StiAsignacionActivo } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Asignaciones', href: '/admin/sti/asignacion-activos' },
];

const columns: Column<StiAsignacionActivo>[] = [
    {
        key: 'empleado',
        label: 'Empleado',
        render: (a) => (
            <div>
                <div className="font-medium">{a.empleado}</div>
                <div className="text-sm text-gray-500">#{a.no_empleado}</div>
            </div>
        ),
    },
    {
        key: 'equipo_id',
        label: 'Equipo',
        render: (a) => a.equipo?.descripcion ?? '-',
    },
    {
        key: 'departamento_id',
        label: 'Departamento',
        render: (a) => a.departamento?.descripcion ?? '-',
    },
    {
        key: 'fecha_inicial',
        label: 'Fecha Asignacion',
        render: (a) => new Date(a.fecha_inicial).toLocaleDateString('es-MX'),
    },
    {
        key: 'estado',
        label: 'Estado',
        render: (a) => (
            <Badge variant={a.estado === 'activo' ? 'success' : a.estado === 'devuelto' ? 'secondary' : 'warning'}>
                {a.estado}
            </Badge>
        ),
    },
];

type Props = {
    asignaciones: PaginatedData<StiAsignacionActivo>;
    filters: { search?: string };
};

export default function AsignacionActivosIndex({ asignaciones, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Asignaciones de Activos" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={asignaciones}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar asignaciones..."
                    createHref="/admin/sti/asignacion-activos/create"
                    createLabel="Nueva Asignacion"
                    emptyMessage="No hay asignaciones registradas"
                    getRowHref={(a) => `/admin/sti/asignacion-activos/${a.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
