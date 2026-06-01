import { DataTable, type Column } from '@/components/data-table';
import PortalLayout from '@/layouts/portal/portal-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosFactura, PaginatedData } from '@/types/models';
import { FACTURA_ESTATUS_COLORS, FACTURA_ESTATUS_LABELS } from '@/types/models';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/portal' },
    { title: 'Facturas', href: '/portal/facturas' },
];

const columns: Column<CostosFactura>[] = [
    { key: 'folio', label: 'Folio' },
    {
        key: 'orden_compra',
        label: 'OC',
        render: (row) => row.orden_compra?.folio ?? '-',
    },
    {
        key: 'fecha_factura',
        label: 'Fecha',
        render: (row) => (row.fecha_factura ? new Date(row.fecha_factura).toLocaleDateString() : '-'),
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
            <span className={`badge ${FACTURA_ESTATUS_COLORS[row.estatus]}`}>{FACTURA_ESTATUS_LABELS[row.estatus]}</span>
        ),
    },
];

const estatusOptions = [
    { value: '', label: 'Todos' },
    { value: 'pendiente_aprobacion', label: 'Pendiente Aprobación' },
    { value: 'pendiente_pago', label: 'Pendiente Pago' },
    { value: 'pagada', label: 'Pagada' },
    { value: 'cancelada', label: 'Cancelada' },
];

type Props = {
    facturas: PaginatedData<CostosFactura>;
    filters: { search?: string; estatus?: string };
};

export default function PortalFacturasIndex({ facturas, filters }: Props) {
    const handleEstatusChange = (estatus: string) => {
        router.get('/portal/facturas', { ...filters, estatus: estatus || undefined }, { preserveState: true });
    };

    return (
        <PortalLayout breadcrumbs={breadcrumbs}>
            <Head title="Mis Facturas" />

            <div className="p-6">
                <h1 className="text-2xl font-semibold mb-4">Mis Facturas</h1>

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
                    data={facturas}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por folio..."
                    emptyMessage="No hay facturas"
                    getRowHref={(row) => `/portal/facturas/${row.id}`}
                />
            </div>
        </PortalLayout>
    );
}
