import { Head, Link } from '@inertiajs/react';
import { ChevronDownIcon, ChevronRightIcon, DownloadIcon, FileIcon, FileTextIcon, FolderIcon, FolderOpenIcon, PaperclipIcon } from 'lucide-react';
import { Fragment, useState } from 'react';
import { ActivityTimeline } from '@/components/costos/activity-timeline';
import { CancelarModal } from '@/components/costos/cancelar-modal';
import { DevolverItemModal } from '@/components/costos/devolver-item-modal';
import { EntregaModal } from '@/components/costos/entrega-modal';
import { formatMoney as fmtMonto } from '@/components/costos/monto';
import { CONTADO_STEPS, getContadoStep } from '@/components/costos/oc-contado';
import { SubirFacturaContadoModal } from '@/components/costos/subir-factura-contado-modal';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosOrdenCompra, CostosOrdenCompraEstatus, CostosRetencionDesglose } from '@/types/models';
import { DEVOLUCION_ESTATUS_COLORS, DEVOLUCION_ESTATUS_LABELS, FACTURA_ESTATUS_COLORS, FACTURA_ESTATUS_LABELS, MODO_PAGO_LABELS, ORDEN_COMPRA_ESTATUS_COLORS, ORDEN_COMPRA_ESTATUS_LABELS, TIPO_MONEDA_LABELS } from '@/types/models';

type Props = {
    ordenCompra: CostosOrdenCompra;
    retenciones: CostosRetencionDesglose | null;
};

const steps: { key: CostosOrdenCompraEstatus; label: string }[] = [
    { key: 'pendiente_entrega', label: 'Pend. Entrega' },
    { key: 'pendiente_factura', label: 'Pend. Factura' },
    { key: 'pendiente_aprobacion', label: 'Pend. Aprobación' },
    { key: 'pendiente_pago', label: 'Pend. Pago' },
    { key: 'pagada', label: 'Pagada' },
];

function getStepIndex(estatus: CostosOrdenCompraEstatus): number {
    if (estatus === 'cancelada') return -1;
    return steps.findIndex((s) => s.key === estatus);
}


type DevolverTarget = {
    entregaDetalleId: number;
    partidaDescripcion: string;
    unidad: string;
    cantidadDisponible: number;
};

export default function OrdenesCompraShow({ ordenCompra, retenciones }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/ordenes-compra' },
        { title: 'Ordenes de Compra', href: '/admin/costos/ordenes-compra' },
        { title: ordenCompra.folio, href: `/admin/costos/ordenes-compra/${ordenCompra.id}` },
    ];

    const { can } = useCan();
    // El operador del módulo ve todo el detalle; el solicitante que llega por
    // propiedad solo ve Datos y Documentos.
    const esOperador = can('costos.ordenes-compra.ver-todas');
    const esContado = ordenCompra.tipo_pago === 'contado';
    const activeSteps = esContado ? CONTADO_STEPS : steps;
    const currentStep = esContado ? getContadoStep(ordenCompra) : getStepIndex(ordenCompra.estatus);
    const [activeTab, setActiveTab] = useState<'datos' | 'facturas' | 'recepciones' | 'documentos' | 'historial'>('datos');
    const [showCancelarModal, setShowCancelarModal] = useState(false);
    const [showEntregaModal, setShowEntregaModal] = useState(false);
    const [showSubirFacturaModal, setShowSubirFacturaModal] = useState(false);
    const [devolverTarget, setDevolverTarget] = useState<DevolverTarget | null>(null);

    // Compras sube la factura de contado tras la recepción (paso "Subir factura").
    const puedeSubirFacturaContado = esContado
        && getContadoStep(ordenCompra) === 3
        && can('costos.facturas.crear');

    const formatMoney = (n: number) => fmtMonto(n, ordenCompra.moneda);

    const puedeCrearAnticipo = can('costos.anticipos.crear')
        && !['cancelada', 'pagada'].includes(ordenCompra.estatus);

    const puedeDevolver = can('costos.devoluciones.crear')
        && ordenCompra.estatus !== 'cancelada';

    const totalRecepciones = ordenCompra.entregas?.reduce(
        (sum, e) => sum + (e.detalles?.length ?? 0),
        0,
    ) ?? 0;

    const anticipoCreateUrl = `/admin/costos/anticipos/create?proveedor_id=${ordenCompra.proveedor_id}${ordenCompra.obra_id ? `&obra_id=${ordenCompra.obra_id}` : ''}`;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={ordenCompra.folio} />

            <div className="p-6">
                {/* Header */}
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">{ordenCompra.folio}</h1>
                        <div className="flex items-center gap-3 mt-1">
                            {ordenCompra.retrasada ? (
                                <span className="badge badge-error">ENTREGA RETRASADA</span>
                            ) : (
                                <span className={`badge ${ORDEN_COMPRA_ESTATUS_COLORS[ordenCompra.estatus]}`}>
                                    {ORDEN_COMPRA_ESTATUS_LABELS[ordenCompra.estatus]}
                                </span>
                            )}
                            {ordenCompra.tipo_pago && (
                                <span className={`badge ${ordenCompra.tipo_pago === 'credito' ? 'badge-warning' : 'badge-success'}`}>
                                    {MODO_PAGO_LABELS[ordenCompra.tipo_pago]}
                                </span>
                            )}
                            <span className="text-lg font-semibold">{formatMoney(ordenCompra.total)}</span>
                            {ordenCompra.pagada_anticipo_contado && (
                                <span className="badge badge-success">Pagada (anticipo contado)</span>
                            )}
                            {ordenCompra.solicitudes_pago?.[0] && (
                                <Link
                                    href={`/admin/costos/solicitudes-pago/${ordenCompra.solicitudes_pago[0].id}`}
                                    className="link link-primary text-sm"
                                >
                                    Ver solicitud {ordenCompra.solicitudes_pago[0].folio}
                                </Link>
                            )}
                            {ordenCompra.requisicion_id && (
                                <Link
                                    href={`/admin/costos/requisiciones/${ordenCompra.requisicion_id}`}
                                    className="link link-primary text-sm"
                                >
                                    Ver requisición de origen
                                </Link>
                            )}
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <a
                            href={`/admin/costos/ordenes-compra/${ordenCompra.id}/pdf-oc?download=1`}
                            className="btn bg-blue-600 hover:bg-blue-700 text-white gap-1.5"
                        >
                            <DownloadIcon className="size-4" /> Descargar OC
                        </a>
                        {['pendiente_entrega', 'pendiente_factura', 'pendiente_aprobacion'].includes(ordenCompra.estatus) && can('costos.entregas.crear') && (
                            <Button onClick={() => setShowEntregaModal(true)}>Registrar entrega</Button>
                        )}
                        {puedeSubirFacturaContado && (
                            <Button onClick={() => setShowSubirFacturaModal(true)}>Subir factura</Button>
                        )}
                        {puedeCrearAnticipo && (
                            <Button variant="outline" asChild>
                                <Link href={anticipoCreateUrl}>Crear anticipo</Link>
                            </Button>
                        )}
                        {['pendiente_entrega', 'pendiente_factura', 'pendiente_aprobacion'].includes(ordenCompra.estatus) && can('costos.ordenes-compra.cancelar') && (
                            <Button variant="destructive" onClick={() => setShowCancelarModal(true)}>Cancelar</Button>
                        )}
                    </div>
                </div>

                {/* Stepper */}
                {ordenCompra.estatus !== 'cancelada' && (
                    <ul className="steps steps-horizontal w-full mb-6">
                        {activeSteps.map((step, idx) => (
                            <li key={step.key} className={`step ${idx <= currentStep ? 'step-primary' : ''}`}>
                                {step.label}
                            </li>
                        ))}
                    </ul>
                )}

                {/* Panel de saldos */}
                <div className="stats stats-horizontal shadow mb-6 w-full">
                    <div className="stat">
                        <div className="stat-title">Total OC</div>
                        <div className="stat-value text-base">{formatMoney(ordenCompra.total)}</div>
                    </div>
                    <div className="stat">
                        <div className="stat-title">Facturado</div>
                        <div className="stat-value text-base">{formatMoney(ordenCompra.total_facturado ?? 0)}</div>
                    </div>
                    <div className="stat">
                        <div className="stat-title">Pagado</div>
                        <div className="stat-value text-base">{formatMoney(ordenCompra.total_pagado ?? 0)}</div>
                    </div>
                    <div className="stat">
                        <div className="stat-title">Saldo Pendiente</div>
                        <div className={`stat-value text-base ${(ordenCompra.saldo_pendiente ?? 0) > 0 ? 'text-warning' : 'text-success'}`}>
                            {formatMoney(ordenCompra.saldo_pendiente ?? 0)}
                        </div>
                    </div>
                </div>

                {/* Tabs */}
                <div className="tabs tabs-bordered mb-6">
                    <button className={`tab ${activeTab === 'datos' ? 'tab-active' : ''}`} onClick={() => setActiveTab('datos')}>Datos</button>
                    {esOperador && (
                        <button className={`tab ${activeTab === 'facturas' ? 'tab-active' : ''}`} onClick={() => setActiveTab('facturas')}>Facturas ({ordenCompra.facturas?.length ?? 0})</button>
                    )}
                    {esOperador && (
                        <button className={`tab ${activeTab === 'recepciones' ? 'tab-active' : ''}`} onClick={() => setActiveTab('recepciones')}>Recepciones ({totalRecepciones})</button>
                    )}
                    <button className={`tab ${activeTab === 'documentos' ? 'tab-active' : ''}`} onClick={() => setActiveTab('documentos')}>Documentos</button>
                    {esOperador && (
                        <button className={`tab ${activeTab === 'historial' ? 'tab-active' : ''}`} onClick={() => setActiveTab('historial')}>Historial ({ordenCompra.activities?.length ?? 0})</button>
                    )}
                </div>

                {activeTab === 'datos' && (
                    <div className="space-y-6">
                        {/* Info general */}
                        <div className="grid grid-cols-2 gap-6">
                            <div className="space-y-3">
                                <div>
                                    <span className="text-sm text-base-content/60">Proveedor</span>
                                    <p className="font-medium">{ordenCompra.proveedor?.razon_social}</p>
                                </div>
                                <div>
                                    <span className="text-sm text-base-content/60">Departamento</span>
                                    <p className="font-medium">{ordenCompra.departamento?.descripcion}</p>
                                </div>
                                {ordenCompra.referencia && (
                                    <div>
                                        <span className="text-sm text-base-content/60">Folio Referencia</span>
                                        <p className="font-medium">{ordenCompra.referencia}</p>
                                    </div>
                                )}
                            </div>
                            <div className="space-y-3">
                                <div>
                                    <span className="text-sm text-base-content/60">Moneda</span>
                                    <p className="font-medium">{TIPO_MONEDA_LABELS[ordenCompra.moneda] ?? ordenCompra.moneda}</p>
                                </div>
                                <div>
                                    <span className="text-sm text-base-content/60">Fecha Entrega Esperada</span>
                                    <p className="font-medium">{ordenCompra.fecha_entrega_esperada ? new Date(ordenCompra.fecha_entrega_esperada).toLocaleDateString() : '-'}</p>
                                </div>
                                {ordenCompra.media?.find((m) => m.descripcion === 'oc_archivo') && (
                                    <div>
                                        <span className="text-sm text-base-content/60">Archivo</span>
                                        <p>
                                            <a href={`/storage/${ordenCompra.media!.find((m) => m.descripcion === 'oc_archivo')!.path}`} target="_blank" rel="noopener noreferrer" className="link link-primary inline-flex items-center gap-1">
                                                <FileIcon className="size-4" /> Ver archivo
                                            </a>
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {ordenCompra.notas && (
                            <div>
                                <span className="text-sm text-base-content/60">Notas</span>
                                <p>{ordenCompra.notas}</p>
                            </div>
                        )}

                        {/* Rubros table */}
                        <div>
                            <h2 className="text-lg font-medium mb-3">Partidas</h2>
                            <div className="overflow-x-auto">
                                <table className="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Descripción</th>
                                            <th>Centro de Costos</th>
                                            <th>Uso CFDI</th>
                                            <th className="text-right">Cantidad</th>
                                            <th>Unidad</th>
                                            <th className="text-right">P. Unitario</th>
                                            <th className="text-right">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {ordenCompra.detalles?.map((d) => (
                                            <tr key={d.id}>
                                                <td>{d.descripcion}</td>
                                                <td>{d.obra_rubro?.rubro?.codigo} - {d.obra_rubro?.rubro?.descripcion}</td>
                                                <td>{d.uso_cfdi ? `${d.uso_cfdi.clave}` : '-'}</td>
                                                <td className="text-right">{Number(d.cantidad).toLocaleString('es-MX')}</td>
                                                <td>{d.unidad}</td>
                                                <td className="text-right">{formatMoney(d.precio_unitario)}</td>
                                                <td className="text-right">{formatMoney(d.subtotal)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {retenciones && (
                            <div className="flex justify-end">
                                <div className="w-full max-w-sm rounded-lg border border-base-300 p-3 text-sm">
                                    <div className="grid grid-cols-[1fr_auto] gap-y-1">
                                        <span className="text-base-content/60">Subtotal</span>
                                        <span className="text-right">{formatMoney(retenciones.subtotal)}</span>
                                        <span className="text-base-content/60">IVA trasladado</span>
                                        <span className="text-right">{formatMoney(retenciones.iva)}</span>
                                        {retenciones.retenciones.map((r) => (
                                            <Fragment key={r.clave}>
                                                <span className="text-error/80">Ret. {r.concepto} ({(r.tasa * 100).toFixed(2)}%)</span>
                                                <span className="text-right text-error/80">−{formatMoney(r.monto)}</span>
                                            </Fragment>
                                        ))}
                                        <span className="mt-1 border-t border-base-300 pt-1 font-semibold">Total neto a pagar</span>
                                        <span className="mt-1 border-t border-base-300 pt-1 text-right font-semibold">{formatMoney(retenciones.total_neto)}</span>
                                    </div>
                                    {retenciones.retenciones.length > 0 && (
                                        <p className="mt-2 text-[11px] text-base-content/50">Retenciones informativas; se corroboran al recibir la factura.</p>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {activeTab === 'facturas' && (
                    <div>
                        {(!ordenCompra.facturas || ordenCompra.facturas.length === 0) ? (
                            <p className="text-base-content/60">No hay facturas registradas.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Folio</th>
                                            <th>Fecha</th>
                                            <th className="text-right">Total</th>
                                            <th>Estatus</th>
                                            <th>Entregas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {ordenCompra.facturas.map((f) => (
                                            <tr key={f.id}>
                                                <td>
                                                    <Link href={`/admin/costos/facturas/${f.id}`} className="link link-primary">
                                                        {f.folio}
                                                    </Link>
                                                    {(f.notas_credito?.length ?? 0) > 0 && (
                                                        <div className="mt-1">
                                                            {f.notas_credito!.map((nc) => (
                                                                <Link key={nc.id} href={`/admin/costos/notas-credito/${nc.id}`} className="badge badge-sm badge-outline badge-warning mr-1">
                                                                    NC: {nc.folio} ({formatMoney(nc.monto)})
                                                                </Link>
                                                            ))}
                                                        </div>
                                                    )}
                                                </td>
                                                <td>{f.fecha_factura ? new Date(f.fecha_factura).toLocaleDateString() : '-'}</td>
                                                <td className="text-right">{formatMoney(f.total)}</td>
                                                <td>
                                                    <span className={`badge ${FACTURA_ESTATUS_COLORS[f.estatus]}`}>
                                                        {FACTURA_ESTATUS_LABELS[f.estatus]}
                                                    </span>
                                                </td>
                                                <td>{f.entregas?.length ?? 0}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                )}

                {activeTab === 'recepciones' && (
                    <div className="space-y-4">
                        {(!ordenCompra.entregas || ordenCompra.entregas.length === 0) ? (
                            <p className="text-base-content/60">No hay entregas registradas.</p>
                        ) : (
                            ordenCompra.entregas.map((entrega) => (
                                <div key={entrega.id} className="rounded-lg border border-base-300 p-4">
                                    <div className="flex items-center justify-between mb-2">
                                        <div>
                                            <span className="font-medium">{entrega.folio ?? `Entrega #${entrega.id}`}</span>
                                            <span className="ml-2 badge badge-sm badge-outline">{entrega.tipo}</span>
                                            <span className="ml-2 text-sm text-base-content/60">
                                                {new Date(entrega.fecha_entrega).toLocaleDateString()}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            {entrega.recibidor?.name && (
                                                <span className="text-sm text-base-content/60">
                                                    Recibió: {entrega.recibidor.name}
                                                </span>
                                            )}
                                            <a
                                                href={`/admin/costos/entregas/${entrega.id}/pdf`}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="btn btn-xs btn-outline"
                                            >
                                                PDF
                                            </a>
                                        </div>
                                    </div>

                                    {entrega.observaciones && (
                                        <p className="text-sm text-base-content/60 mb-2">{entrega.observaciones}</p>
                                    )}

                                    <table className="table table-xs">
                                        <thead>
                                            <tr>
                                                <th>Partida</th>
                                                <th className="text-right">Recibido</th>
                                                <th className="text-right">P.U. recibido</th>
                                                <th className="text-right">Devuelto</th>
                                                <th className="text-right">Disponible</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {entrega.detalles?.map((d) => {
                                                const partida = d.orden_compra_detalle ?? ordenCompra.detalles?.find((od) => od.id === d.orden_compra_detalle_id);
                                                const descripcion = partida?.descripcion ?? `Partida #${d.orden_compra_detalle_id}`;
                                                const unidad = partida?.unidad ?? '';
                                                const recibida = Number(d.cantidad_recibida);
                                                const devueltaVigente = (d.devoluciones ?? [])
                                                    .filter((dev) => dev.estatus === 'vigente')
                                                    .reduce((s, dev) => s + Number(dev.cantidad), 0);
                                                const disponible = Math.max(0, recibida - devueltaVigente);
                                                const puOc = Number(partida?.precio_unitario ?? 0);
                                                const puRecibido = d.precio_unitario != null ? Number(d.precio_unitario) : puOc;
                                                const difiere = Math.abs(puRecibido - puOc) >= 0.005;

                                                return (
                                                    <tr key={d.id}>
                                                        <td>{descripcion}</td>
                                                        <td className="text-right">
                                                            {recibida.toLocaleString('es-MX')} {unidad}
                                                        </td>
                                                        <td className="text-right">
                                                            ${puRecibido.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                            {difiere && (
                                                                <span
                                                                    className="ml-1 badge badge-xs badge-warning"
                                                                    title={`Precio OC: $${puOc.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`}
                                                                >
                                                                    ≠ OC
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="text-right">
                                                            {devueltaVigente.toLocaleString('es-MX')}
                                                        </td>
                                                        <td className="text-right">
                                                            <strong>{disponible.toLocaleString('es-MX')}</strong>
                                                        </td>
                                                        <td className="text-right">
                                                            {puedeDevolver && disponible > 0.001 ? (
                                                                <Button
                                                                    variant="outline"
                                                                    onClick={() => setDevolverTarget({
                                                                        entregaDetalleId: d.id,
                                                                        partidaDescripcion: descripcion,
                                                                        unidad,
                                                                        cantidadDisponible: disponible,
                                                                    })}
                                                                >
                                                                    Devolver
                                                                </Button>
                                                            ) : (
                                                                <span className="text-xs text-base-content/40">—</span>
                                                            )}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>

                                    {entrega.detalles?.some((d) => (d.devoluciones ?? []).length > 0) && (
                                        <div className="mt-3 border-t border-base-200 pt-3">
                                            <p className="text-xs text-base-content/60 mb-2">Devoluciones registradas</p>
                                            <table className="table table-xs">
                                                <thead>
                                                    <tr>
                                                        <th>Folio</th>
                                                        <th>Partida</th>
                                                        <th className="text-right">Cantidad</th>
                                                        <th>Fecha</th>
                                                        <th>Motivo</th>
                                                        <th>Estatus</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {entrega.detalles?.flatMap((d) =>
                                                        (d.devoluciones ?? []).map((dev) => {
                                                            const partida = d.orden_compra_detalle ?? ordenCompra.detalles?.find((od) => od.id === d.orden_compra_detalle_id);
                                                            return (
                                                                <tr key={dev.id}>
                                                                    <td>
                                                                        <Link href={`/admin/costos/devoluciones/${dev.id}`} className="link link-primary font-mono text-xs">
                                                                            {dev.folio}
                                                                        </Link>
                                                                    </td>
                                                                    <td className="text-xs">{partida?.descripcion ?? '-'}</td>
                                                                    <td className="text-right">{Number(dev.cantidad).toLocaleString('es-MX')}</td>
                                                                    <td className="text-xs">{dev.fecha}</td>
                                                                    <td className="text-xs">{dev.motivo}</td>
                                                                    <td>
                                                                        <span className={`badge badge-xs ${DEVOLUCION_ESTATUS_COLORS[dev.estatus]}`}>
                                                                            {DEVOLUCION_ESTATUS_LABELS[dev.estatus]}
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                            );
                                                        })
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>
                            ))
                        )}
                    </div>
                )}

                {activeTab === 'documentos' && (
                    <DocumentosTree ordenCompra={ordenCompra} />
                )}

                {activeTab === 'historial' && (
                    <ActivityTimeline activities={ordenCompra.activities ?? []} />
                )}

                <CancelarModal
                    open={showCancelarModal}
                    onClose={() => setShowCancelarModal(false)}
                    url={`/admin/costos/ordenes-compra/${ordenCompra.id}/cancelar`}
                    title={`Cancelar orden ${ordenCompra.folio}`}
                    description="La orden quedará cancelada y se revertirá su impacto presupuestal. Esta acción no se puede deshacer."
                    submitLabel="Cancelar orden"
                />

                <EntregaModal
                    open={showEntregaModal}
                    onClose={() => setShowEntregaModal(false)}
                    ordenCompra={ordenCompra}
                />

                <SubirFacturaContadoModal
                    ordenCompraId={ordenCompra.id}
                    open={showSubirFacturaModal}
                    onClose={() => setShowSubirFacturaModal(false)}
                />

                {devolverTarget && (
                    <DevolverItemModal
                        entregaDetalleId={devolverTarget.entregaDetalleId}
                        partidaDescripcion={devolverTarget.partidaDescripcion}
                        unidad={devolverTarget.unidad}
                        cantidadDisponible={devolverTarget.cantidadDisponible}
                        open={true}
                        onClose={() => setDevolverTarget(null)}
                    />
                )}
            </div>
        </AppLayout>
    );
}

// ---------- Filetree de Documentos ----------

function TreeFolder({ label, defaultOpen = true, children }: { label: string; defaultOpen?: boolean; children: React.ReactNode }) {
    const [open, setOpen] = useState(defaultOpen);
    return (
        <div>
            <button onClick={() => setOpen(!open)} className="flex items-center gap-1.5 py-1 text-sm font-medium hover:text-primary">
                {open ? <ChevronDownIcon className="size-4" /> : <ChevronRightIcon className="size-4" />}
                {open ? <FolderOpenIcon className="size-4 text-warning" /> : <FolderIcon className="size-4 text-warning" />}
                {label}
            </button>
            {open && <div className="ml-6 border-l border-base-300 pl-3">{children}</div>}
        </div>
    );
}

type PreviewFn = (url: string, title: string) => void;

function TreeFile({ label, href, pending, onPreview }: { label: string; href?: string; pending?: string; onPreview?: PreviewFn }) {
    if (!href) {
        return (
            <div className="flex items-center gap-1.5 py-0.5 text-sm text-base-content/40">
                <FileIcon className="size-3.5" />
                <span>{label}</span>
                {pending && <span className="ml-1 text-xs italic">({pending})</span>}
            </div>
        );
    }
    return (
        <button
            onClick={() => onPreview?.(href, label)}
            className="flex items-center gap-1.5 py-0.5 text-sm text-primary hover:underline"
        >
            <FileTextIcon className="size-3.5" />
            <span>{label}</span>
        </button>
    );
}

function TreeAttachment({ label, href, onPreview }: { label: string; href?: string; onPreview?: PreviewFn }) {
    if (!href) return null;
    const isXml = href.endsWith('.xml') || href.endsWith('.txt');
    if (isXml) {
        return (
            <a href={href} download className="flex items-center gap-1.5 py-0.5 text-sm text-primary hover:underline">
                <PaperclipIcon className="size-3.5" />
                <span>{label}</span>
            </a>
        );
    }
    return (
        <button
            onClick={() => onPreview?.(href, label)}
            className="flex items-center gap-1.5 py-0.5 text-sm text-primary hover:underline"
        >
            <PaperclipIcon className="size-3.5" />
            <span>{label}</span>
        </button>
    );
}

function DocPreviewModal({ url, title, onClose }: { url: string; title: string; onClose: () => void }) {
    const isImage = /\.(jpg|jpeg|png|webp|gif)$/i.test(url);
    return (
        <dialog className="modal modal-open">
            <div className="modal-box w-11/12 max-w-5xl h-[85vh] flex flex-col p-0">
                <div className="flex items-center justify-between border-b border-base-300 px-4 py-2">
                    <h3 className="text-sm font-semibold">{title}</h3>
                    <div className="flex items-center gap-2">
                        <a href={url} target="_blank" rel="noopener noreferrer" className="btn btn-ghost btn-xs">Abrir en nueva pestaña</a>
                        <button onClick={onClose} className="btn btn-ghost btn-xs">Cerrar</button>
                    </div>
                </div>
                <div className="flex-1 overflow-hidden">
                    {isImage ? (
                        <img src={url} alt={title} className="h-full w-full object-contain p-4" />
                    ) : (
                        <iframe src={url} className="h-full w-full" title={title} />
                    )}
                </div>
            </div>
            <div className="modal-backdrop" onClick={onClose} />
        </dialog>
    );
}

function DocumentosTree({ ordenCompra }: { ordenCompra: CostosOrdenCompra }) {
    const fmtDate = (d: string | null) => d ? new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '';
    const fmtMoney = (n: number) => fmtMonto(n, ordenCompra.moneda);
    const baseUrl = `/admin/costos/ordenes-compra/${ordenCompra.id}`;
    const [preview, setPreview] = useState<{ url: string; title: string } | null>(null);
    const openPreview: PreviewFn = (url, title) => setPreview({ url, title });

    return (
        <div className="space-y-1 rounded-lg border border-base-300 p-4">
            {/* Requisicion */}
            {ordenCompra.requisicion_id && (
                <TreeFolder label="Requisicion">
                    <TreeFile label="Comparativo de proveedores (PDF)" href={`${baseUrl}/pdf-requisicion`} onPreview={openPreview} />
                </TreeFolder>
            )}

            {/* Orden de Compra */}
            <TreeFolder label="Orden de Compra">
                <TreeFile label="Formato OC (PDF)" href={`${baseUrl}/pdf-oc`} onPreview={openPreview} />
            </TreeFolder>

            {/* Solicitudes de pago (flujo de contado) */}
            {(ordenCompra.solicitudes_pago?.length ?? 0) > 0 && (
                <TreeFolder label="Solicitudes de pago">
                    {ordenCompra.solicitudes_pago!.map((sol) => (
                        <TreeFile
                            key={sol.id}
                            label={`Solicitud ${sol.folio} (PDF)`}
                            href={`/admin/costos/solicitudes-pago/${sol.id}/pdf`}
                            onPreview={openPreview}
                        />
                    ))}
                </TreeFolder>
            )}

            {/* Recepciones */}
            <TreeFolder label="Recepciones">
                {(ordenCompra.entregas?.length ?? 0) === 0 ? (
                    <TreeFile label="Evidencia de recepcion" pending="Pendiente de entrega" />
                ) : (
                    ordenCompra.entregas!.map((entrega) => {
                        const evidencia = entrega.media && entrega.media.descripcion === 'evidencia_recepcion' ? entrega.media : null;
                        return (
                            <TreeFolder key={entrega.id} label={`${entrega.folio ?? `Entrega #${entrega.id}`} — ${fmtDate(entrega.fecha_entrega)}`}>
                                <TreeFile label="Recepcion (PDF)" href={`/admin/costos/entregas/${entrega.id}/pdf`} onPreview={openPreview} />
                                {evidencia ? (
                                    <TreeAttachment label="Evidencia de recepcion" href={`/storage/${evidencia.path}`} onPreview={openPreview} />
                                ) : (
                                    <TreeFile label="Evidencia de recepcion" pending="Sin archivo" />
                                )}
                            </TreeFolder>
                        );
                    })
                )}
            </TreeFolder>

            {/* Facturas */}
            <TreeFolder label="Facturas">
                {(ordenCompra.facturas?.length ?? 0) === 0 ? (
                    <TreeFile label="Factura" pending="Pendiente de facturacion" />
                ) : (
                    ordenCompra.facturas!.map((factura) => {
                        const pdfMedia = factura.media?.find((m: any) => m.descripcion === 'pdf_factura');
                        const xmlMedia = factura.media?.find((m: any) => m.descripcion === 'xml_factura');
                        return (
                            <TreeFolder key={factura.id} label={`${factura.folio} — ${fmtMoney(factura.total)}`}>
                                {pdfMedia ? (
                                    <TreeAttachment label="Factura PDF" href={`/storage/${pdfMedia.path}`} onPreview={openPreview} />
                                ) : (
                                    <TreeFile label="Factura PDF" pending="Sin archivo" />
                                )}
                                {xmlMedia && (
                                    <TreeAttachment label="XML CFDI" href={`/storage/${xmlMedia.path}`} />
                                )}
                                <TreeFile label="Contrarecibo (PDF)" href={`${baseUrl}/pdf-contrarecibo/${factura.id}`} onPreview={openPreview} />
                                {(factura.notas_credito ?? []).map((nc) => {
                                    const ncPdf = nc.media?.find((m: any) => m.descripcion === 'pdf_nota_credito');
                                    const ncXml = nc.media?.find((m: any) => m.descripcion === 'xml_nota_credito');
                                    return (
                                        <TreeFolder key={nc.id} label={`NC: ${nc.folio} — ${fmtMoney(nc.monto)}`}>
                                            {ncPdf && <TreeAttachment label="Nota de credito PDF" href={`/storage/${ncPdf.path}`} onPreview={openPreview} />}
                                            {ncXml && <TreeAttachment label="XML Nota de credito" href={`/storage/${ncXml.path}`} />}
                                            {!ncPdf && !ncXml && <TreeFile label="Nota de credito" pending="Sin archivos" />}
                                        </TreeFolder>
                                    );
                                })}
                            </TreeFolder>
                        );
                    })
                )}
            </TreeFolder>

            {/* Pagos (de facturas a crédito y del anticipo de contado vía solicitud) */}
            <TreeFolder label="Pagos">
                {(() => {
                    const pagosFactura = ordenCompra.facturas?.flatMap((f) => f.pago ? [{ ...f.pago, origen: `Factura ${f.folio}` }] : []) ?? [];
                    const pagosSolicitud = ordenCompra.solicitudes_pago?.flatMap((s) => s.pago ? [{ ...s.pago, origen: `Solicitud ${s.folio}` }] : []) ?? [];
                    const pagos = [...pagosSolicitud, ...pagosFactura];
                    if (pagos.length === 0) {
                        return <TreeFile label="Comprobante de pago" pending="Pendiente de pago" />;
                    }
                    return pagos.map((pago: any) => {
                        // Pago.media es morphOne (objeto único), no un arreglo.
                        const comprobante = pago.media && pago.media.descripcion === 'comprobante_pago' ? pago.media : null;
                        return (
                            <TreeFolder key={`${pago.origen}-${pago.id}`} label={`${pago.origen} · ${pago.folio} — ${fmtMoney(Number(pago.monto_pago ?? pago.monto ?? 0))}`}>
                                {comprobante ? (
                                    <TreeAttachment label="Comprobante de pago" href={`/storage/${comprobante.path}`} onPreview={openPreview} />
                                ) : (
                                    <TreeFile label="Comprobante de pago" pending={pago.estatus === 'pagado' ? 'Sin archivo' : 'Pendiente'} />
                                )}
                            </TreeFolder>
                        );
                    });
                })()}
            </TreeFolder>

            {preview && (
                <DocPreviewModal url={preview.url} title={preview.title} onClose={() => setPreview(null)} />
            )}
        </div>
    );
}
