import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizObra, PaginatedData } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { LayersIcon, SlidersHorizontalIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/obras' },
    { title: 'Obras', href: '/admin/cotiz/obras' },
];

const columns: Column<CotizObra>[] = [
    { key: 'nombre', label: 'Nombre' },
    {
        key: 'op',
        label: 'OP',
        render: (o) => o.op ?? '-',
    },
    {
        key: 'factor_contratista',
        label: 'Factor contratista',
        render: (o) => Number(o.factor_contratista).toFixed(2),
    },
    { key: 'num_grupos', label: 'Grupos' },
    {
        key: 'generadoras_count',
        label: 'Generadoras',
        render: (o) => (
            <div className="flex items-center gap-2">
                <span className="badge badge-ghost badge-sm">
                    {o.generadoras_count ?? 0}
                </span>
                <Link
                    href={`/admin/cotiz/obras/${o.id}/generadoras`}
                    className="inline-flex link items-center gap-1 text-xs link-primary"
                    onClick={(e) => e.stopPropagation()}
                >
                    <LayersIcon className="size-3.5" />
                    Ver generadoras
                </Link>
            </div>
        ),
    },
    {
        key: 'catalogo',
        label: 'Catálogo',
        render: (o) => (
            <Link
                href={`/admin/cotiz/obras/${o.id}/catalogo`}
                className="inline-flex link items-center gap-1 text-xs link-primary"
                onClick={(e) => e.stopPropagation()}
            >
                <SlidersHorizontalIcon className="size-3.5" />
                Overrides
            </Link>
        ),
    },
];

type Props = {
    obras: PaginatedData<CotizObra>;
    filters: { search?: string };
};

export default function ObrasIndex({ obras, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Obras" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={obras}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar obras..."
                    createHref="/admin/cotiz/obras/create"
                    createLabel="Nueva obra"
                    emptyMessage="No hay obras registradas"
                    getRowHref={(o) => `/admin/cotiz/obras/${o.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
