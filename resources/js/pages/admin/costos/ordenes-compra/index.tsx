import { formatMoney as fmtMonto } from '@/components/costos/monto';
import { CONTADO_STEPS, getContadoStep } from '@/components/costos/oc-contado';
import { SearchSelect } from '@/components/ui/search-select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosFactura, CostosOcEtapaProceso, CostosOrdenCompra, ModoPago, PaginatedData, Proveedor } from '@/types/models';
import { MODO_PAGO_LABELS, OC_ETAPA_BADGE, OC_ETAPA_LABELS } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronDownIcon, ChevronRightIcon, DownloadIcon, FileTextIcon, PlusIcon, SearchIcon } from 'lucide-react';
import { Fragment, type FormEvent, useState } from 'react';

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
    filters: { search?: string; estatus?: string; proveedor_id?: string; presupuesto_id?: string; tipo_pago?: string };
    proveedoresFiltro: Pick<Proveedor, 'id' | 'razon_social' | 'nombre_comercial'>[];
    presupuestosFiltro: { id: number; label: string }[];
};

const tipoPagoOptions = [
    { value: '', label: 'Todo tipo de pago' },
    { value: 'contado', label: 'Contado' },
    { value: 'credito', label: 'Crédito' },
];


const facturasActivas = (oc: CostosOrdenCompra): CostosFactura[] =>
    (oc.facturas ?? []).filter((f) => f.estatus !== 'cancelada');

const facturasFacturadasCount = (oc: CostosOrdenCompra) =>
    facturasActivas(oc).filter((f) => f.estatus !== 'pendiente_aprobacion').length;

const pagosTotal = (oc: CostosOrdenCompra) =>
    facturasActivas(oc).filter((f) => f.pago).length;

const pagosPagadosCount = (oc: CostosOrdenCompra) =>
    facturasActivas(oc).filter((f) => f.pago?.estatus === 'pagado').length;

function presupuestoLabel(oc: CostosOrdenCompra): string {
    // La OC carga a un presupuesto vía el centro de costos de sus detalles.
    return oc.presupuesto_label ?? 'Sin presupuesto';
}

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

function contadoSubtitle(oc: CostosOrdenCompra, step: number): string {
    switch (step) {
        case 0: return 'Anticipo pendiente de firma';
        case 1: return 'Anticipo aprobado, por pagar';
        case 2: return recepcionSubtitle(oc);
        case 3: return 'Recibido, falta subir factura';
        case 4: return 'Factura registrada';
        default: return 'Cancelada';
    }
}

/**
 * Aviso para el jefe de compras: hay unidades dadas por canceladas esperando su
 * firma. Va en la columna de proceso porque la orden se reporta pendiente de
 * aprobación justo por eso, y sin el aviso no se distingue de una orden que
 * espera aprobación de factura.
 */
function AvisoCancelaciones({ oc }: { oc: CostosOrdenCompra }) {
    const pendientes = oc.cancelaciones_pendientes_count ?? 0;

    if (pendientes <= 0) {
        return null;
    }

    return (
        <div className="text-error mt-1.5 text-xs font-semibold">
            {pendientes === 1 ? 'Cancelación de unidades por autorizar' : `${pendientes} cancelaciones de unidades por autorizar`}
        </div>
    );
}

function EtapaCell({ oc }: { oc: CostosOrdenCompra }) {
    // Las OCs de contado siguen otro flujo (anticipo por solicitud de pago).
    if (oc.tipo_pago === 'contado') {
        const step = getContadoStep(oc);
        if (step < 0) {
            return <span className="badge badge-neutral">Cancelada</span>;
        }
        const etapa = CONTADO_STEPS[step];
        return (
            <div>
                <span className={etapa.badge}>{etapa.label}</span>
                <div className="text-xs text-base-content/60 mt-1.5">{contadoSubtitle(oc, step)}</div>
                <AvisoCancelaciones oc={oc} />
            </div>
        );
    }

    const etapa = (oc.etapa_proceso ?? 'recepcion') as CostosOcEtapaProceso;
    return (
        <div>
            <span className={OC_ETAPA_BADGE[etapa]}>{OC_ETAPA_LABELS[etapa]}</span>
            <div className="text-xs text-base-content/60 mt-1.5">{etapaSubtitle(oc)}</div>
            <AvisoCancelaciones oc={oc} />
        </div>
    );
}

function PartidasTree({ oc }: { oc: CostosOrdenCompra }) {
    const detalles = oc.detalles ?? [];
    if (detalles.length === 0) {
        return <div className="text-xs text-base-content/40">Esta orden no tiene partidas.</div>;
    }
    return (
        <div className="ml-1 border-l border-base-300 pl-3">
            <div className="mb-1 text-[10px] uppercase tracking-wider text-base-content/50">
                {detalles.length} {detalles.length === 1 ? 'partida' : 'partidas'}
            </div>
            <div className="space-y-0.5">
                {detalles.map((d) => (
                    <div key={d.id} className="flex items-center gap-2 text-xs">
                        <FileTextIcon className="size-3 shrink-0 text-base-content/40" />
                        <span className="flex-1 truncate" title={d.descripcion}>{d.descripcion}</span>
                        <span className="shrink-0 text-base-content/60">
                            {Number(d.cantidad).toLocaleString('es-MX')} {d.unidad}
                        </span>
                        <span className="w-24 shrink-0 text-right text-base-content/60">${money(d.precio_unitario)}</span>
                        <span className="w-28 shrink-0 text-right font-medium">${money(d.subtotal)}</span>
                    </div>
                ))}
            </div>
            <div className="mt-1.5 flex items-center justify-end gap-2 border-t border-base-300 pt-1.5 text-xs">
                <span className="text-base-content/60">Total</span>
                <span className="w-28 text-right font-semibold">{fmtMonto(oc.total, oc.moneda)}</span>
            </div>
        </div>
    );
}

function TipoPagoBadge({ tipo }: { tipo: ModoPago | null }) {
    if (!tipo) return null;
    return (
        <span className={`badge badge-sm ${tipo === 'credito' ? 'badge-warning' : 'badge-success'}`}>
            {MODO_PAGO_LABELS[tipo]}
        </span>
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
    if (oc.tiene_devolucion) alerts.push('DEVOLUCION DE INSUMOS');
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

export default function OrdenesCompraIndex({ ordenes, filters, proveedoresFiltro, presupuestosFiltro }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [expanded, setExpanded] = useState<Set<number>>(new Set());

    const toggleExpanded = (id: number) =>
        setExpanded((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });

    const applyFilter = (patch: Record<string, string | undefined>) => {
        const next: Record<string, string | undefined> = { ...filters, ...patch };
        Object.keys(next).forEach((k) => {
            if (!next[k]) delete next[k];
        });
        router.get('/admin/costos/ordenes-compra', next, { preserveState: true, replace: true, preserveScroll: true });
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        applyFilter({ search: search || undefined });
    };

    const proveedorOptions = [
        { value: '', label: 'Todos los proveedores' },
        ...proveedoresFiltro.map((p) => ({ value: String(p.id), label: p.razon_social })),
    ];
    const presupuestoOptions = [
        { value: '', label: 'Todos los presupuestos' },
        ...presupuestosFiltro.map((p) => ({ value: String(p.id), label: p.label })),
    ];

    const exportUrl = (() => {
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([k, v]) => {
            if (v) params.set(k, String(v));
        });
        const qs = params.toString();
        return `/admin/costos/ordenes-compra/exportar${qs ? `?${qs}` : ''}`;
    })();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Compras — Órdenes de Compra" />

            <div className="p-4 md:p-6 w-full max-w-full min-w-0 overflow-x-hidden">
                <div className="mb-6 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="text-xl md:text-2xl font-semibold">Órdenes de compra</h1>
                        <p className="text-base-content/60 text-sm mt-1">Etapa en proceso, avance por monto y alertas.</p>
                    </div>
                    <div className="flex gap-2 self-start md:self-auto">
                        <a href={exportUrl} className="btn btn-outline btn-sm gap-1">
                            <DownloadIcon className="size-4" /> Exportar Excel
                        </a>
                        <Link
                            href="/admin/costos/ordenes-compra/create"
                            className="btn btn-primary btn-sm gap-1"
                        >
                            <PlusIcon className="size-4" /> Nueva orden
                        </Link>
                    </div>
                </div>

                <div className="mb-4 flex flex-col gap-2 lg:flex-row lg:flex-wrap lg:items-center lg:gap-3">
                    <form onSubmit={handleSubmit} className="flex items-center gap-2">
                        <div className="relative flex-1 sm:flex-initial">
                            <SearchIcon className="absolute left-2 top-1/2 -translate-y-1/2 size-4 text-base-content/40" />
                            <input
                                type="text"
                                className="input input-bordered input-sm pl-8 w-full sm:w-64"
                                placeholder="Buscar por folio..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>
                        <button type="submit" className="btn btn-sm">Buscar</button>
                    </form>

                    <SearchSelect
                        options={proveedorOptions}
                        value={filters.proveedor_id ?? ''}
                        onValueChange={(v) => applyFilter({ proveedor_id: v || undefined })}
                        placeholder="Proveedor..."
                        className="w-full lg:w-56"
                    />

                    <SearchSelect
                        options={presupuestoOptions}
                        value={filters.presupuesto_id ?? ''}
                        onValueChange={(v) => applyFilter({ presupuesto_id: v || undefined })}
                        placeholder="Presupuesto..."
                        className="w-full lg:w-64"
                    />

                    <select
                        className="select select-bordered select-sm w-full lg:w-auto"
                        value={filters.tipo_pago ?? ''}
                        onChange={(e) => applyFilter({ tipo_pago: e.target.value || undefined })}
                    >
                        {tipoPagoOptions.map((opt) => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                    </select>

                    <select
                        className="select select-bordered select-sm w-full lg:w-auto"
                        value={filters.estatus ?? ''}
                        onChange={(e) => applyFilter({ estatus: e.target.value || undefined })}
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
                                            {fmtMonto(oc.total, oc.moneda)}
                                        </div>
                                        <div className="text-base-content/50 text-xs mt-0.5">
                                            {presupuestoLabel(oc)}
                                        </div>
                                        <div className="mt-1.5">
                                            <TipoPagoBadge tipo={oc.tipo_pago} />
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
                                {(oc.retrasada || oc.pago_vencido || oc.tiene_devolucion) && (
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
                                <th className="p-3 xl:p-5 font-semibold">Proveedor</th>
                                <th className="p-3 xl:p-5 font-semibold">Presupuesto</th>
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
                                    <td colSpan={8} className="p-10 text-center text-base-content/40">
                                        No hay órdenes de compra
                                    </td>
                                </tr>
                            ) : (
                                ordenes.data.map((oc) => (
                                    <Fragment key={oc.id}>
                                    <tr
                                        className="hover:bg-base-200 cursor-pointer"
                                        onClick={() => router.visit(`/admin/costos/ordenes-compra/${oc.id}`)}
                                    >
                                        <td className="p-3 xl:p-5 w-[260px]">
                                            <div className="flex items-center gap-1.5">
                                                <button
                                                    type="button"
                                                    className="btn btn-ghost btn-xs px-1"
                                                    title={expanded.has(oc.id) ? 'Ocultar partidas' : 'Ver partidas'}
                                                    onClick={(e) => { e.stopPropagation(); toggleExpanded(oc.id); }}
                                                >
                                                    {expanded.has(oc.id)
                                                        ? <ChevronDownIcon className="size-4" />
                                                        : <ChevronRightIcon className="size-4" />}
                                                </button>
                                                <Link
                                                    href={`/admin/costos/ordenes-compra/${oc.id}`}
                                                    className="font-bold text-primary text-base xl:text-lg hover:underline"
                                                    onClick={(e) => e.stopPropagation()}
                                                >
                                                    {oc.folio}
                                                </Link>
                                            </div>
                                            <div className="mt-1.5">
                                                <TipoPagoBadge tipo={oc.tipo_pago} />
                                            </div>
                                        </td>
                                        <td className="p-3 xl:p-5">
                                            <div className="text-sm">{oc.proveedor?.razon_social ?? '—'}</div>
                                        </td>
                                        <td className="p-3 xl:p-5">
                                            <div className="text-sm">{presupuestoLabel(oc)}</div>
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
                                    {expanded.has(oc.id) && (
                                        <tr className="bg-base-200/30">
                                            <td colSpan={8} className="px-5 pb-4 pt-1">
                                                <PartidasTree oc={oc} />
                                            </td>
                                        </tr>
                                    )}
                                    </Fragment>
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
