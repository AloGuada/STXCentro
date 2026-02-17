import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra, PaginatedData } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/cortes' },
    { title: 'Conceptos', href: '/admin/prod/conceptos' },
];

type ObraRow = Obra & { conceptos_count: number; conceptos_activos_count: number };

const columns: Column<ObraRow>[] = [
    { key: 'no', label: 'No. Obra' },
    { key: 'descripcion', label: 'Descripcion' },
    {
        key: 'estatus',
        label: 'Activos',
        render: (obra) => <span className="font-mono text-sm">{obra.conceptos_activos_count}</span>,
    },
    {
        key: 'id',
        label: 'Total',
        render: (obra) => <span className="font-mono text-sm">{obra.conceptos_count}</span>,
    },
];

type Props = {
    obras: PaginatedData<ObraRow>;
    filters: { search?: string };
};

export default function ConceptosIndex({ obras, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Conceptos" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={obras}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar obra..."
                    emptyMessage="No hay obras registradas"
                    getRowHref={(obra) => `/admin/prod/conceptos/obra/${obra.id}`}
                />
            </div>
        </AppLayout>
    );
}
