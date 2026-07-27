import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra, PaginatedData } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/destajos' },
    { title: 'Conceptos', href: '/admin/prod/conceptos' },
];

type ObraRow = Obra & { conceptos_count: number; conceptos_activos_count: number };

const columns: Column<ObraRow>[] = [
    { key: 'no', label: 'No. Obra' },
    { key: 'descripcion', label: 'Descripcion' },
    {
        key: 'conceptos_activos_count',
        label: 'Activos',
        className: 'text-right',
        render: (obra) => <span className="font-mono text-sm">{obra.conceptos_activos_count}</span>,
    },
    {
        key: 'conceptos_count',
        label: 'Total',
        className: 'text-right',
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
            <Head title="Conceptos / Piezas" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Conceptos / Piezas</h1>
                    <p className="mt-1 text-sm text-base-content/60">Catálogo de piezas por obra.</p>
                </div>

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
