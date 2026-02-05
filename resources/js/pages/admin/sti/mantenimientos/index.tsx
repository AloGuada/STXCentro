import { DataTable, type Column } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, StiMantenimiento } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Mantenimientos', href: '/admin/sti/mantenimientos' },
];

const columns: Column<StiMantenimiento>[] = [
    {
        key: 'equipo_id',
        label: 'Equipo',
        render: (m) => m.equipo?.descripcion ?? '-',
    },
    {
        key: 'tipo',
        label: 'Tipo',
        render: (m) => (
            <Badge variant={m.tipo === 'preventivo' ? 'success' : m.tipo === 'correctivo' ? 'error' : 'secondary'}>
                {m.tipo}
            </Badge>
        ),
    },
    {
        key: 'fecha_programada',
        label: 'Fecha Programada',
        render: (m) => new Date(m.fecha_programada).toLocaleDateString('es-MX'),
    },
    {
        key: 'tecnico_id',
        label: 'Técnico',
        render: (m) => m.tecnico?.descripcion ?? '-',
    },
];

type Props = {
    mantenimientos: PaginatedData<StiMantenimiento>;
    filters: { search?: string };
};

export default function MantenimientosIndex({ mantenimientos, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mantenimientos" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={mantenimientos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar mantenimientos..."
                    createHref="/admin/sti/mantenimientos/create"
                    createLabel="Nuevo Mantenimiento"
                    emptyMessage="No hay mantenimientos registrados"
                    getRowHref={(m) => `/admin/sti/mantenimientos/${m.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
