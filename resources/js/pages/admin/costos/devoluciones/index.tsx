import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosDevolucion, PaginatedData } from '@/types/models';
import { DEVOLUCION_ESTATUS_COLORS, DEVOLUCION_ESTATUS_LABELS } from '@/types/models';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/devoluciones' },
    { title: 'Devoluciones', href: '/admin/costos/devoluciones' },
];

const fmtDate = (d: string | null) => (d ? new Date(d).toLocaleDateString('es-MX') : '-');

const columns: Column<CostosDevolucion>[] = [
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
        key: 'oc',
        label: 'OC / Proveedor',
        render: (r) => (
            <div>
                <span className="font-mono text-xs">{r.entrega_detalle?.entrega?.orden_compra?.folio ?? '-'}</span>
                <div className="mt-0.5 text-[11px] text-base-content/50">
                    {r.entrega_detalle?.entrega?.orden_compra?.proveedor?.razon_social ?? ''}
                </div>
            </div>
        ),
    },
    {
        key: 'partida',
        label: 'Partida',
        render: (r) => (
            <span className="text-xs">
                {r.entrega_detalle?.orden_compra_detalle?.descripcion ?? '-'}
            </span>
        ),
    },
    {
        key: 'cantidad',
        label: 'Cantidad',
        sortable: true,
        render: (r) => (
            <span className="font-medium">
                {Number(r.cantidad).toLocaleString('es-MX')} {r.entrega_detalle?.orden_compra_detalle?.unidad ?? ''}
            </span>
        ),
    },
    {
        key: 'motivo',
        label: 'Motivo',
        sortable: true,
        render: (r) => <span className="text-xs text-base-content/60 line-clamp-2">{r.motivo}</span>,
    },
    {
        key: 'estatus',
        label: 'Estatus',
        sortable: true,
        render: (r) => (
            <span className={`badge badge-sm ${DEVOLUCION_ESTATUS_COLORS[r.estatus]}`}>
                {DEVOLUCION_ESTATUS_LABELS[r.estatus]}
            </span>
        ),
    },
];

type Props = {
    devoluciones: PaginatedData<CostosDevolucion>;
    filters: { search?: string; estatus?: string };
    sortBy?: string;
    sortDir?: 'asc' | 'desc';
};

export default function DevolucionesIndex({ devoluciones, filters, sortBy, sortDir }: Props) {
    const handleEstatus = (estatus: string) => {
        router.get('/admin/costos/devoluciones', { ...filters, estatus: estatus || undefined }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Devoluciones" />

            <div className="p-6">
                <div className="mb-4 flex items-center gap-3">
                    <select
                        className="select select-bordered select-sm"
                        value={filters.estatus ?? ''}
                        onChange={(e) => handleEstatus(e.target.value)}
                    >
                        <option value="">Todos los estatus</option>
                        {Object.entries(DEVOLUCION_ESTATUS_LABELS).map(([v, l]) => (
                            <option key={v} value={v}>{l}</option>
                        ))}
                    </select>
                </div>

                <DataTable
                    columns={columns}
                    data={devoluciones}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por folio o motivo..."
                    createHref="/admin/costos/devoluciones/create"
                    createLabel="Nueva devolución"
                    emptyMessage="No hay devoluciones registradas"
                    getRowHref={(r) => `/admin/costos/devoluciones/${r.id}`}
                    sortBy={sortBy}
                    sortDir={sortDir}
                />
            </div>
        </AppLayout>
    );
}
