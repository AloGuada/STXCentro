import { DocumentoUpload } from '@/components/costos/documento-upload';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosAprobacionSolicitud, CostosSolicitudPago, CostosSolicitudPagoEstatus } from '@/types/models';
import { SOLICITUD_PAGO_ESTATUS_COLORS, SOLICITUD_PAGO_ESTATUS_LABELS, TIPO_MONEDA_LABELS } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

type Props = {
    aprobacion: CostosAprobacionSolicitud;
    solicitud: CostosSolicitudPago;
};

const steps: { key: CostosSolicitudPagoEstatus; label: string }[] = [
    { key: 'borrador', label: 'Borrador' },
    { key: 'pendiente_firma', label: 'Pendiente Firma' },
    { key: 'aprobada', label: 'Aprobada' },
    { key: 'pagada', label: 'Pagada' },
];

function getStepIndex(estatus: CostosSolicitudPagoEstatus): number {
    if (estatus === 'cancelada') {
        return -1;
    }
    return steps.findIndex((s) => s.key === estatus);
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

export default function AprobacionesShow({ aprobacion, solicitud }: Props) {
    const [showRechazo, setShowRechazo] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/solicitudes-pago' },
        { title: 'Mis Aprobaciones', href: '/admin/costos/aprobaciones' },
        { title: solicitud.folio, href: `/admin/costos/aprobaciones/${aprobacion.id}` },
    ];

    const currentStep = getStepIndex(solicitud.estatus);
    const isPending = aprobacion.estatus === 'pendiente';

    const handleAprobar = () => {
        if (confirm('¿Aprobar esta solicitud?')) {
            router.post(`/admin/costos/aprobaciones/${aprobacion.id}/aprobar`, {}, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Aprobación - ${solicitud.folio}`} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">{solicitud.folio}</h1>
                        <span className={`badge mt-1 ${SOLICITUD_PAGO_ESTATUS_COLORS[solicitud.estatus]}`}>
                            {SOLICITUD_PAGO_ESTATUS_LABELS[solicitud.estatus]}
                        </span>
                    </div>
                    <div className="flex gap-2">
                        {isPending && (
                            <>
                                <Button className="bg-green-600 hover:bg-green-700" onClick={handleAprobar}>
                                    Aprobar
                                </Button>
                                <Button variant="destructive" onClick={() => setShowRechazo(true)}>
                                    Rechazar
                                </Button>
                            </>
                        )}
                        <Button variant="outline" asChild>
                            <Link href="/admin/costos/aprobaciones">Volver</Link>
                        </Button>
                    </div>
                </div>

                {/* Stepper */}
                {solicitud.estatus !== 'cancelada' && (
                    <ul className="steps steps-horizontal w-full mb-8">
                        {steps.map((step, i) => (
                            <li key={step.key} className={`step ${i <= currentStep ? 'step-primary' : ''}`}>
                                {step.label}
                            </li>
                        ))}
                    </ul>
                )}

                {/* Tabs */}
                <div role="tablist" className="tabs tabs-bordered mb-6">
                    <input type="radio" name="aprobacion_show_tabs" role="tab" className="tab" aria-label="Datos" defaultChecked />
                    <div role="tabpanel" className="tab-content py-4">
                        <div className="grid grid-cols-2 gap-6">
                            <div className="space-y-3">
                                <div>
                                    <span className="text-sm text-base-content/60">Departamento</span>
                                    <p className="font-medium">{solicitud.departamento?.descripcion ?? '-'}</p>
                                </div>
                                <div>
                                    <span className="text-sm text-base-content/60">Proveedor</span>
                                    <p className="font-medium">{solicitud.proveedor?.razon_social ?? 'Sin proveedor'}</p>
                                </div>
                                <div>
                                    <span className="text-sm text-base-content/60">Tipo de Solicitud</span>
                                    <p className="font-medium">{solicitud.tipo_solicitud?.titulo ?? '-'}</p>
                                </div>
                                <div>
                                    <span className="text-sm text-base-content/60">Solicitante</span>
                                    <p className="font-medium">{solicitud.solicitante?.name ?? '-'}</p>
                                </div>
                            </div>
                            <div className="space-y-3">
                                <div>
                                    <span className="text-sm text-base-content/60">Tipo de Pago</span>
                                    <p className="font-medium capitalize">{solicitud.tipo_pago}</p>
                                </div>
                                <div>
                                    <span className="text-sm text-base-content/60">Moneda</span>
                                    <p className="font-medium">{TIPO_MONEDA_LABELS[solicitud.tipo_moneda] ?? solicitud.tipo_moneda}</p>
                                </div>
                                <div>
                                    <span className="text-sm text-base-content/60">Fecha Pago Solicitada</span>
                                    <p className="font-medium">{solicitud.fecha_pago_solicitada ? new Date(solicitud.fecha_pago_solicitada).toLocaleDateString() : '-'}</p>
                                </div>
                                <div>
                                    <span className="text-sm text-base-content/60">Monto Total</span>
                                    <p className="text-xl font-bold">${Number(solicitud.monto_total).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</p>
                                </div>
                            </div>
                        </div>

                        <div className="mt-4 space-y-3">
                            <div>
                                <span className="text-sm text-base-content/60">Concepto</span>
                                <p>{solicitud.concepto}</p>
                            </div>
                        </div>

                        {/* Detalles table */}
                        {solicitud.detalles && solicitud.detalles.length > 0 && (
                            <div className="mt-6">
                                <h3 className="mb-3 font-medium">Detalles</h3>
                                <div className="overflow-x-auto">
                                    <table className="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Rubro</th>
                                                <th>Concepto</th>
                                                <th className="text-right">Cantidad</th>
                                                <th className="text-right">P. Unitario</th>
                                                <th className="text-right">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {solicitud.detalles.map((d) => (
                                                <tr key={d.id}>
                                                    <td>{d.obra_rubro?.rubro?.codigo ?? '-'} - {d.obra_rubro?.rubro?.descripcion ?? ''}</td>
                                                    <td>{d.concepto}</td>
                                                    <td className="text-right">{Number(d.cantidad).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</td>
                                                    <td className="text-right">${Number(d.precio_unitario).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</td>
                                                    <td className="text-right">${Number(d.subtotal).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colSpan={4} className="text-right font-bold">Total</td>
                                                <td className="text-right font-bold">${Number(solicitud.monto_total).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        )}
                    </div>

                    <input type="radio" name="aprobacion_show_tabs" role="tab" className="tab" aria-label="Documentos" />
                    <div role="tabpanel" className="tab-content py-4">
                        {solicitud.tipo_solicitud?.documentos && solicitud.tipo_solicitud.documentos.length > 0 ? (
                            <div className="space-y-4">
                                {solicitud.tipo_solicitud.documentos.map((doc) => (
                                    <DocumentoUpload
                                        key={doc.id}
                                        documento={doc}
                                        archivos={solicitud.archivos ?? []}
                                        storeUrl={`/admin/costos/solicitudes-pago/${solicitud.id}/archivos`}
                                        destroyUrlPrefix={`/admin/costos/solicitudes-pago/${solicitud.id}/archivos`}
                                        readOnly
                                    />
                                ))}
                            </div>
                        ) : (
                            <p className="text-base-content/60">Este tipo de solicitud no requiere documentos.</p>
                        )}
                    </div>

                    <input type="radio" name="aprobacion_show_tabs" role="tab" className="tab" aria-label="Aprobaciones" />
                    <div role="tabpanel" className="tab-content py-4">
                        {solicitud.aprobaciones && solicitud.aprobaciones.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Nivel</th>
                                            <th>Aprobador</th>
                                            <th>Estatus</th>
                                            <th>Fecha</th>
                                            <th>Observaciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {solicitud.aprobaciones.map((a) => {
                                            const esTurnoActual = a.estatus === 'pendiente' && !solicitud.aprobaciones?.some(
                                                (otra) => otra.estatus === 'pendiente' && otra.nivel < a.nivel
                                            );
                                            return (
                                                <tr key={a.id} className={esTurnoActual ? 'bg-warning/10' : ''}>
                                                    <td>{a.nivel}</td>
                                                    <td>{a.aprobador?.name ?? 'Sin asignar'}</td>
                                                    <td>
                                                        <span className={`badge ${a.estatus === 'aprobada' ? 'badge-success' : a.estatus === 'rechazada' ? 'badge-error' : a.estatus === 'cancelada' ? 'badge-ghost' : 'badge-warning'}`}>
                                                            {a.estatus}
                                                        </span>
                                                        {esTurnoActual && <span className="ml-2 text-xs text-warning">Turno actual</span>}
                                                    </td>
                                                    <td>{a.fecha_respuesta ? new Date(a.fecha_respuesta).toLocaleDateString() : '-'}</td>
                                                    <td>{a.observaciones ?? '-'}</td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-base-content/60">No hay aprobaciones registradas.</p>
                        )}
                    </div>
                </div>
            </div>

            {showRechazo && (
                <RechazoModal aprobacionId={aprobacion.id} onClose={() => setShowRechazo(false)} />
            )}
        </AppLayout>
    );
}
