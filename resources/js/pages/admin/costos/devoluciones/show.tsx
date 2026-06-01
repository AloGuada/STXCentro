import { CancelarModal } from '@/components/costos/cancelar-modal';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosDevolucion } from '@/types/models';
import { DEVOLUCION_ESTATUS_COLORS, DEVOLUCION_ESTATUS_LABELS } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { FileIcon } from 'lucide-react';
import { useState } from 'react';

type Props = {
    devolucion: CostosDevolucion;
};

export default function DevolucionShow({ devolucion }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/devoluciones' },
        { title: 'Devoluciones', href: '/admin/costos/devoluciones' },
        { title: devolucion.folio, href: `/admin/costos/devoluciones/${devolucion.id}` },
    ];

    const { can } = useCan();
    const [cancelando, setCancelando] = useState(false);

    const ed = devolucion.entrega_detalle;
    const oc = ed?.entrega?.orden_compra;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={devolucion.folio} />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">{devolucion.folio}</h1>
                        <div className="mt-1 flex flex-wrap items-center gap-2">
                            <span className={`badge ${DEVOLUCION_ESTATUS_COLORS[devolucion.estatus]}`}>
                                {DEVOLUCION_ESTATUS_LABELS[devolucion.estatus]}
                            </span>
                            <span className="text-sm text-base-content/60">{devolucion.fecha}</span>
                            {oc && (
                                <Link
                                    href={`/admin/costos/ordenes-compra/${oc.id}`}
                                    className="link link-primary text-sm"
                                >
                                    OC {oc.folio}
                                </Link>
                            )}
                        </div>
                    </div>

                    {devolucion.estatus === 'vigente' && can('costos.devoluciones.cancelar') && (
                        <Button variant="outline" className="text-error" onClick={() => setCancelando(true)}>
                            Cancelar devolución
                        </Button>
                    )}
                </div>

                <div className="mb-6 rounded-lg border border-base-300 p-4">
                    <h2 className="mb-3 font-medium">Datos de la devolución</h2>
                    <dl className="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                        <div>
                            <dt className="text-base-content/60">Partida</dt>
                            <dd>{ed?.orden_compra_detalle?.descripcion ?? '-'}</dd>
                        </div>
                        <div>
                            <dt className="text-base-content/60">Cantidad devuelta</dt>
                            <dd className="font-semibold">
                                {Number(devolucion.cantidad).toLocaleString('es-MX')} {ed?.orden_compra_detalle?.unidad ?? ''}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-base-content/60">Cantidad recibida original</dt>
                            <dd>{ed ? Number(ed.cantidad_recibida).toLocaleString('es-MX') : '-'}</dd>
                        </div>
                        <div>
                            <dt className="text-base-content/60">Proveedor</dt>
                            <dd>{oc?.proveedor?.razon_social ?? '-'}</dd>
                        </div>
                        <div>
                            <dt className="text-base-content/60">Registró</dt>
                            <dd>{devolucion.creador?.name ?? '-'}</dd>
                        </div>
                        <div className="md:col-span-3">
                            <dt className="text-base-content/60">Motivo</dt>
                            <dd className="whitespace-pre-line">{devolucion.motivo}</dd>
                        </div>
                    </dl>
                </div>

                {devolucion.evidencia && (
                    <div className="mb-6 rounded-lg border border-base-300 p-4">
                        <h2 className="mb-3 font-medium">Evidencia</h2>
                        <a href={`/storage/${devolucion.evidencia.path}`} target="_blank" rel="noopener noreferrer"
                            className="link link-primary text-sm inline-flex items-center gap-1">
                            <FileIcon className="size-3.5" /> {devolucion.evidencia.nombre_original}
                        </a>
                    </div>
                )}

                {devolucion.motivo_cancelacion && (
                    <div className="alert alert-error mb-4">
                        <span><strong>Motivo de cancelación:</strong> {devolucion.motivo_cancelacion}</span>
                    </div>
                )}

                <CancelarModal
                    open={cancelando}
                    onClose={() => setCancelando(false)}
                    url={`/admin/costos/devoluciones/${devolucion.id}/cancelar`}
                    title="Cancelar devolución"
                    description="La cantidad devuelta volverá a sumarse al neto recibido."
                />
            </div>
        </AppLayout>
    );
}
