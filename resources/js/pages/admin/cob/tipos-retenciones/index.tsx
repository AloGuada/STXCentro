import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CobTipoRetencion, PaginatedData } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cobranza', href: '/admin/cob/dashboard' },
    { title: 'Tipos Retenciones', href: '/admin/cob/tipos-retenciones' },
];

const columns: Column<CobTipoRetencion>[] = [
    { key: 'nombre', label: 'Nombre' },
    { key: 'descripcion', label: 'Descripcion' },
    {
        key: 'retenciones_count',
        label: 'Retenciones',
        render: (tipo) => tipo.retenciones_count ?? 0,
    },
];

type Props = {
    tiposRetenciones: PaginatedData<CobTipoRetencion>;
    filters: { search?: string };
};

export default function TiposRetencionesIndex({ tiposRetenciones, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tipos de Retenciones" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={tiposRetenciones}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar tipos de retenciones..."
                    createHref="/admin/cob/tipos-retenciones/create"
                    createLabel="Nuevo Tipo Retencion"
                    emptyMessage="No hay tipos de retenciones registrados"
                    getRowHref={(tipo) => `/admin/cob/tipos-retenciones/${tipo.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
