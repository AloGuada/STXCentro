import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, ProdCorte } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/cortes' },
    { title: 'Cortes', href: '/admin/prod/cortes' },
];

type CorteRow = ProdCorte & { liquidaciones_count: number };

const columns: Column<CorteRow>[] = [
    {
        key: 'semana',
        label: 'Semana',
        render: (c) => <span className="font-mono text-sm font-semibold">{c.semana}</span>,
    },
    {
        key: 'fecha_inicio',
        label: 'Periodo',
        render: (c) => <span className="text-sm">{c.fecha_inicio} — {c.fecha_fin}</span>,
    },
    {
        key: 'cerrado',
        label: 'Estado',
        render: (c) => (
            <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${c.cerrado ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'}`}>
                {c.cerrado ? 'Cerrado' : 'Abierto'}
            </span>
        ),
    },
    {
        key: 'liquidaciones_count',
        label: 'Liquidaciones',
        render: (c) => <span className="font-mono text-sm">{c.liquidaciones_count}</span>,
    },
];

type Props = {
    cortes: PaginatedData<CorteRow>;
    filters: { search?: string };
};

export default function CortesIndex({ cortes, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Cortes" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={cortes}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por semana..."
                    createHref="/admin/prod/cortes/create"
                    createLabel="Nuevo Corte"
                    emptyMessage="No hay cortes registrados"
                    getRowHref={(c) => `/admin/prod/cortes/${c.id}`}
                />
            </div>
        </AppLayout>
    );
}
