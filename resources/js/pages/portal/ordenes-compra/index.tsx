import { formatMoney as fmtMonto } from '@/components/costos/monto';
import PortalLayout from '@/layouts/portal/portal-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosFactura, CostosOcEtapaProceso, CostosOrdenCompra, PaginatedData } from '@/types/models';
import { OC_ETAPA_BADGE, OC_ETAPA_LABELS } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/portal' },
    { title: 'Ordenes de Compra', href: '/portal/ordenes-compra' },
];

const estatusOptions = [
    { value: '', label: 'Todos' },
    { value: 'pendiente_entrega', label: 'Pend. Entrega' },
    { value: 'pendiente_factura', label: 'Pend. Factura' },
    { value: 'pendiente_aprobacion', label: 'Contrarecibo pendiente' },
    { value: 'pendiente_pago', label: 'Pend. Pago' },
    { value: 'pagada', label: 'Pagada' },
];

type Props = {
    ordenes: PaginatedData<CostosOrdenCompra>;
    filters: { search?: string; estatus?: string };
};


const facturasActivas = (oc: CostosOrdenCompra): CostosFactura[] =>
    (oc.facturas ?? []).filter((f) => f.estatus !== 'cancelada');

const facturasFacturadasCount = (oc: CostosOrdenCompra) =>
    facturasActivas(oc).filter((f) => f.estatus !== 'pendiente_aprobacion').length;

const pagosTotal = (oc: CostosOrdenCompra) =>
    facturasActivas(oc).filter((f) => f.pago).length;

const pagosPagadosCount = (oc: CostosOrdenCompra) =>
    facturasActivas(oc).filter((f) => f.pago?.estatus === 'pagado').length;

function recepcionSubtitle(oc: CostosOrdenCompra): string {
    const ent = oc.entregas_count ?? 0;
    if (ent === 0) {
        const total = oc.detalles_count ?? 0;
        return `0 de ${total} ${total === 1 ? 'partida' : 'partidas'}`;
    }
    return `${ent} ${ent === 1 ? 'entrega' : 'entregas'}`;
}

function etapaSubtitle(oc: CostosOrdenCompra): string {
    const etapa = oc.etapa_proceso;
    const activas = facturasActivas(oc);

    if (etapa === 'recepcion') {
        const det = oc.detalles_count ?? 0;
        return `${det} ${det === 1 ? 'partida pendiente' : 'partidas pendientes'}`;
    }
    if (etapa === 'espera_factura') {
        return '1 recepción sin facturar';
    }
    if (etapa === 'validacion_documentos') {
        const n = activas.filter((f) => f.estatus === 'pendiente_aprobacion').length;
        return `${n} ${n === 1 ? 'factura en revisión' : 'facturas en revisión'}`;
    }
    if (etapa === 'pago_programado') {
        const n = activas.filter((f) => f.pago && f.pago.estatus !== 'pagado').length;
        return `${n} ${n === 1 ? 'pago pendiente' : 'pagos pendientes'}`;
    }
    if (etapa === 'completada') {
        return `Cerrada el ${new Date(oc.updated_at).toLocaleDateString('es-MX', { day: '2-digit', month: 'short' })}`;
    }
    if (etapa === 'cancelada') {
        return 'Cancelada';
    }
    return '';
}

function pctClasses(pct: number | undefined): string {
    const v = pct ?? 0;
    if (v <= 0) return 'text-base-content/40';
    if (v >= 100) return 'text-success';
    return 'text-warning';
}

function EtapaCell({ oc }: { oc: CostosOrdenCompra }) {
    const etapa = (oc.etapa_proceso ?? 'recepcion') as CostosOcEtapaProceso;
    return (
        <div>
            <span className={OC_ETAPA_BADGE[etapa]}>{OC_ETAPA_LABELS[etapa]}</span>
            <div className="text-xs text-base-content/60 mt-1.5">{etapaSubtitle(oc)}</div>
        </div>
    );
}

function AvanceCell({ pct, subtitle }: { pct: number | undefined; subtitle: string }) {
    return (
        <div>
            <div className={`text-sm font-semibold ${pctClasses(pct)}`}>{(pct ?? 0).toFixed(0)}%</div>
            <div className="text-xs text-base-content/60 mt-0.5">{subtitle}</div>
        </div>
    );
}

function AlertasCell({ oc }: { oc: CostosOrdenCompra }) {
    const alerts: string[] = [];
    if (oc.retrasada) alerts.push('ENTREGA RETRASADA');
    if (oc.pago_vencido) alerts.push('PAGO VENCIDO');
    if (alerts.length === 0) return <span className="text-base-content/40 text-sm">—</span>;
    return (
        <div className="flex flex-wrap gap-1">
            {alerts.map((a) => (
                <span key={a} className="badge badge-error badge-sm font-semibold">
                    {a}
                </span>
            ))}
        </div>
    );
}

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

                <div className="max-w-full max-h-[calc(100vh-260px)] overflow-auto rounded-2xl border border-base-300 bg-base-100 shadow-sm">
                    <table className="w-full min-w-[900px]">
                        <thead className="bg-base-200 text-base-content/70 sticky top-0 z-10">
                            <tr className="text-left text-sm">
                                <th className="p-5 font-semibold">OC</th>
                                <th className="p-5 font-semibold">En proceso</th>
                                <th className="p-5 font-semibold">
                                    Recepción <span className="font-normal text-base-content/40 normal-case">(% del monto)</span>
                                </th>
                                <th className="p-5 font-semibold">
                                    Facturación <span className="font-normal text-base-content/40 normal-case">(% del monto)</span>
                                </th>
                                <th className="p-5 font-semibold">
                                    Pago <span className="font-normal text-base-content/40 normal-case">(% del monto)</span>
                                </th>
                                <th className="p-5 font-semibold">Alertas</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-base-300">
                            {ordenes.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="p-10 text-center text-base-content/40">
                                        No hay ordenes de compra
                                    </td>
                                </tr>
                            ) : (
                                ordenes.data.map((oc) => (
                                    <tr
                                        key={oc.id}
                                        className="hover:bg-base-200 cursor-pointer"
                                        onClick={() => router.visit(`/portal/ordenes-compra/${oc.id}`)}
                                    >
                                        <td className="p-5 w-[240px]">
                                            <Link
                                                href={`/portal/ordenes-compra/${oc.id}`}
                                                className="font-bold text-primary text-lg hover:underline"
                                                onClick={(e) => e.stopPropagation()}
                                            >
                                                {oc.folio}
                                            </Link>
                                            <div className="text-base-content/60 text-sm mt-1">
                                                {oc.proveedor?.razon_social ?? '—'}
                                            </div>
                                            <div className="text-base-content text-sm font-semibold mt-1">
                                                {fmtMonto(oc.total, oc.moneda)}
                                            </div>
                                        </td>
                                        <td className="p-5">
                                            <EtapaCell oc={oc} />
                                        </td>
                                        <td className="p-5">
                                            <AvanceCell pct={oc.porcentaje_recepcion} subtitle={recepcionSubtitle(oc)} />
                                        </td>
                                        <td className="p-5">
                                            <AvanceCell
                                                pct={oc.porcentaje_facturacion}
                                                subtitle={`${facturasFacturadasCount(oc)}/${facturasActivas(oc).length} facturas`}
                                            />
                                        </td>
                                        <td className="p-5">
                                            <AvanceCell
                                                pct={oc.porcentaje_pago}
                                                subtitle={`${pagosPagadosCount(oc)}/${pagosTotal(oc)} pagos`}
                                            />
                                        </td>
                                        <td className="p-5">
                                            <AlertasCell oc={oc} />
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {ordenes.last_page > 1 && (
                    <div className="mt-4 flex items-center justify-between text-sm text-base-content/70">
                        <span>
                            Página {ordenes.current_page} de {ordenes.last_page} · {ordenes.total} órdenes
                        </span>
                        <div className="flex gap-2">
                            {ordenes.prev_page_url && (
                                <Link href={ordenes.prev_page_url} className="btn btn-sm btn-outline" preserveState>
                                    Anterior
                                </Link>
                            )}
                            {ordenes.next_page_url && (
                                <Link href={ordenes.next_page_url} className="btn btn-sm btn-outline" preserveState>
                                    Siguiente
                                </Link>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </PortalLayout>
    );
}
