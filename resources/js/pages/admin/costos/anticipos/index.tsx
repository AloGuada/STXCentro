import { formatMoney as fmtMonto } from '@/components/costos/monto';
import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosAnticipo, PaginatedData, Proveedor } from '@/types/models';
import { ANTICIPO_ESTATUS_COLORS, ANTICIPO_ESTATUS_LABELS } from '@/types/models';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/anticipos' },
    { title: 'Anticipos', href: '/admin/costos/anticipos' },
];

const fmtDate = (d: string | null) => (d ? new Date(d).toLocaleDateString('es-MX') : '-');

const columns: Column<CostosAnticipo>[] = [
    {
        key: 'folio',
        label: 'Folio',
        sortable: true,
        render: (r) => (
            <div>
                <span className="font-mono text-xs font-medium">{r.folio}</span>
                <div className="mt-0.5 text-[11px] text-base-content/50">{fmtDate(r.fecha)}</div>
            </div>
        ),
    },
    {
        key: 'proveedor',
        label: 'Proveedor',
        sortable: true,
        render: (r) => <span className="text-sm">{r.proveedor?.razon_social ?? '-'}</span>,
    },
    {
        key: 'monto',
        label: 'Monto',
        sortable: true,
        render: (r) => <span className="font-medium">{fmtMonto(r.monto, r.moneda)}</span>,
    },
    {
        key: 'saldo_disponible',
        label: 'Saldo disponible',
        sortable: true,
        render: (r) => <span className="font-medium text-success">{fmtMonto(r.saldo_disponible, r.moneda)}</span>,
    },
    {
        key: 'moneda',
        label: 'Moneda',
        sortable: true,
        render: (r) => <span className="text-xs uppercase">{r.moneda}</span>,
    },
    {
        key: 'estatus',
        label: 'Estatus',
        sortable: true,
        render: (r) => (
            <span className={`badge badge-sm ${ANTICIPO_ESTATUS_COLORS[r.estatus]}`}>
                {ANTICIPO_ESTATUS_LABELS[r.estatus]}
            </span>
        ),
    },
];

type Props = {
    anticipos: PaginatedData<CostosAnticipo>;
    filters: { search?: string; estatus?: string; proveedor_id?: number };
    proveedores: Pick<Proveedor, 'id' | 'razon_social'>[];
    sortBy?: string;
    sortDir?: 'asc' | 'desc';
};

export default function AnticiposIndex({ anticipos, filters, proveedores, sortBy, sortDir }: Props) {
    const handleEstatus = (estatus: string) => {
        router.get('/admin/costos/anticipos', { ...filters, estatus: estatus || undefined }, { preserveState: true });
    };

    const handleProveedor = (id: string) => {
        router.get('/admin/costos/anticipos', { ...filters, proveedor_id: id || undefined }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Anticipos" />

            <div className="p-6">
                <div className="mb-4 flex flex-wrap items-center gap-3">
                    <select
                        className="select select-bordered select-sm"
                        value={filters.estatus ?? ''}
                        onChange={(e) => handleEstatus(e.target.value)}
                    >
                        <option value="">Todos los estatus</option>
                        {Object.entries(ANTICIPO_ESTATUS_LABELS).map(([v, l]) => (
                            <option key={v} value={v}>{l}</option>
                        ))}
                    </select>

                    <select
                        className="select select-bordered select-sm"
                        value={filters.proveedor_id ?? ''}
                        onChange={(e) => handleProveedor(e.target.value)}
                    >
                        <option value="">Todos los proveedores</option>
                        {proveedores.map((p) => (
                            <option key={p.id} value={p.id}>{p.razon_social}</option>
                        ))}
                    </select>
                </div>

                <DataTable
                    columns={columns}
                    data={anticipos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por folio, referencia o proveedor..."
                    createHref="/admin/costos/anticipos/create"
                    createLabel="Nuevo anticipo"
                    emptyMessage="No hay anticipos registrados"
                    getRowHref={(r) => `/admin/costos/anticipos/${r.id}`}
                    sortBy={sortBy}
                    sortDir={sortDir}
                />
            </div>
        </AppLayout>
    );
}
