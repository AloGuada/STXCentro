import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, ProdDestajo } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/destajos' },
    { title: 'Destajos', href: '/admin/prod/destajos' },
];

type DestajoWithAgg = ProdDestajo & {
    fabricados_count: number;
    pagos_extra_count: number;
    fabricados_sum_total_calculado: number | null;
};

const columns: Column<DestajoWithAgg>[] = [
    {
        key: 'semana',
        label: 'Semana',
        render: (d) => <span className="font-mono text-sm">{d.semana}</span>,
    },
    {
        key: 'fabricados_count',
        label: 'Fabricados',
        render: (d) => <span className="font-mono text-sm">{d.fabricados_count ?? 0}</span>,
    },
    {
        key: 'pagos_extra_count',
        label: 'Pagos Extra',
        render: (d) => <span className="font-mono text-sm">{d.pagos_extra_count ?? 0}</span>,
    },
    {
        key: 'fabricados_sum_total_calculado',
        label: 'Total ($)',
        render: (d) => (
            <span className="font-mono text-sm">
                ${Number(d.fabricados_sum_total_calculado ?? 0).toLocaleString('es-MX', { minimumFractionDigits: 2 })}
            </span>
        ),
    },
    {
        key: 'cantidad',
        label: 'Cantidad',
        render: (d) =>
            d.cerrada ? (
                <span className="font-mono text-sm">${Number(d.cantidad ?? 0).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</span>
            ) : (
                <span className="text-sm text-gray-400">-</span>
            ),
    },
    {
        key: 'cerrada',
        label: 'Estado',
        render: (d) => (
            <span className={`badge badge-sm ${d.cerrada ? 'badge-success' : 'badge-warning'}`}>{d.cerrada ? 'Cerrada' : 'Abierta'}</span>
        ),
    },
];

type Props = {
    destajos: PaginatedData<DestajoWithAgg>;
    filters: { search?: string };
};

export default function DestajosIndex({ destajos, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Destajos" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={destajos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por semana..."
                    createHref="/admin/prod/destajos/create"
                    createLabel="Nuevo Destajo"
                    emptyMessage="No hay destajos registrados"
                    getRowHref={(d) => `/admin/prod/destajos/${d.id}`}
                />
            </div>
        </AppLayout>
    );
}
