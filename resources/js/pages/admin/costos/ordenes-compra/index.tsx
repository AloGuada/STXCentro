import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosFactura, CostosOcEtapaProceso, CostosOrdenCompra, PaginatedData } from '@/types/models';
import { OC_ETAPA_BADGE, OC_ETAPA_LABELS } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { PlusIcon, SearchIcon } from 'lucide-react';
import { type FormEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/ordenes-compra' },
    { title: 'Compras', href: '/admin/costos/ordenes-compra' },
];

const estatusOptions = [
    { value: '', label: 'Todos' },
    { value: 'pendiente_entrega', label: 'Pend. Entrega' },
    { value: 'pendiente_factura', label: 'Pend. Factura' },
    { value: 'pendiente_aprobacion', label: 'Pend. Aprobación' },
    { value: 'pendiente_pago', label: 'Pend. Pago' },
    { value: 'pagada', label: 'Pagada' },
    { value: 'cancelada', label: 'Cancelada' },
];

type Props = {
    ordenes: PaginatedData<CostosOrdenCompra>;
    filters: { search?: string; estatus?: string };
};

const money = (n: number) =>
    Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

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

export default function OrdenesCompraIndex({ ordenes, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            '/admin/costos/ordenes-compra',
            { ...filters, search: search || undefined },
            { preserveState: true, replace: true },
        );
    };

    const handleEstatusChange = (estatus: string) => {
        router.get('/admin/costos/ordenes-compra', { ...filters, estatus: estatus || undefined }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Compras — Órdenes de Compra" />

            <div className="p-4 md:p-6 w-full max-w-full min-w-0 overflow-x-hidden">
                <div className="mb-6 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="text-xl md:text-2xl font-semibold">Órdenes de compra</h1>
                        <p className="text-base-content/60 text-sm mt-1">Etapa en proceso, avance por monto y alertas.</p>
                    </div>
                    <Link
                        href="/admin/costos/ordenes-compra/create"
                        className="btn btn-primary btn-sm gap-1 self-start md:self-auto"
                    >
                        <PlusIcon className="size-4" /> Nueva orden
                    </Link>
                </div>

                <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                    <form onSubmit={handleSubmit} className="flex flex-1 items-center gap-2">
                        <div className="relative flex-1 sm:flex-initial">
                            <SearchIcon className="absolute left-2 top-1/2 -translate-y-1/2 size-4 text-base-content/40" />
                            <input
                                type="text"
                                className="input input-bordered input-sm pl-8 w-full sm:w-72"
                                placeholder="Buscar por folio o proveedor..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>
                        <button type="submit" className="btn btn-sm">Buscar</button>
                    </form>

                    <select
                        className="select select-bordered select-sm w-full sm:w-auto"
                        value={filters.estatus ?? ''}
                        onChange={(e) => handleEstatusChange(e.target.value)}
                    >
                        {estatusOptions.map((opt) => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                    </select>
                </div>

                {/* Mobile: cards apiladas con scroll vertical */}
                <div className="space-y-3 lg:hidden max-h-[calc(100vh-260px)] overflow-y-auto pr-1">
                    {ordenes.data.length === 0 ? (
                        <div className="rounded-2xl border border-base-300 bg-base-100 p-10 text-center text-base-content/40 shadow-sm">
                            No hay órdenes de compra
                        </div>
                    ) : (
                        ordenes.data.map((oc) => (
                            <Link
                                key={oc.id}
                                href={`/admin/costos/ordenes-compra/${oc.id}`}
                                className="block rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm hover:bg-base-200"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <div className="font-bold text-primary text-base">{oc.folio}</div>
                                        <div className="text-base-content/60 text-xs mt-0.5">
                                            {oc.proveedor?.razon_social ?? '—'}
                                        </div>
                                        <div className="text-base-content text-sm font-semibold mt-1">
                                            ${money(oc.total)} MXN
                                        </div>
                                    </div>
                                    <EtapaCell oc={oc} />
                                </div>
                                <div className="mt-3 grid grid-cols-3 gap-2 border-t border-base-300 pt-3">
                                    <div>
                                        <div className="text-xs text-base-content/60">Recepción</div>
                                        <AvanceCell pct={oc.porcentaje_recepcion} subtitle={recepcionSubtitle(oc)} />
                                    </div>
                                    <div>
                                        <div className="text-xs text-base-content/60">Facturación</div>
                                        <AvanceCell
                                            pct={oc.porcentaje_facturacion}
                                            subtitle={`${facturasFacturadasCount(oc)}/${facturasActivas(oc).length} facturas`}
                                        />
                                    </div>
                                    <div>
                                        <div className="text-xs text-base-content/60">Pago</div>
                                        <AvanceCell
                                            pct={oc.porcentaje_pago}
                                            subtitle={`${pagosPagadosCount(oc)}/${pagosTotal(oc)} pagos`}
                                        />
                                    </div>
                                </div>
                                {(oc.retrasada || oc.pago_vencido) && (
                                    <div className="mt-3 border-t border-base-300 pt-3">
                                        <AlertasCell oc={oc} />
                                    </div>
                                )}
                            </Link>
                        ))
                    )}
                </div>

                {/* Desktop: tabla con scroll horizontal+vertical interno y header sticky */}
                <div className="hidden lg:block max-w-full max-h-[calc(100vh-260px)] overflow-auto rounded-2xl border border-base-300 bg-base-100 shadow-sm">
                    <table className="w-full min-w-[960px]">
                        <thead className="bg-base-200 text-base-content/70 sticky top-0 z-10">
                            <tr className="text-left text-sm">
                                <th className="p-3 xl:p-5 font-semibold">OC</th>
                                <th className="p-3 xl:p-5 font-semibold">En proceso</th>
                                <th className="p-3 xl:p-5 font-semibold">
                                    Recepción <span className="font-normal text-base-content/40 normal-case">(% del monto)</span>
                                </th>
                                <th className="p-3 xl:p-5 font-semibold">
                                    Facturación <span className="font-normal text-base-content/40 normal-case">(% del monto)</span>
                                </th>
                                <th className="p-3 xl:p-5 font-semibold">
                                    Pago <span className="font-normal text-base-content/40 normal-case">(% del monto)</span>
                                </th>
                                <th className="p-3 xl:p-5 font-semibold">Alertas</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-base-300">
                            {ordenes.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="p-10 text-center text-base-content/40">
                                        No hay órdenes de compra
                                    </td>
                                </tr>
                            ) : (
                                ordenes.data.map((oc) => (
                                    <tr
                                        key={oc.id}
                                        className="hover:bg-base-200 cursor-pointer"
                                        onClick={() => router.visit(`/admin/costos/ordenes-compra/${oc.id}`)}
                                    >
                                        <td className="p-3 xl:p-5 w-[260px]">
                                            <Link
                                                href={`/admin/costos/ordenes-compra/${oc.id}`}
                                                className="font-bold text-primary text-base xl:text-lg hover:underline"
                                                onClick={(e) => e.stopPropagation()}
                                            >
                                                {oc.folio}
                                            </Link>
                                            <div className="text-base-content/60 text-sm mt-1">
                                                {oc.proveedor?.razon_social ?? '—'}
                                            </div>
                                            <div className="text-base-content text-sm font-semibold mt-1">
                                                ${money(oc.total)} MXN
                                            </div>
                                        </td>
                                        <td className="p-3 xl:p-5">
                                            <EtapaCell oc={oc} />
                                        </td>
                                        <td className="p-3 xl:p-5">
                                            <AvanceCell pct={oc.porcentaje_recepcion} subtitle={recepcionSubtitle(oc)} />
                                        </td>
                                        <td className="p-3 xl:p-5">
                                            <AvanceCell
                                                pct={oc.porcentaje_facturacion}
                                                subtitle={`${facturasFacturadasCount(oc)}/${facturasActivas(oc).length} facturas`}
                                            />
                                        </td>
                                        <td className="p-3 xl:p-5">
                                            <AvanceCell
                                                pct={oc.porcentaje_pago}
                                                subtitle={`${pagosPagadosCount(oc)}/${pagosTotal(oc)} pagos`}
                                            />
                                        </td>
                                        <td className="p-3 xl:p-5">
                                            <AlertasCell oc={oc} />
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {ordenes.last_page > 1 && (
                    <div className="mt-4 flex flex-col items-start gap-2 text-sm text-base-content/70 sm:flex-row sm:items-center sm:justify-between">
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
        </AppLayout>
    );
}
