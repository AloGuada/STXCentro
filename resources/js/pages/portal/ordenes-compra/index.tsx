import { DataTable, type Column } from '@/components/data-table';
import PortalLayout from '@/layouts/portal/portal-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosOrdenCompra, PaginatedData } from '@/types/models';
import { ORDEN_COMPRA_ESTATUS_COLORS, ORDEN_COMPRA_ESTATUS_LABELS } from '@/types/models';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/portal' },
    { title: 'Ordenes de Compra', href: '/portal/ordenes-compra' },
];

const columns: Column<CostosOrdenCompra>[] = [
    { key: 'folio', label: 'Folio' },
    {
        key: 'obra',
        label: 'Obra',
        render: (row) => row.obra?.descripcion ?? '-',
    },
    {
        key: 'total',
        label: 'Total',
        render: (row) => `$${Number(row.total).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`,
    },
    {
        key: 'facturas_count',
        label: 'Facturas',
        render: (row) => String(row.facturas_count ?? 0),
    },
    {
        key: 'estatus',
        label: 'Estatus',
        render: (row) => (
            <span className={`badge ${ORDEN_COMPRA_ESTATUS_COLORS[row.estatus]}`}>{ORDEN_COMPRA_ESTATUS_LABELS[row.estatus]}</span>
        ),
    },
];

const estatusOptions = [
    { value: '', label: 'Todos' },
    { value: 'pendiente_factura', label: 'Pend. Factura' },
    { value: 'pendiente_entrega', label: 'Pend. Entrega' },
    { value: 'pendiente_aprobacion', label: 'Pend. Aprobación' },
    { value: 'pendiente_pago', label: 'Pend. Pago' },
    { value: 'pagada', label: 'Pagada' },
];

type Props = {
    ordenes: PaginatedData<CostosOrdenCompra>;
    filters: { search?: string; estatus?: string };
};

export default function PortalOrdenesCompraIndex({ ordenes, filters }: Props) {
    const handleEstatusChange = (estatus: string) => {
        router.get('/portal/ordenes-compra', { ...filters, estatus: estatus || undefined }, { preserveState: true });
    };

    return (
        <PortalLayout breadcrumbs={breadcrumbs}>
            <Head title="Mis Ordenes de Compra" />

            <div className="p-6">
                <h1 className="text-2xl font-semibold mb-4">Mis Ordenes de Compra</h1>

                <div className="mb-4 flex items-center gap-4">
                    <select
                        className="select select-bordered select-sm"
                        value={filters.estatus ?? ''}
                        onChange={(e) => handleEstatusChange(e.target.value)}
                    >
                        {estatusOptions.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                </div>

                <DataTable
                    columns={columns}
                    data={ordenes}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por folio..."
                    emptyMessage="No hay ordenes de compra"
                    getRowHref={(row) => `/portal/ordenes-compra/${row.id}`}
                />
            </div>
        </PortalLayout>
    );
}
