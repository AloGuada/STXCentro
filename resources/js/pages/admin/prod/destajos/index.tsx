import { DataTable, type Column } from '@/components/data-table';
import { FormattedDate } from '@/components/ui/formatted-date';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, ProdDestajo } from '@/types/models';
import { Head } from '@inertiajs/react';
import { FileDownIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/destajos' },
    { title: 'Destajos', href: '/admin/prod/destajos' },
];

type Props = {
    destajos: PaginatedData<ProdDestajo>;
    filters: { search?: string };
};

export default function DestajosIndex({ destajos, filters }: Props) {
    const columns: Column<ProdDestajo>[] = [
        { key: 'anio', label: 'Año', className: 'font-mono' },
        {
            key: 'semana',
            label: 'Semana',
            render: (d) => <span className="font-medium">Semana {d.semana}</span>,
        },
        {
            key: 'periodo',
            label: 'Periodo',
            render: (d) => (
                <span className="text-base-content/70 font-mono text-sm">
                    <FormattedDate value={d.fecha_inicio} /> — <FormattedDate value={d.fecha_fin} />
                </span>
            ),
        },
        {
            key: 'cerrado',
            label: 'Estado',
            render: (d) => (
                <span className={`badge badge-sm ${d.cerrado ? 'badge-neutral' : 'badge-success'}`}>
                    {d.cerrado ? 'Cerrado' : 'Abierto'}
                </span>
            ),
        },
        {
            key: 'liquidaciones_count',
            label: 'Liquidaciones',
            className: 'text-right',
            render: (d) => <span className="font-mono">{d.liquidaciones_count ?? 0}</span>,
        },
        {
            key: 'acciones',
            label: '',
            className: 'text-right',
            render: (d) => (
                <a
                    href={`/admin/prod/destajos/${d.id}/orden-pago`}
                    target="_blank"
                    rel="noopener noreferrer"
                    onClick={(e) => e.stopPropagation()}
                    className="btn btn-ghost btn-xs"
                    title="Orden de pago (PDF)"
                >
                    <FileDownIcon className="size-4" /> Orden de pago
                </a>
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Destajos" />

            <div className="p-6">
                <div className="mb-4">
                    <h1 className="text-2xl font-semibold">Destajos</h1>
                    <p className="text-base-content/60 text-sm">Destajo semanal: produccion y pagos extra por grupo.</p>
                </div>

                <DataTable
                    columns={columns}
                    data={destajos}
                    searchable
                    searchPlaceholder="Buscar por año o semana..."
                    searchValue={filters.search}
                    createHref="/admin/prod/destajos/create"
                    createLabel="Nuevo destajo"
                    getRowHref={(d) => `/admin/prod/destajos/${d.id}`}
                    emptyMessage="No hay destajos registrados"
                />
            </div>
        </AppLayout>
    );
}
