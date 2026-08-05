import { Head, Link, router } from '@inertiajs/react';
import { ChevronDown, ChevronRight, FileCheck2, FileText, FileUp, Receipt, ReceiptText, Upload } from 'lucide-react';
import { type ComponentType, useState } from 'react';
import { formatMoney } from '@/components/costos/monto';
import { ConfirmarFacturaModal } from '@/components/portal/confirmar-factura-modal';
import { DetalleFacturaModal } from '@/components/portal/detalle-factura-modal';
import { SubirComprobanteRecepcionModal } from '@/components/portal/subir-comprobante-recepcion-modal';
import { SubirFacturaModal } from '@/components/portal/subir-factura-modal';
import { VisorDocumentoModal, type DocumentoVisor } from '@/components/portal/visor-documento-modal';
import { Button } from '@/components/ui/button';
import PortalLayout from '@/layouts/portal/portal-layout';
import type {
    PaginatedData,
    PortalFacturaPreview,
    PortalTableroFactura,
    PortalTableroOrden,
    PortalTableroResumen,
    PortalTableroTab,
} from '@/types/models';

type Props = {
    ordenes: PaginatedData<PortalTableroOrden>;
    conteos: { activas: number; completadas: number };
    resumen: PortalTableroResumen;
    tab: PortalTableroTab;
    /** Paso 2 del alta de factura: si viene, se abre el modal de confirmación. */
    facturaPreview: PortalFacturaPreview | null;
};

const fmtFecha = (iso: string | null) =>
    iso ? new Date(`${iso}T12:00:00`).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: '2-digit' }) : '—';

/** Icono de documento: abre el visor si existe, se ve apagado si falta. */
function DocIcono({
    icon: Icon,
    titulo,
    url,
    nombre,
    onVer,
    className,
}: {
    icon: ComponentType<{ className?: string }>;
    titulo: string;
    url: string | null;
    nombre?: string | null;
    onVer: (doc: DocumentoVisor) => void;
    className?: string;
}) {
    if (!url) {
        return (
            <span className="tooltip" data-tip={`Sin ${titulo.toLowerCase()}`}>
                <Icon className="size-4 text-base-content/20" />
            </span>
        );
    }

    return (
        <span className="tooltip" data-tip={titulo}>
            <button
                type="button"
                aria-label={`Ver ${titulo}`}
                className={`btn btn-ghost btn-xs btn-square ${className ?? 'text-base-content/60'}`}
                onClick={() => onVer({ titulo, url, nombre })}
            >
                <Icon className="size-4" />
            </button>
        </span>
    );
}

/** Celda de la orden: el folio manda y el total va debajo, en su propio renglón. */
function CeldaOrden({
    orden,
    expandida,
    onToggle,
}: {
    orden: PortalTableroOrden;
    expandida: boolean;
    onToggle: () => void;
}) {
    const pendiente = orden.saldo_facturable;

    return (
        <>
            <button type="button" className="flex items-center gap-1 font-bold text-primary" onClick={onToggle}>
                {expandida ? <ChevronDown className="size-4" /> : <ChevronRight className="size-4" />}
                {orden.folio}
            </button>
            <div className="mt-0.5 text-base font-semibold tabular-nums">{formatMoney(orden.total, orden.moneda)}</div>
            <div className="mt-0.5 text-xs text-base-content/60">
                {orden.total_facturado === 0 ? (
                    'Sin facturar'
                ) : (
                    <>
                        Facturado {formatMoney(orden.total_facturado, orden.moneda)}
                        {pendiente > 0 && ` · pendiente ${formatMoney(pendiente, orden.moneda)}`}
                    </>
                )}
            </div>
        </>
    );
}

export default function PortalTablero({ ordenes, conteos, resumen, tab, facturaPreview }: Props) {
    const [facturandoOrden, setFacturandoOrden] = useState<PortalTableroOrden | null>(null);
    const [comprobandoFactura, setComprobandoFactura] = useState<PortalTableroFactura | null>(null);
    const [docVisor, setDocVisor] = useState<DocumentoVisor | null>(null);
    const [detalleFactura, setDetalleFactura] = useState<PortalTableroFactura | null>(null);
    const [expandidas, setExpandidas] = useState<number[]>([]);

    const cambiarTab = (nuevo: PortalTableroTab) => {
        router.get('/portal', { tab: nuevo }, { preserveState: true, preserveScroll: true, only: ['ordenes', 'conteos', 'tab'] });
    };

    const alternarPartidas = (id: number) =>
        setExpandidas((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));

    return (
        <PortalLayout>
            <Head title="Mis órdenes de compra" />

            <div className="p-6">
                <div className="mb-4">
                    <h1 className="text-xl font-semibold">Mis órdenes de compra</h1>
                    <p className="text-sm text-base-content/60">Sube tu factura, adjunta la recepción y consulta tu pago.</p>
                </div>

                <div className="mb-4 grid gap-3 sm:grid-cols-3">
                    <div className="rounded-xl border border-base-300 bg-base-100 px-4 py-3">
                        <div className="text-xs uppercase tracking-wide text-base-content/60">Facturado</div>
                        <div className="mt-0.5 text-xl font-bold">{formatMoney(resumen.facturado, resumen.moneda)}</div>
                        <div className="text-xs text-base-content/50">
                            {resumen.facturas} {resumen.facturas === 1 ? 'factura' : 'facturas'}
                        </div>
                    </div>
                    <div className="rounded-xl border border-success/30 bg-success/10 px-4 py-3">
                        <div className="text-xs uppercase tracking-wide text-base-content/60">Pagado</div>
                        <div className="mt-0.5 text-xl font-bold text-success">{formatMoney(resumen.pagado, resumen.moneda)}</div>
                        <div className="text-xs text-base-content/50">
                            {resumen.facturas_pagadas} {resumen.facturas_pagadas === 1 ? 'factura pagada' : 'facturas pagadas'}
                        </div>
                    </div>
                    <div className="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3">
                        <div className="text-xs uppercase tracking-wide text-base-content/60">Pendiente de pago</div>
                        <div className="mt-0.5 text-xl font-bold text-warning">{formatMoney(resumen.pendiente, resumen.moneda)}</div>
                        <div className="text-xs text-base-content/50">{resumen.facturas - resumen.facturas_pagadas} por cobrar</div>
                    </div>
                </div>

                <div role="tablist" className="tabs tabs-boxed mb-3 w-fit bg-base-100">
                    {(
                        [
                            ['activas', 'Activas', conteos.activas],
                            ['completadas', 'Completadas', conteos.completadas],
                        ] as const
                    ).map(([valor, etiqueta, cuantas]) => (
                        <button
                            key={valor}
                            type="button"
                            role="tab"
                            aria-selected={tab === valor}
                            className={`tab gap-2 ${tab === valor ? 'tab-active' : ''}`}
                            onClick={() => cambiarTab(valor)}
                        >
                            {etiqueta}
                            <span className="badge badge-sm">{cuantas}</span>
                        </button>
                    ))}
                </div>

                <div className="overflow-x-auto rounded-2xl border border-base-300 bg-base-100 shadow-sm">
                    <table className="w-full min-w-[1000px] text-sm">
                        <thead className="bg-base-200 text-base-content/70">
                            <tr className="text-left text-xs uppercase tracking-wide">
                                <th className="px-4 py-2.5 font-semibold">Orden de compra</th>
                                <th className="px-4 py-2.5 font-semibold">Factura</th>
                                <th className="px-4 py-2.5 font-semibold">Recepción</th>
                                <th className="px-4 py-2.5 font-semibold">Contrarecibo</th>
                                <th className="px-4 py-2.5 font-semibold">Comprobante de pago</th>
                            </tr>
                        </thead>

                        {ordenes.data.length === 0 && (
                            <tbody>
                                <tr>
                                    <td colSpan={5} className="px-4 py-12 text-center text-base-content/50">
                                        {tab === 'activas'
                                            ? 'No tienes órdenes de compra activas.'
                                            : 'Aquí verás las órdenes que ya se facturaron y pagaron por completo.'}
                                    </td>
                                </tr>
                            </tbody>
                        )}

                        {ordenes.data.map((oc) => {
                            const expandida = expandidas.includes(oc.id);
                            // La celda de la OC abarca sus facturas, el pie de acciones
                            // y —si está abierta— la fila de partidas.
                            const filasOcupadas = oc.facturas.length + 1 + (expandida ? 1 : 0);

                            return (
                                <tbody key={oc.id} className="border-t-4 border-base-200">
                                    {oc.facturas.map((f, i) => (
                                        <tr key={f.id} className="border-t border-base-300">
                                            {i === 0 && (
                                                <td className="w-[260px] px-4 py-2.5 align-top" rowSpan={filasOcupadas}>
                                                    <CeldaOrden orden={oc} expandida={expandida} onToggle={() => alternarPartidas(oc.id)} />
                                                </td>
                                            )}

                                            <td className="px-4 py-2.5">
                                                <div className="flex items-center gap-2 whitespace-nowrap">
                                                    <button
                                                        type="button"
                                                        className={`link link-hover font-medium ${f.cancelada ? 'text-base-content/40 line-through' : ''}`}
                                                        onClick={() => setDetalleFactura(f)}
                                                    >
                                                        {f.folio}
                                                    </button>
                                                    <span className="text-xs text-base-content/50">{fmtFecha(f.fecha)}</span>
                                                    {f.cancelada && <span className="badge badge-error badge-sm">Cancelada</span>}
                                                    <span className="ml-auto flex items-center">
                                                        <DocIcono
                                                            icon={FileText}
                                                            titulo="PDF de la factura"
                                                            url={f.pdf_url}
                                                            onVer={setDocVisor}
                                                        />
                                                    </span>
                                                </div>
                                            </td>

                                            <td className="px-4 py-2.5">
                                                {f.recepcion ? (
                                                    <div className="flex items-center gap-1 whitespace-nowrap">
                                                        <DocIcono
                                                            icon={FileCheck2}
                                                            titulo="Comprobante de recepción"
                                                            url={f.recepcion.url}
                                                            nombre={f.recepcion.nombre}
                                                            onVer={setDocVisor}
                                                            className="text-success"
                                                        />
                                                        <span className="text-xs text-base-content/60">{fmtFecha(f.recepcion.fecha)}</span>
                                                    </div>
                                                ) : f.puede_subir_recepcion ? (
                                                    <Button size="xs" variant="outline" onClick={() => setComprobandoFactura(f)}>
                                                        <Upload className="size-3" />
                                                        Subir
                                                    </Button>
                                                ) : (
                                                    <span className="text-base-content/40">—</span>
                                                )}
                                            </td>

                                            {/* Sin pago programado no hay contrarecibo que entregar. */}
                                            <td className="whitespace-nowrap px-4 py-2.5">
                                                {f.contrarecibo_url ? (
                                                    <DocIcono
                                                        icon={ReceiptText}
                                                        titulo="Contrarecibo"
                                                        url={f.contrarecibo_url}
                                                        onVer={setDocVisor}
                                                    />
                                                ) : (
                                                    <span className="text-base-content/40">Por programar</span>
                                                )}
                                            </td>

                                            <td className="px-4 py-2.5">
                                                {f.comprobantes_pago.length === 0 ? (
                                                    <DocIcono icon={Receipt} titulo="Comprobante de pago" url={null} onVer={setDocVisor} />
                                                ) : (
                                                    <div className="flex items-center gap-1">
                                                        {f.comprobantes_pago.map((c) => (
                                                            <DocIcono
                                                                key={c.id}
                                                                icon={Receipt}
                                                                titulo={
                                                                    c.numero_parcialidad
                                                                        ? `Comprobante de pago (parcialidad ${c.numero_parcialidad})`
                                                                        : 'Comprobante de pago'
                                                                }
                                                                url={c.url}
                                                                onVer={setDocVisor}
                                                                className="text-primary"
                                                            />
                                                        ))}
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    ))}

                                    {expandida && (
                                        <tr className="border-t border-base-300 bg-base-200/40">
                                            {/* Sin facturas, ésta es la primera fila: aquí nace la celda de la orden. */}
                                            {oc.facturas.length === 0 && (
                                                <td className="w-[260px] px-4 py-2.5 align-top" rowSpan={filasOcupadas}>
                                                    <CeldaOrden
                                                        orden={oc}
                                                        expandida={expandida}
                                                        onToggle={() => alternarPartidas(oc.id)}
                                                    />
                                                </td>
                                            )}
                                            <td className="px-4 py-3" colSpan={4}>
                                                {oc.partidas.length === 0 ? (
                                                    <span className="text-xs text-base-content/50">Esta orden no tiene partidas.</span>
                                                ) : (
                                                    <table className="table table-xs">
                                                        <thead>
                                                            <tr>
                                                                <th>Descripción</th>
                                                                <th className="text-right">Cantidad</th>
                                                                <th>Unidad</th>
                                                                <th className="text-right">P. unitario</th>
                                                                <th className="text-right">Importe</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            {oc.partidas.map((p) => (
                                                                <tr key={p.id}>
                                                                    <td>{p.descripcion}</td>
                                                                    <td className="text-right tabular-nums">{p.cantidad}</td>
                                                                    <td>{p.unidad ?? '—'}</td>
                                                                    <td className="text-right tabular-nums">
                                                                        {formatMoney(p.precio_unitario, oc.moneda)}
                                                                    </td>
                                                                    <td className="text-right tabular-nums">
                                                                        {formatMoney(p.subtotal, oc.moneda)}
                                                                    </td>
                                                                </tr>
                                                            ))}
                                                        </tbody>
                                                    </table>
                                                )}
                                            </td>
                                        </tr>
                                    )}

                                    <tr className="border-t border-base-300">
                                        {oc.facturas.length === 0 && !expandida && (
                                            <td className="w-[260px] px-4 py-2.5 align-top" rowSpan={filasOcupadas}>
                                                <CeldaOrden orden={oc} expandida={expandida} onToggle={() => alternarPartidas(oc.id)} />
                                            </td>
                                        )}
                                        <td className="px-4 py-2" colSpan={4}>
                                            {oc.completada ? (
                                                <span className="text-xs text-success">Orden facturada y pagada por completo.</span>
                                            ) : oc.puede_facturar ? (
                                                <>
                                                    <Button
                                                        size="xs"
                                                        variant="ghost"
                                                        className="text-primary"
                                                        onClick={() => setFacturandoOrden(oc)}
                                                    >
                                                        <FileUp className="size-3" />
                                                        Subir factura
                                                    </Button>
                                                    {oc.facturas.length === 0 && (
                                                        <span className="ml-2 text-xs text-base-content/50">
                                                            Aún no has facturado esta orden.
                                                        </span>
                                                    )}
                                                </>
                                            ) : (
                                                <span className="text-xs text-base-content/50">Orden facturada por completo.</span>
                                            )}
                                        </td>
                                    </tr>
                                </tbody>
                            );
                        })}
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

            {facturandoOrden && !facturaPreview && (
                <SubirFacturaModal orden={facturandoOrden} onClose={() => setFacturandoOrden(null)} />
            )}

            {/* Paso 2: lo abre el servidor, no un clic; vive mientras dure la sesión de preview. */}
            {facturaPreview && <ConfirmarFacturaModal preview={facturaPreview} />}

            {comprobandoFactura && (
                <SubirComprobanteRecepcionModal factura={comprobandoFactura} onClose={() => setComprobandoFactura(null)} />
            )}

            {detalleFactura && !docVisor && (
                <DetalleFacturaModal
                    factura={detalleFactura}
                    onClose={() => setDetalleFactura(null)}
                    onVerDocumento={setDocVisor}
                />
            )}

            {docVisor && <VisorDocumentoModal doc={docVisor} onClose={() => setDocVisor(null)} />}
        </PortalLayout>
    );
}
