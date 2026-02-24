import { calcularDatosProyecto } from '@/components/cob/calculos';
import { formatearMXN } from '@/components/cob/money-display';
import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra, PaginatedData } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cobranza', href: '/admin/cob/dashboard' },
    { title: 'Obras', href: '/admin/cob/obras' },
];

const columns: Column<Obra>[] = [
    { key: 'no', label: 'No' },
    { key: 'descripcion', label: 'Descripcion' },
    {
        key: 'cliente',
        label: 'Cliente',
        render: (obra) => obra.cliente?.nombre ?? '-',
    },
    {
        key: 'facturado',
        label: 'Facturado',
        className: 'text-right',
        render: (obra) => {
            const datos = calcularDatosProyecto(obra);
            return formatearMXN(datos.totalFacturado);
        },
    },
    {
        key: 'cobrado',
        label: 'Cobrado',
        className: 'text-right',
        render: (obra) => {
            const datos = calcularDatosProyecto(obra);
            return formatearMXN(datos.totalCobrado);
        },
    },
    {
        key: 'por_cobrar',
        label: 'Por Cobrar',
        className: 'text-right',
        render: (obra) => {
            const datos = calcularDatosProyecto(obra);
            return formatearMXN(datos.porCobrar);
        },
    },
];

type Props = {
    obras: PaginatedData<Obra>;
    filters: { search?: string };
};

export default function ObrasIndex({ obras, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Obras - Cobranza" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={obras}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar obras..."
                    emptyMessage="No hay obras registradas"
                    getRowHref={(obra) => `/admin/cob/obras/${obra.id}`}
                />
            </div>
        </AppLayout>
    );
}
