import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosNotaCredito, PaginatedData } from '@/types/models';
import { NOTA_CREDITO_ESTATUS_COLORS, NOTA_CREDITO_ESTATUS_LABELS } from '@/types/models';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/notas-credito' },
    { title: 'Notas de crédito', href: '/admin/costos/notas-credito' },
];

const formatMoney = (n: number) => `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;
const fmtDate = (d: string | null) => (d ? new Date(d).toLocaleDateString('es-MX') : '-');

const columns: Column<CostosNotaCredito>[] = [
    {
        key: 'folio',
        label: 'Folio',
        render: (r) => (
            <div>
                <span className="font-mono text-xs font-medium">{r.folio}</span>
                <div className="mt-0.5 text-[11px] text-base-content/50">{fmtDate(r.fecha_emision)}</div>
            </div>
        ),
    },
    {
        key: 'factura',
        label: 'Factura',
        render: (r) => (
            <div>
                <span className="font-mono text-xs">{r.factura?.folio ?? `#${r.factura_id}`}</span>
                <div className="mt-0.5 text-[11px] text-base-content/50">{r.factura?.proveedor?.razon_social ?? ''}</div>
            </div>
        ),
    },
    {
        key: 'concepto',
        label: 'Concepto',
        render: (r) => <span className="text-xs text-base-content/60">{r.concepto}</span>,
    },
    {
        key: 'monto',
        label: 'Monto',
        render: (r) => <span className="font-medium">{formatMoney(r.monto)}</span>,
    },
    {
        key: 'uuid_fiscal',
        label: 'UUID',
        render: (r) => (
            <span className="font-mono text-[10px] text-base-content/60">
                {r.uuid_fiscal ? `${r.uuid_fiscal.slice(0, 8)}...` : '-'}
            </span>
        ),
    },
    {
        key: 'estatus',
        label: 'Estatus',
        render: (r) => (
            <span className={`badge badge-sm ${NOTA_CREDITO_ESTATUS_COLORS[r.estatus]}`}>
                {NOTA_CREDITO_ESTATUS_LABELS[r.estatus]}
            </span>
        ),
    },
];

type Props = {
    notas: PaginatedData<CostosNotaCredito>;
    filters: { search?: string; estatus?: string; factura_id?: number };
};

export default function NotasCreditoIndex({ notas, filters }: Props) {
    const handleEstatus = (estatus: string) => {
        router.get('/admin/costos/notas-credito', { ...filters, estatus: estatus || undefined }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notas de crédito" />

            <div className="p-6">
                <div className="mb-4 flex items-center gap-3">
                    <select
                        className="select select-bordered select-sm"
                        value={filters.estatus ?? ''}
                        onChange={(e) => handleEstatus(e.target.value)}
                    >
                        <option value="">Todos los estatus</option>
                        {Object.entries(NOTA_CREDITO_ESTATUS_LABELS).map(([v, l]) => (
                            <option key={v} value={v}>{l}</option>
                        ))}
                    </select>
                </div>

                <DataTable
                    columns={columns}
                    data={notas}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por folio, UUID o concepto..."
                    emptyMessage="No hay notas de crédito registradas"
                    getRowHref={(r) => `/admin/costos/notas-credito/${r.id}`}
                />
            </div>
        </AppLayout>
    );
}
