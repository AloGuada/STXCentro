import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosFactura, CostosFacturaEstatus, PaginatedData } from '@/types/models';
import { FACTURA_ESTATUS_COLORS, FACTURA_ESTATUS_LABELS } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import { FileTextIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/facturas' },
    { title: 'Facturas', href: '/admin/costos/facturas' },
];

const columns: Column<CostosFactura>[] = [
    { key: 'folio', label: 'Folio' },
    {
        key: 'orden_compra',
        label: 'OC',
        render: (row) => row.orden_compra?.folio ?? '-',
    },
    {
        key: 'media_pdf',
        label: 'PDF',
        render: (row) =>
            row.media_pdf ? (
                <a
                    href={`/storage/${row.media_pdf.path}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    onClick={(e) => e.stopPropagation()}
                    className="btn btn-ghost btn-xs"
                    title="Ver factura PDF"
                >
                    <FileTextIcon className="size-4" />
                </a>
            ) : (
                <span className="text-base-content/30">—</span>
            ),
        className: 'w-16 text-center',
    },
    {
        key: 'proveedor',
        label: 'Proveedor',
        render: (row) => row.proveedor?.razon_social ?? '-',
    },

    {
        key: 'fecha_factura',
        label: 'Fecha',
        render: (row) => row.fecha_factura ? new Date(row.fecha_factura).toLocaleDateString() : '-',
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
            <span className={`badge ${FACTURA_ESTATUS_COLORS[row.estatus]}`}>
                {FACTURA_ESTATUS_LABELS[row.estatus]}
            </span>
        ),
    },
];

const estatusOptions = [
    { value: '', label: 'Todos' },
    { value: 'pendiente_entrega', label: 'Pendiente Entrega' },
    { value: 'pendiente_pago', label: 'Pendiente Pago' },
    { value: 'pagada', label: 'Pagada' },
    { value: 'cancelada', label: 'Cancelada' },
];

type Props = {
    facturas: PaginatedData<CostosFactura>;
    filters: { search?: string; estatus?: string };
};

export default function FacturasIndex({ facturas, filters }: Props) {
    const handleEstatusChange = (estatus: string) => {
        router.get('/admin/costos/facturas', { ...filters, estatus: estatus || undefined }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Facturas" />

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
                    data={facturas}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por folio o proveedor..."
                    emptyMessage="No hay facturas"
                    getRowHref={(row) => `/admin/costos/facturas/${row.id}`}
                />
            </div>
        </AppLayout>
    );
}
