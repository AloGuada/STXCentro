import { ActivityTimeline } from '@/components/costos/activity-timeline';
import { CancelarModal } from '@/components/costos/cancelar-modal';
import { DevolverItemModal } from '@/components/costos/devolver-item-modal';
import { EntregaModal } from '@/components/costos/entrega-modal';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosOrdenCompra, CostosOrdenCompraEstatus } from '@/types/models';
import { DEVOLUCION_ESTATUS_COLORS, DEVOLUCION_ESTATUS_LABELS, FACTURA_ESTATUS_COLORS, FACTURA_ESTATUS_LABELS, ORDEN_COMPRA_ESTATUS_COLORS, ORDEN_COMPRA_ESTATUS_LABELS, TIPO_MONEDA_LABELS } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { FileIcon } from 'lucide-react';
import { useState } from 'react';

type Props = {
    ordenCompra: CostosOrdenCompra;
};

const steps: { key: CostosOrdenCompraEstatus; label: string }[] = [
    { key: 'pendiente_factura', label: 'Pend. Factura' },
    { key: 'pendiente_entrega', label: 'Pend. Entrega' },
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

export default function OrdenesCompraShow({ ordenCompra }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/ordenes-compra' },
        { title: 'Ordenes de Compra', href: '/admin/costos/ordenes-compra' },
        { title: ordenCompra.folio, href: `/admin/costos/ordenes-compra/${ordenCompra.id}` },
    ];

    const { can } = useCan();
    const currentStep = getStepIndex(ordenCompra.estatus);
    const [activeTab, setActiveTab] = useState<'datos' | 'facturas' | 'recepciones' | 'historial'>('datos');
    const [showCancelarModal, setShowCancelarModal] = useState(false);
    const [showEntregaModal, setShowEntregaModal] = useState(false);
    const [devolverTarget, setDevolverTarget] = useState<DevolverTarget | null>(null);

    const formatMoney = (n: number) => `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

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
                            <span className={`badge ${ORDEN_COMPRA_ESTATUS_COLORS[ordenCompra.estatus]}`}>
                                {ORDEN_COMPRA_ESTATUS_LABELS[ordenCompra.estatus]}
                            </span>
                            <span className="text-lg font-semibold">{formatMoney(ordenCompra.total)}</span>
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
                        {['pendiente_factura', 'pendiente_entrega', 'pendiente_aprobacion'].includes(ordenCompra.estatus) && can('costos.entregas.crear') && (
                            <Button onClick={() => setShowEntregaModal(true)}>Registrar entrega</Button>
                        )}
                        {puedeCrearAnticipo && (
                            <Button variant="outline" asChild>
                                <Link href={anticipoCreateUrl}>Crear anticipo</Link>
                            </Button>
                        )}
                        {['pendiente_factura', 'pendiente_entrega', 'pendiente_aprobacion'].includes(ordenCompra.estatus) && can('costos.ordenes-compra.cancelar') && (
                            <Button variant="destructive" onClick={() => setShowCancelarModal(true)}>Cancelar</Button>
                        )}
                    </div>
                </div>

                {/* Stepper */}
                {ordenCompra.estatus !== 'cancelada' && (
                    <ul className="steps steps-horizontal w-full mb-6">
                        {steps.map((step, idx) => (
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
                    <button className={`tab ${activeTab === 'facturas' ? 'tab-active' : ''}`} onClick={() => setActiveTab('facturas')}>Facturas ({ordenCompra.facturas?.length ?? 0})</button>
                    <button className={`tab ${activeTab === 'recepciones' ? 'tab-active' : ''}`} onClick={() => setActiveTab('recepciones')}>Recepciones ({totalRecepciones})</button>
                    <button className={`tab ${activeTab === 'historial' ? 'tab-active' : ''}`} onClick={() => setActiveTab('historial')}>Historial ({ordenCompra.activities?.length ?? 0})</button>
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
                                            <th>Rubro</th>
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
                                            <span className="font-medium">Entrega #{entrega.id}</span>
                                            <span className="ml-2 badge badge-sm badge-outline">{entrega.tipo}</span>
                                            <span className="ml-2 text-sm text-base-content/60">
                                                {new Date(entrega.fecha_entrega).toLocaleDateString()}
                                            </span>
                                        </div>
                                        {entrega.recibidor?.name && (
                                            <span className="text-sm text-base-content/60">
                                                Recibió: {entrega.recibidor.name}
                                            </span>
                                        )}
                                    </div>

                                    {entrega.observaciones && (
                                        <p className="text-sm text-base-content/60 mb-2">{entrega.observaciones}</p>
                                    )}

                                    <table className="table table-xs">
                                        <thead>
                                            <tr>
                                                <th>Partida</th>
                                                <th className="text-right">Recibido</th>
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

                                                return (
                                                    <tr key={d.id}>
                                                        <td>{descripcion}</td>
                                                        <td className="text-right">
                                                            {recibida.toLocaleString('es-MX')} {unidad}
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
