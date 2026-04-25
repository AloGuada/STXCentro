import { ActivityTimeline } from '@/components/costos/activity-timeline';
import { CancelarModal } from '@/components/costos/cancelar-modal';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosFactura } from '@/types/models';
import { ENTREGA_TIPO_LABELS, FACTURA_ESTATUS_COLORS, FACTURA_ESTATUS_LABELS, PAGO_ESTATUS_COLORS, PAGO_ESTATUS_LABELS } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { FileIcon, Loader2Icon } from 'lucide-react';
import { useMemo, useState } from 'react';

type Props = {
    factura: CostosFactura;
};

export default function FacturasShow({ factura }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/facturas' },
        { title: 'Facturas', href: '/admin/costos/facturas' },
        { title: factura.folio, href: `/admin/costos/facturas/${factura.id}` },
    ];

    const { can } = useCan();
    const [showAprobarModal, setShowAprobarModal] = useState(false);
    const [showAceptarModal, setShowAceptarModal] = useState(false);
    const [showCerrarModal, setShowCerrarModal] = useState(false);
    const [aprobarProcessing, setAprobarProcessing] = useState(false);
    const [aceptarProcessing, setAceptarProcessing] = useState(false);
    const formatMoney = (n: number) => `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

    const fechaPago = useMemo(() => {
        const dias = factura.proveedor?.dias_credito_default;
        if (!dias || dias <= 0) return null;
        const base = new Date();
        base.setDate(base.getDate() + dias);
        const day = base.getDay();
        if (day === 6) base.setDate(base.getDate() + 6);
        else if (day === 0) base.setDate(base.getDate() + 5);
        else if (day < 5) base.setDate(base.getDate() + (5 - day));
        return base;
    }, [factura.proveedor?.dias_credito_default]);

    const hasEntregas = (factura.entregas?.length ?? 0) > 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={factura.folio} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">{factura.folio}</h1>
                        <div className="flex items-center gap-2 mt-1">
                            <span className={`badge ${FACTURA_ESTATUS_COLORS[factura.estatus]}`}>
                                {FACTURA_ESTATUS_LABELS[factura.estatus]}
                            </span>
                            {factura.aprobada_costos && (
                                <span className="badge badge-success">Aprobada Costos</span>
                            )}
                            {factura.aceptada_contabilidad && (
                                <span className="badge badge-info">Aceptada Contabilidad</span>
                            )}
                            {factura.orden_compra && (
                                <Link href={`/admin/costos/ordenes-compra/${factura.orden_compra.id}`} className="link link-primary text-sm">
                                    OC: {factura.orden_compra.folio}
                                </Link>
                            )}
                        </div>
                    </div>

                    <div className="flex gap-2">
                        {factura.estatus === 'pendiente_entrega' && factura.orden_compra && (
                            <Button variant="outline" asChild>
                                <Link href={`/admin/costos/ordenes-compra/${factura.orden_compra.id}`}>
                                    Registrar entrega en OC
                                </Link>
                            </Button>
                        )}
                        {factura.estatus === 'pendiente_aprobacion' && !factura.aprobada_costos && can('costos.facturas.aprobar') && (
                            <Button onClick={() => setShowAprobarModal(true)}>
                                Aprobar Costos
                            </Button>
                        )}
                        {factura.estatus === 'pendiente_pago' && factura.aprobada_costos && !factura.aceptada_contabilidad && can('costos.facturas.aceptar-contabilidad') && (
                            <Button onClick={() => setShowAceptarModal(true)}>
                                Aceptar y Programar Pago
                            </Button>
                        )}
                        {factura.estatus !== 'pagada' && factura.estatus !== 'cancelada' && !factura.aceptada_contabilidad && can('costos.facturas.cancelar') && (
                            <Button variant="destructive" onClick={() => setShowCerrarModal(true)}>
                                Cerrar factura
                            </Button>
                        )}
                    </div>
                </div>

                {/* Factura info */}
                <div className="grid grid-cols-2 gap-6 mb-6">
                    <div className="space-y-3">
                        <div>
                            <span className="text-sm text-base-content/60">Proveedor</span>
                            <p className="font-medium">{factura.proveedor?.razon_social}</p>
                        </div>
                        <div>
                            <span className="text-sm text-base-content/60">Fecha Factura</span>
                            <p className="font-medium">{factura.fecha_factura ? new Date(factura.fecha_factura).toLocaleDateString() : '-'}</p>
                        </div>
                    </div>
                    <div className="space-y-3">
                        <div>
                            <span className="text-sm text-base-content/60">Total</span>
                            <p className="font-medium text-lg">{formatMoney(factura.total)}</p>
                        </div>
                        {factura.uuid_fiscal && (
                            <div>
                                <span className="text-sm text-base-content/60">UUID Fiscal</span>
                                <p className="font-mono text-sm">{factura.uuid_fiscal}</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Partidas */}
                {factura.detalles && factura.detalles.length > 0 && (
                    <div className="mb-6">
                        <div className="flex items-center justify-between mb-3">
                            <h2 className="text-lg font-medium">Partidas facturadas</h2>
                            {factura.estatus === 'pendiente_entrega' && (
                                <span className={`badge ${factura.cobertura_completa ? 'badge-success' : 'badge-warning'}`}>
                                    {factura.cobertura_completa ? 'Recepción completa' : 'Pendiente de recepción'}
                                </span>
                            )}
                        </div>
                        <div className="overflow-x-auto">
                            <table className="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Partida de OC</th>
                                        <th className="text-right">Cantidad</th>
                                        <th>Unidad</th>
                                        <th className="text-right">P. unitario</th>
                                        <th className="text-right">Subtotal</th>
                                        <th className="text-right">Disponible</th>
                                        <th>Cobertura</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {factura.detalles.map((d) => {
                                        const cobertura = factura.cobertura_por_partida?.[d.id];
                                        return (
                                            <tr key={d.id}>
                                                <td>{d.orden_compra_detalle?.descripcion ?? '-'}</td>
                                                <td className="text-right">{Number(d.cantidad).toLocaleString('es-MX')}</td>
                                                <td>{d.orden_compra_detalle?.unidad ?? '-'}</td>
                                                <td className="text-right">{formatMoney(d.precio_unitario)}</td>
                                                <td className="text-right">{formatMoney(d.subtotal)}</td>
                                                <td className="text-right">
                                                    {cobertura ? Number(cobertura.disponible).toLocaleString('es-MX') : '—'}
                                                </td>
                                                <td>
                                                    {cobertura && (
                                                        <span className={`badge badge-sm ${cobertura.cubierta ? 'badge-success' : 'badge-warning'}`}>
                                                            {cobertura.cubierta ? 'Cubierta' : 'Sin cubrir'}
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Desglose fiscal */}
                <div className="mb-6">
                    <h2 className="text-lg font-medium mb-3">Desglose fiscal</h2>
                    <div className="rounded-lg border border-base-300 p-4">
                        <dl className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div>
                                <dt className="text-base-content/60">Subtotal</dt>
                                <dd className="font-medium">{formatMoney(factura.subtotal)}</dd>
                            </div>
                            <div>
                                <dt className="text-base-content/60">IVA trasladado</dt>
                                <dd className="font-medium">{formatMoney(factura.iva_trasladado)}</dd>
                            </div>
                            <div>
                                <dt className="text-base-content/60">IVA retenido</dt>
                                <dd className="font-medium">{formatMoney(factura.iva_retenido)}</dd>
                            </div>
                            <div>
                                <dt className="text-base-content/60">ISR retenido</dt>
                                <dd className="font-medium">{formatMoney(factura.isr_retenido)}</dd>
                            </div>
                            <div>
                                <dt className="text-base-content/60">Total</dt>
                                <dd className="font-medium">{formatMoney(factura.total)}</dd>
                            </div>
                            <div>
                                <dt className="text-base-content/60">UUID fiscal</dt>
                                <dd className="font-mono text-xs break-all">{factura.uuid_fiscal ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-base-content/60">Folio fiscal</dt>
                                <dd>{factura.folio_fiscal ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-base-content/60">Fecha factura</dt>
                                <dd>{factura.fecha_factura ?? '—'}</dd>
                            </div>
                        </dl>
                        {factura.impuestos_detalle && (
                            <details className="mt-3">
                                <summary className="cursor-pointer text-sm text-base-content/70">
                                    Ver detalle por concepto (CFDI)
                                </summary>
                                <div className="mt-2 space-y-3">
                                    {factura.impuestos_detalle.traslados.length > 0 && (
                                        <div>
                                            <div className="text-xs font-medium mb-1">Traslados</div>
                                            <table className="table table-xs">
                                                <thead>
                                                    <tr>
                                                        <th>Impuesto</th>
                                                        <th>Factor</th>
                                                        <th>Tasa</th>
                                                        <th className="text-right">Base</th>
                                                        <th className="text-right">Importe</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {factura.impuestos_detalle.traslados.map((t, i) => (
                                                        <tr key={i}>
                                                            <td>{t.impuesto}</td>
                                                            <td>{t.tipo_factor}</td>
                                                            <td>{t.tasa}</td>
                                                            <td className="text-right">{formatMoney(t.base)}</td>
                                                            <td className="text-right">{formatMoney(t.importe)}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                    {factura.impuestos_detalle.retenciones.length > 0 && (
                                        <div>
                                            <div className="text-xs font-medium mb-1">Retenciones</div>
                                            <table className="table table-xs">
                                                <thead>
                                                    <tr>
                                                        <th>Impuesto</th>
                                                        <th className="text-right">Importe</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {factura.impuestos_detalle.retenciones.map((r, i) => (
                                                        <tr key={i}>
                                                            <td>{r.impuesto}</td>
                                                            <td className="text-right">{formatMoney(r.importe)}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>
                            </details>
                        )}
                    </div>
                </div>

                {/* Entregas */}
                <div className="mb-6">
                    <h2 className="text-lg font-medium mb-3">Entregas</h2>
                    {!hasEntregas ? (
                        <p className="text-base-content/60">No hay entregas registradas.</p>
                    ) : (
                        <div className="space-y-3">
                            {factura.entregas!.map((e) => (
                                <div key={e.id} className="rounded-lg border border-base-300 p-4">
                                    <div className="flex justify-between">
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">Entrega #{e.id}</span>
                                            <span className={`badge badge-sm ${e.tipo === 'completa' ? 'badge-success' : 'badge-warning'}`}>
                                                {ENTREGA_TIPO_LABELS[e.tipo]}
                                            </span>
                                        </div>
                                        <span className="text-sm text-base-content/60">
                                            {new Date(e.fecha_entrega).toLocaleDateString()} - {e.recibidor?.name}
                                        </span>
                                    </div>
                                    {e.observaciones && (
                                        <p className="text-sm text-base-content/60 mt-1">{e.observaciones}</p>
                                    )}
                                    {e.media?.path && (
                                        <a href={`/storage/${e.media.path}`} target="_blank" rel="noopener noreferrer" className="link link-primary text-sm inline-flex items-center gap-1 mt-1">
                                            <FileIcon className="size-3" /> Ver documento
                                        </a>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Pago */}
                {factura.pago && (
                    <div>
                        <h2 className="text-lg font-medium mb-3">Pago</h2>
                        <div className="rounded-lg border border-base-300 p-4">
                            <div className="flex items-center gap-3">
                                <Link href={`/admin/costos/pagos/${factura.pago.id}`} className="link link-primary font-medium">
                                    {factura.pago.folio}
                                </Link>
                                <span className={`badge ${PAGO_ESTATUS_COLORS[factura.pago.estatus]}`}>
                                    {PAGO_ESTATUS_LABELS[factura.pago.estatus]}
                                </span>
                                <span>{formatMoney(factura.pago.monto_pago)}</span>
                            </div>
                        </div>
                    </div>
                )}

                <div className="mt-8">
                    <h2 className="text-lg font-medium mb-3">Historial</h2>
                    <ActivityTimeline activities={factura.activities ?? []} />
                </div>

                {/* Aprobar Costos Modal */}
                {showAprobarModal && (
                    <dialog className="modal modal-open">
                        <div className="modal-box">
                            <h3 className="font-bold text-lg mb-4">Confirmar Aprobación de Costos</h3>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-base-content/60">Folio Factura</span>
                                    <span className="font-medium">{factura.folio}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-base-content/60">Proveedor</span>
                                    <span className="font-medium">{factura.proveedor?.razon_social}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-base-content/60">Total</span>
                                    <span className="font-medium">{formatMoney(factura.total)}</span>
                                </div>
                            </div>
                            <p className="mt-4 text-sm text-base-content/60">
                                Se aprobará la factura para su pago por contabilidad.
                            </p>
                            <div className="modal-action">
                                <Button variant="outline" onClick={() => setShowAprobarModal(false)} disabled={aprobarProcessing}>
                                    Cancelar
                                </Button>
                                <Button
                                    disabled={aprobarProcessing}
                                    onClick={() => {
                                        setAprobarProcessing(true);
                                        router.post(
                                            `/admin/costos/facturas/${factura.id}/aprobar-costos`,
                                            {},
                                            {
                                                preserveScroll: true,
                                                onFinish: () => {
                                                    setAprobarProcessing(false);
                                                    setShowAprobarModal(false);
                                                },
                                            },
                                        );
                                    }}
                                >
                                    {aprobarProcessing && <Loader2Icon className="size-4 animate-spin" />}
                                    Confirmar
                                </Button>
                            </div>
                        </div>
                        <div className="modal-backdrop" onClick={() => setShowAprobarModal(false)}></div>
                    </dialog>
                )}

                {/* Aceptar Contabilidad Modal */}
                {showAceptarModal && (
                    <dialog className="modal modal-open">
                        <div className="modal-box">
                            <h3 className="font-bold text-lg mb-4">Aceptar Factura y Programar Pago</h3>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-base-content/60">Folio Factura</span>
                                    <span className="font-medium">{factura.folio}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-base-content/60">Proveedor</span>
                                    <span className="font-medium">{factura.proveedor?.razon_social}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-base-content/60">Total</span>
                                    <span className="font-medium">{formatMoney(factura.total)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-base-content/60">Tipo de Pago</span>
                                    <span className="font-medium">
                                        {factura.proveedor?.maneja_credito
                                            ? `Crédito (${factura.proveedor.dias_credito_default} días)`
                                            : 'Contado'}
                                    </span>
                                </div>
                                {fechaPago && (
                                    <div className="flex justify-between">
                                        <span className="text-base-content/60">Fecha estimada de pago</span>
                                        <span className="font-medium">
                                            Viernes {fechaPago.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })}
                                        </span>
                                    </div>
                                )}
                            </div>
                            <p className="mt-4 text-sm text-base-content/60">
                                Se creará el pago programado al viernes más cercano y se notificará al proveedor por correo.
                            </p>
                            <div className="modal-action">
                                <Button variant="outline" onClick={() => setShowAceptarModal(false)} disabled={aceptarProcessing}>
                                    Cancelar
                                </Button>
                                <Button
                                    disabled={aceptarProcessing}
                                    onClick={() => {
                                        setAceptarProcessing(true);
                                        router.post(
                                            `/admin/costos/facturas/${factura.id}/aceptar-contabilidad`,
                                            {},
                                            {
                                                preserveScroll: true,
                                                onFinish: () => {
                                                    setAceptarProcessing(false);
                                                    setShowAceptarModal(false);
                                                },
                                            },
                                        );
                                    }}
                                >
                                    {aceptarProcessing && <Loader2Icon className="size-4 animate-spin" />}
                                    Aceptar y Programar Pago
                                </Button>
                            </div>
                        </div>
                        <div className="modal-backdrop" onClick={() => setShowAceptarModal(false)}></div>
                    </dialog>
                )}

                <CancelarModal
                    open={showCerrarModal}
                    onClose={() => setShowCerrarModal(false)}
                    url={`/admin/costos/facturas/${factura.id}/cancelar`}
                    title={`Cerrar factura ${factura.folio}`}
                    description="La factura quedará marcada como cancelada y no podrá continuar su flujo de aprobación o pago."
                    submitLabel="Cerrar factura"
                />

            </div>
        </AppLayout>
    );
}
