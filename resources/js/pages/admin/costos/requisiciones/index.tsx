import { Head, router } from '@inertiajs/react';
import { formatMoney } from '@/components/costos/monto';
import OcsAdjudicadas from '@/components/costos/ocs-adjudicadas';
import { DataTable, type Column } from '@/components/data-table';
import { formatDate } from '@/components/ui/formatted-date';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosRequisicion, Departamento, PaginatedData } from '@/types/models';
import { REQUISICION_ESTATUS_COLORS, REQUISICION_ESTATUS_LABELS } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/requisiciones' },
    { title: 'Requisiciones', href: '/admin/costos/requisiciones' },
];

const fmtDate = (date: string | null) =>
    date ? new Date(date).toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '-';

/**
 * Hay OCs en divisa y la requisición no tiene tipo de cambio capturado.
 * `tipo_cambio` nace en 1, así que un 1 con divisa de por medio es un TC que
 * nadie capturó, no una paridad real.
 */
const faltaTipoCambio = (row: CostosRequisicion) =>
    (row.ocs_resumen ?? []).some((oc) => (oc.moneda ?? 'mxn').toLowerCase() !== 'mxn') && !(Number(row.tipo_cambio) > 1);

const AvisoSinTc = () => (
    <div
        className="mt-0.5 text-[11px] font-semibold text-warning"
        title="Hay cotizaciones en divisa y la requisición no tiene tipo de cambio capturado: el monto está sin convertir"
    >
        Falta tipo de cambio
    </div>
);

const columns: Column<CostosRequisicion>[] = [
    {
        key: 'folio',
        label: 'Folio',
        sortable: true,
        render: (row) => (
            <div>
                <span className="font-mono text-xs font-medium">{row.folio}</span>
                <div className="mt-0.5 text-[11px] text-base-content/50">{fmtDate(row.created_at)}</div>
            </div>
        ),
    },
    {
        key: 'solicitante',
        label: 'Solicitante',
        sortable: true,
        render: (row) => (
            <div>
                <div className="text-sm">{row.solicitante?.name ?? '-'}</div>
                <div className="mt-0.5 text-[11px] text-base-content/50">{row.departamento?.descripcion ?? ''}</div>
            </div>
        ),
    },
    {
        key: 'fecha_requerida',
        label: 'Fecha requerida',
        sortable: true,
        render: (row) => <span className="text-xs text-base-content/60">{formatDate(row.fecha_requerida) ?? '-'}</span>,
    },
    {
        key: 'mejor_proveedor',
        label: 'Monto',
        render: (row) => {
            const cotCount = row.proveedores_cotizadores_count ?? 0;
            const ocs = row.ocs_resumen ?? [];

            // Ya hay proveedor adjudicado: manda lo seleccionado en la(s) OC(s),
            // no el mejor precio del comparativo.
            if (ocs.length > 0) {
                return (
                    <div>
                        <OcsAdjudicadas ocs={ocs} />
                        <div className="mt-0.5 text-xs font-semibold text-success">{formatMoney(row.total_neto ?? 0)} MXN</div>
                        {faltaTipoCambio(row) && <AvisoSinTc />}
                        <div className="mt-0.5 text-[11px] text-base-content/50">Neto a pagar</div>
                    </div>
                );
            }

            if (!row.mejor_proveedor) {
                return (
                    <div>
                        <div className="text-xs text-base-content/40">—</div>
                        <div className="mt-0.5 text-[11px] text-base-content/50">
                            {cotCount > 0 ? `${cotCount} ${cotCount === 1 ? 'proveedor' : 'proveedores'} (parcial)` : 'Sin cotizaciones'}
                        </div>
                    </div>
                );
            }

            const m = row.mejor_proveedor;
            return (
                <div>
                    <div className="text-sm font-medium">{m.nombre_comercial || m.razon_social}</div>
                    <div className="mt-0.5 text-xs font-semibold text-success">{formatMoney(m.total)} MXN</div>
                    {m.falta_tc && <AvisoSinTc />}
                    <div className="mt-0.5 text-[11px] text-base-content/50">
                        Mejor precio · {cotCount} {cotCount === 1 ? 'proveedor cotizó' : 'proveedores cotizaron'}
                    </div>
                </div>
            );
        },
    },
    {
        key: 'estatus',
        label: 'Estatus',
        sortable: true,
        render: (row) => (
            <span className={`badge badge-sm ${REQUISICION_ESTATUS_COLORS[row.estatus]}`}>
                {REQUISICION_ESTATUS_LABELS[row.estatus]}
            </span>
        ),
    },
];

type Props = {
    requisiciones: PaginatedData<CostosRequisicion>;
    filters: { search?: string; estatus?: string; departamento_id?: number };
    departamentos: Pick<Departamento, 'id' | 'descripcion'>[];
    sortBy?: string;
    sortDir?: 'asc' | 'desc';
};

export default function RequisicionesIndex({ requisiciones, filters, departamentos, sortBy, sortDir }: Props) {
    const handleEstatusChange = (estatus: string) => {
        router.get('/admin/costos/requisiciones', { ...filters, estatus: estatus || undefined }, { preserveState: true });
    };

    const handleDeptoChange = (departamentoId: string) => {
        router.get('/admin/costos/requisiciones', { ...filters, departamento_id: departamentoId || undefined }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Requisiciones" />

            <div className="p-6">
                <div className="mb-4 flex items-center gap-3">
                    <select
                        className="select select-bordered select-sm"
                        value={filters.estatus ?? ''}
                        onChange={(e) => handleEstatusChange(e.target.value)}
                    >
                        <option value="">Todos los estatus</option>
                        {Object.entries(REQUISICION_ESTATUS_LABELS).map(([value, label]) => (
                            <option key={value} value={value}>{label}</option>
                        ))}
                    </select>

                    <select
                        className="select select-bordered select-sm"
                        value={filters.departamento_id ?? ''}
                        onChange={(e) => handleDeptoChange(e.target.value)}
                    >
                        <option value="">Todos los departamentos</option>
                        {departamentos.map((d) => (
                            <option key={d.id} value={d.id}>{d.descripcion}</option>
                        ))}
                    </select>
                </div>

                <DataTable
                    columns={columns}
                    data={requisiciones}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por folio, solicitante o proveedor..."
                    createHref="/admin/costos/requisiciones/create"
                    createLabel="Nueva requisición"
                    emptyMessage="No hay requisiciones"
                    getRowHref={(row) => `/admin/costos/requisiciones/${row.id}`}
                    sortBy={sortBy}
                    sortDir={sortDir}
                />
            </div>
        </AppLayout>
    );
}
