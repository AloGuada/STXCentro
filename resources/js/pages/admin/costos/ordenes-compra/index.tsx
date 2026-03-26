import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosOrdenCompra, PaginatedData } from '@/types/models';
import { ORDEN_COMPRA_ESTATUS_COLORS, ORDEN_COMPRA_ESTATUS_LABELS } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/ordenes-compra' },
    { title: 'Compras', href: '/admin/costos/ordenes-compra' },
];

const fmt = (v: number) => {
    const n = Number(v);
    if (n >= 1_000_000) return `$${(n / 1_000_000).toFixed(1)}M`;
    if (n >= 1_000) return `$${(n / 1_000).toFixed(1)}k`;
    return `$${n.toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;
};

const columns: Column<CostosOrdenCompra>[] = [
    {
        key: 'folio',
        label: 'Orden de Compra',
        render: (row) => (
            <div>
                <div className="font-semibold text-primary">{row.folio}</div>
                <div className="text-base-content/50 text-xs">
                    {row.proveedor?.razon_social ?? '-'} · {new Date(row.created_at).toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' })}
                </div>
            </div>
        ),
    },
    {
        key: 'total',
        label: 'Monto',
        render: (row) => <span className="text-sm font-semibold">{fmt(row.total)}</span>,
    },
    {
        key: 'estatus',
        label: 'Estado',
        render: (row) => (
            <span className={`badge badge-sm ${ORDEN_COMPRA_ESTATUS_COLORS[row.estatus]}`}>
                {ORDEN_COMPRA_ESTATUS_LABELS[row.estatus]}
            </span>
        ),
    },
    {
        key: 'counts',
        label: 'Facturas / Entregas / Pagos',
        render: (row) => (
            <div className="flex flex-wrap gap-1.5">
                <Link
                    href={`/admin/costos/facturas?orden_compra_id=${row.id}`}
                    className="rounded bg-base-200 px-2 py-0.5 text-xs hover:bg-primary/10 transition-colors"
                    onClick={(e) => e.stopPropagation()}
                >
                    <span className="font-semibold">{row.facturas_count ?? 0}</span> facturas
                </Link>
                <Link
                    href={`/admin/costos/facturas?orden_compra_id=${row.id}`}
                    className="rounded bg-base-200 px-2 py-0.5 text-xs hover:bg-primary/10 transition-colors"
                    onClick={(e) => e.stopPropagation()}
                >
                    <span className="font-semibold">{row.entregas_count ?? 0}</span> entregas
                </Link>
                <Link
                    href={`/admin/costos/pagos?orden_compra_id=${row.id}`}
                    className="rounded bg-base-200 px-2 py-0.5 text-xs hover:bg-primary/10 transition-colors"
                    onClick={(e) => e.stopPropagation()}
                >
                    <span className="font-semibold">{row.pagos_count ?? 0}</span> pagos
                </Link>
            </div>
        ),
    },
    {
        key: 'acciones',
        label: '',
        render: (row) => (
            <Link
                href={`/admin/costos/ordenes-compra/${row.id}`}
                className="btn btn-ghost btn-xs gap-1"
                onClick={(e) => e.stopPropagation()}
            >
                Ver detalle <ArrowRight className="size-3" />
            </Link>
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
            <Head title="Compras — Órdenes de Compra" />

            <div className="p-6">
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
                >
                    <select
                        className="select select-bordered select-sm"
                        value={filters.estatus ?? ''}
                        onChange={(e) => handleEstatusChange(e.target.value)}
                    >
                        {estatusOptions.map((opt) => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                    </select>
                </DataTable>
            </div>
        </AppLayout>
    );
}
