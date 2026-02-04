import { DataTable, type Column } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Area, Documento, PaginatedData, TipoDocumento } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Intranet', href: '/admin/intra/secciones' },
    { title: 'Documentos', href: '/admin/intra/documentos' },
];

const columns: Column<Documento>[] = [
    { key: 'descripcion', label: 'Descripción' },
    { key: 'codigo', label: 'Código' },
    {
        key: 'area_id',
        label: 'Área',
        render: (doc) => doc.area?.descripcion ?? '-',
    },
    { key: 'order', label: 'Orden' },
    {
        key: 'activo',
        label: 'Estado',
        render: (doc) => (
            <Badge variant={doc.activo ? 'success' : 'secondary'}>
                {doc.activo ? 'Activo' : 'Inactivo'}
            </Badge>
        ),
    },
];

type Props = {
    documentos: PaginatedData<Documento>;
    filters: { search?: string; area_id?: string; tipo?: string };
    areas: Pick<Area, 'id' | 'descripcion'>[];
    tipos: Record<TipoDocumento, string>;
};

export default function DocumentosIndex({ documentos, filters, areas, tipos }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Documentos" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={documentos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar documentos..."
                    createHref="/admin/intra/documentos/create"
                    createLabel="Nuevo Documento"
                    emptyMessage="No hay documentos registrados"
                    getRowHref={(doc) => `/admin/intra/documentos/${doc.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
