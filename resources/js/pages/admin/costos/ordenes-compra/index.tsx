import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosOrdenCompra, CostosOrdenCompraEstatus, PaginatedData } from '@/types/models';
import { ORDEN_COMPRA_ESTATUS_COLORS, ORDEN_COMPRA_ESTATUS_LABELS } from '@/types/models';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/ordenes-compra' },
    { title: 'Ordenes de Compra', href: '/admin/costos/ordenes-compra' },
];

const columns: Column<CostosOrdenCompra>[] = [
    { key: 'folio', label: 'Folio' },
    {
        key: 'referencia',
        label: 'Referencia',
        render: (row) => row.referencia ?? '-',
    },
    {
        key: 'proveedor',
        label: 'Proveedor',
        render: (row) => row.proveedor?.razon_social ?? '-',
    },
    {
        key: 'facturas_count',
        label: 'Facturas',
        render: (row) => row.facturas_count ?? 0,
    },
    {
        key: 'total',
        label: 'Total',
        render: (row) => `$${Number(row.total).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`,
    },
    {
        key: 'estatus',
        label: 'Estatus',
        render: (row) => (
            <span className={`badge ${ORDEN_COMPRA_ESTATUS_COLORS[row.estatus]}`}>
                {ORDEN_COMPRA_ESTATUS_LABELS[row.estatus]}
            </span>
        ),
    },
    {
        key: 'created_at',
        label: 'Fecha',
        render: (row) => new Date(row.created_at).toLocaleDateString(),
    },
];

const estatusOptions = [
    { value: '', label: 'Todos' },
    { value: 'pendiente_factura', label: 'Pend. Factura' },
    { value: 'pendiente_entrega', label: 'Pend. Entrega' },
    { value: 'pendiente_aprobacion', label: 'Pend. Aprobación' },
    { value: 'pendiente_pago', label: 'Pend. Pago' },
    { value: 'pagada', label: 'Pagada' },
    { value: 'cancelada', label: 'Cancelada' },
];

type Props = {
    ordenes: PaginatedData<CostosOrdenCompra>;
    filters: { search?: string; estatus?: string };
};

export default function OrdenesCompraIndex({ ordenes, filters }: Props) {
    const handleEstatusChange = (estatus: string) => {
        router.get('/admin/costos/ordenes-compra', { ...filters, estatus: estatus || undefined }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ordenes de Compra" />

            <div className="p-6">
                <div className="mb-4 flex items-center gap-4">
                    <select
                        className="select select-bordered select-sm"
                        value={filters.estatus ?? ''}
                        onChange={(e) => handleEstatusChange(e.target.value)}
                    >
                        {estatusOptions.map((opt) => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                    </select>
                </div>

                <DataTable
                    columns={columns}
                    data={ordenes}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por folio o proveedor..."
                    createHref="/admin/costos/ordenes-compra/create"
                    createLabel="Nueva Orden"
                    emptyMessage="No hay ordenes de compra"
                    getRowHref={(row) => `/admin/costos/ordenes-compra/${row.id}`}
                />
            </div>
        </AppLayout>
    );
}
