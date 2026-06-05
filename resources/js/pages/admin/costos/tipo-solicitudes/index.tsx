import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosTipoSolicitud, PaginatedData } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/tipo-solicitudes' },
    { title: 'Tipo Solicitudes', href: '/admin/costos/tipo-solicitudes' },
];

const columns: Column<CostosTipoSolicitud>[] = [
    { key: 'titulo', label: 'Título' },
    {
        key: 'rubros',
        label: 'Centros de Costos',
        render: (ts) => (
            <span className={`badge badge-sm ${ts.rubros ? 'badge-success' : 'badge-ghost'}`}>
                {ts.rubros ? 'Sí' : 'No'}
            </span>
        ),
    },
    {
        key: 'documentos_count',
        label: 'Documentos',
        render: (ts) => ts.documentos_count ?? 0,
    },
    {
        key: 'created_at',
        label: 'Creado',
        render: (ts) => new Date(ts.created_at).toLocaleDateString(),
    },
];

type Props = {
    tipoSolicitudes: PaginatedData<CostosTipoSolicitud>;
    filters: { search?: string };
};

export default function TipoSolicitudesIndex({ tipoSolicitudes, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tipo Solicitudes" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={tipoSolicitudes}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar tipo solicitudes..."
                    createHref="/admin/costos/tipo-solicitudes/create"
                    createLabel="Nuevo Tipo Solicitud"
                    emptyMessage="No hay tipos de solicitud registrados"
                    getRowHref={(ts) => `/admin/costos/tipo-solicitudes/${ts.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
