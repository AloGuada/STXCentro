import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosAprobacionSolicitud } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/solicitudes-pago' },
    { title: 'Mis Aprobaciones', href: '/admin/costos/aprobaciones' },
];

type Props = {
    pendientes: CostosAprobacionSolicitud[];
    aprobadas: CostosAprobacionSolicitud[];
    rechazadas: CostosAprobacionSolicitud[];
};

function MontoDisplay({ monto }: { monto: number }) {
    return <>${Number(monto).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</>;
}

function RechazoModal({ aprobacionId, onClose }: { aprobacionId: number; onClose: () => void }) {
    const [observaciones, setObservaciones] = useState('');
    const [processing, setProcessing] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        router.post(`/admin/costos/aprobaciones/${aprobacionId}/rechazar`, { observaciones }, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                onClose();
            },
        });
    };

    return (
        <dialog className="modal modal-open">
            <div className="modal-box">
                <h3 className="text-lg font-bold">Rechazar solicitud</h3>
                <p className="py-2 text-sm text-base-content/60">
                    El rechazo cancelara definitivamente la solicitud.
                </p>
                <form onSubmit={handleSubmit}>
                    <div className="form-control">
                        <label className="label">
                            <span className="label-text">Observaciones (obligatorias)</span>
                        </label>
                        <textarea
                            className="textarea textarea-bordered"
                            rows={3}
                            value={observaciones}
                            onChange={(e) => setObservaciones(e.target.value)}
                            required
                            maxLength={500}
                        />
                    </div>
                    <div className="modal-action">
                        <button type="button" className="btn" onClick={onClose} disabled={processing}>
                            Cancelar
                        </button>
                        <button type="submit" className="btn btn-error" disabled={processing || !observaciones.trim()}>
                            Rechazar
                        </button>
                    </div>
                </form>
            </div>
            <div className="modal-backdrop" onClick={onClose} />
        </dialog>
    );
}

export default function AprobacionesIndex({ pendientes, aprobadas, rechazadas }: Props) {
    const [rechazoId, setRechazoId] = useState<number | null>(null);

    const handleAprobar = (aprobacion: CostosAprobacionSolicitud) => {
        if (confirm('¿Aprobar esta solicitud?')) {
            router.post(`/admin/costos/aprobaciones/${aprobacion.id}/aprobar`, {}, { preserveScroll: true });
        }
    };

    const navigateToShow = (aprobacionId: number) => {
        router.visit(`/admin/costos/aprobaciones/${aprobacionId}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mis Aprobaciones" />

            <div className="p-6">
                <h1 className="mb-6 text-2xl font-semibold">Mis Aprobaciones</h1>

                {/* Tabs */}
                <div role="tablist" className="tabs tabs-bordered mb-6">
                    <input type="radio" name="aprobaciones_tabs" role="tab" className="tab" aria-label={`Pendientes (${pendientes.length})`} defaultChecked />
                    <div role="tabpanel" className="tab-content py-4">
                        {pendientes.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Folio</th>
                                            <th>Departamento</th>
                                            <th>Concepto</th>
                                            <th className="text-right">Monto</th>
                                            <th>Solicitante</th>
                                            <th>Nivel</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {pendientes.map((a) => (
                                            <tr
                                                key={a.id}
                                                className="cursor-pointer hover"
                                                onClick={() => navigateToShow(a.id)}
                                            >
                                                <td className="font-mono">{a.solicitud?.folio ?? '-'}</td>
                                                <td>{a.solicitud?.departamento?.descripcion ?? '-'}</td>
                                                <td>{a.solicitud?.concepto ?? '-'}</td>
                                                <td className="text-right">
                                                    <MontoDisplay monto={a.solicitud?.monto_total ?? 0} />
                                                </td>
                                                <td>{a.solicitud?.solicitante?.name ?? '-'}</td>
                                                <td>{a.nivel}</td>
                                                <td>
                                                    <div className="flex gap-1" onClick={(e) => e.stopPropagation()}>
                                                        <button
                                                            className="btn btn-success btn-xs"
                                                            onClick={() => handleAprobar(a)}
                                                        >
                                                            Aprobar
                                                        </button>
                                                        <button
                                                            className="btn btn-error btn-xs"
                                                            onClick={() => setRechazoId(a.id)}
                                                        >
                                                            Rechazar
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-base-content/60">No tienes aprobaciones pendientes.</p>
                        )}
                    </div>

                    <input type="radio" name="aprobaciones_tabs" role="tab" className="tab" aria-label={`Aprobadas (${aprobadas.length})`} />
                    <div role="tabpanel" className="tab-content py-4">
                        {aprobadas.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Folio</th>
                                            <th>Departamento</th>
                                            <th>Concepto</th>
                                            <th className="text-right">Monto</th>
                                            <th>Solicitante</th>
                                            <th>Nivel</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {aprobadas.map((a) => (
                                            <tr
                                                key={a.id}
                                                className="cursor-pointer hover"
                                                onClick={() => navigateToShow(a.id)}
                                            >
                                                <td className="font-mono">{a.solicitud?.folio ?? '-'}</td>
                                                <td>{a.solicitud?.departamento?.descripcion ?? '-'}</td>
                                                <td>{a.solicitud?.concepto ?? '-'}</td>
                                                <td className="text-right">
                                                    <MontoDisplay monto={a.solicitud?.monto_total ?? 0} />
                                                </td>
                                                <td>{a.solicitud?.solicitante?.name ?? '-'}</td>
                                                <td>{a.nivel}</td>
                                                <td>{a.fecha_respuesta ? new Date(a.fecha_respuesta).toLocaleDateString() : '-'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-base-content/60">No has aprobado solicitudes.</p>
                        )}
                    </div>

                    <input type="radio" name="aprobaciones_tabs" role="tab" className="tab" aria-label={`Rechazadas (${rechazadas.length})`} />
                    <div role="tabpanel" className="tab-content py-4">
                        {rechazadas.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Folio</th>
                                            <th>Departamento</th>
                                            <th>Concepto</th>
                                            <th className="text-right">Monto</th>
                                            <th>Solicitante</th>
                                            <th>Nivel</th>
                                            <th>Fecha</th>
                                            <th>Observaciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rechazadas.map((a) => (
                                            <tr
                                                key={a.id}
                                                className="cursor-pointer hover"
                                                onClick={() => navigateToShow(a.id)}
                                            >
                                                <td className="font-mono">{a.solicitud?.folio ?? '-'}</td>
                                                <td>{a.solicitud?.departamento?.descripcion ?? '-'}</td>
                                                <td>{a.solicitud?.concepto ?? '-'}</td>
                                                <td className="text-right">
                                                    <MontoDisplay monto={a.solicitud?.monto_total ?? 0} />
                                                </td>
                                                <td>{a.solicitud?.solicitante?.name ?? '-'}</td>
                                                <td>{a.nivel}</td>
                                                <td>{a.fecha_respuesta ? new Date(a.fecha_respuesta).toLocaleDateString() : '-'}</td>
                                                <td className="max-w-xs truncate">{a.observaciones ?? '-'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-base-content/60">No has rechazado solicitudes.</p>
                        )}
                    </div>
                </div>
            </div>

            {rechazoId && (
                <RechazoModal aprobacionId={rechazoId} onClose={() => setRechazoId(null)} />
            )}
        </AppLayout>
    );
}
