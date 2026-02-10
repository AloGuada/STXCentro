import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, ProdTipo } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/destajos' },
    { title: 'Tipos Pago', href: '/admin/prod/tipos' },
];

type TipoWithCounts = ProdTipo & {
    pagos_extra_count: number;
};

const columns: Column<TipoWithCounts>[] = [
    { key: 'descripcion', label: 'Descripcion' },
    {
        key: 'pagos_extra_count',
        label: 'Pagos Extra',
        render: (tipo) => <span className="font-mono text-sm">{tipo.pagos_extra_count ?? 0}</span>,
    },
];

type Props = {
    tipos: PaginatedData<TipoWithCounts>;
    filters: { search?: string };
};

export default function TiposIndex({ tipos, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tipos de Pago" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={tipos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar tipos..."
                    createHref="/admin/prod/tipos/create"
                    createLabel="Nuevo Tipo"
                    emptyMessage="No hay tipos registrados"
                    getRowHref={(tipo) => `/admin/prod/tipos/${tipo.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
