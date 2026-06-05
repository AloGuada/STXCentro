import { ActivityTimeline } from '@/components/costos/activity-timeline';
import { CancelarModal } from '@/components/costos/cancelar-modal';
import { DocumentoUpload } from '@/components/costos/documento-upload';
import { Button } from '@/components/ui/button';
import { FormattedDate } from '@/components/ui/formatted-date';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosSolicitudPago, CostosSolicitudPagoEstatus } from '@/types/models';
import { SOLICITUD_PAGO_ESTATUS_COLORS, SOLICITUD_PAGO_ESTATUS_LABELS, TIPO_MONEDA_LABELS } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { CheckCircleIcon } from 'lucide-react';
import { useState } from 'react';

type Props = {
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

export default function SolicitudesPagoShow({ solicitud }: Props) {
    const { can } = useCan();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/solicitudes-pago' },
        { title: 'Solicitudes Pago', href: '/admin/costos/solicitudes-pago' },
        { title: solicitud.folio, href: `/admin/costos/solicitudes-pago/${solicitud.id}` },
    ];

    const currentStep = getStepIndex(solicitud.estatus);
    const [showCancelarModal, setShowCancelarModal] = useState(false);

    const handleConfirmarCostos = () => {
        if (confirm('¿Confirmar esta solicitud por costos?')) {
            router.post(`/admin/costos/solicitudes-pago/${solicitud.id}/confirmar-costos`);
        }
    };

    const handleConfirmarContabilidad = () => {
        if (confirm('¿Confirmar esta solicitud por contabilidad?')) {
            router.post(`/admin/costos/solicitudes-pago/${solicitud.id}/confirmar-contabilidad`);
        }
    };

    const showConfirmarCostos = solicitud.estatus === 'aprobada' && !solicitud.confirmada_costos && can('costos.solicitudes.confirmar-costos');
    const showConfirmarContabilidad = solicitud.estatus === 'aprobada' && solicitud.confirmada_costos && !solicitud.confirmada_contabilidad && solicitud.tipo_pago === 'credito' && can('costos.facturas.aceptar-contabilidad');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={solicitud.folio} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">{solicitud.folio}</h1>
                        <span className={`badge mt-1 ${SOLICITUD_PAGO_ESTATUS_COLORS[solicitud.estatus]}`}>
                            {SOLICITUD_PAGO_ESTATUS_LABELS[solicitud.estatus]}
                        </span>
                    </div>
                    <div className="flex gap-2">
                        {solicitud.estatus === 'borrador' && (
                            <Button asChild>
                                <a href={`/admin/costos/solicitudes-pago/${solicitud.id}/pdf`}>Generar Formato PDF</a>
                            </Button>
                        )}
                        {solicitud.estatus === 'pendiente_firma' && (
                            <Button variant="outline" asChild>
                                <a href={`/admin/costos/solicitudes-pago/${solicitud.id}/pdf`}>Descargar Formato</a>
                            </Button>
                        )}
                        {showConfirmarCostos && (
                            <Button className="bg-green-600 hover:bg-green-700" onClick={handleConfirmarCostos}>
                                Confirmar Costos
                            </Button>
                        )}
                        {showConfirmarContabilidad && (
                            <Button className="bg-blue-600 hover:bg-blue-700" onClick={handleConfirmarContabilidad}>
                                Confirmar Contabilidad
                            </Button>
                        )}
                        {solicitud.estatus === 'aprobada' && !solicitud.pago && can('costos.solicitudes.confirmar-costos') && (
                            <Button variant="destructive" onClick={() => setShowCancelarModal(true)}>Cancelar</Button>
                        )}
                        {solicitud.pago && (
                            <Button asChild>
                                <Link href={`/admin/costos/pagos/${solicitud.pago.id}`}>
                                    Ver Pago ({solicitud.pago.folio})
                                </Link>
                            </Button>
                        )}
                        <Button variant="outline" asChild>
                            <Link href="/admin/costos/solicitudes-pago">Volver</Link>
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

                {/* Confirmaciones */}
                {solicitud.estatus === 'aprobada' && (
                    <div className="mb-6 flex gap-4">
                        <div className={`flex-1 rounded-lg border p-3 ${solicitud.confirmada_costos ? 'border-green-300 bg-green-50' : 'border-base-300 bg-base-200'}`}>
                            <div className="flex items-center gap-2">
                                {solicitud.confirmada_costos && <CheckCircleIcon className="size-4 text-green-600" />}
                                <span className="text-sm font-medium">Costos</span>
                            </div>
                            {solicitud.confirmada_costos ? (
                                <div className="mt-1 text-xs text-base-content/60">
                                    {solicitud.confirmador_costos?.name} - <FormattedDate value={solicitud.confirmada_costos_at} />
                                </div>
                            ) : (
                                <div className="mt-1 text-xs text-base-content/50">Pendiente</div>
                            )}
                        </div>
                        {solicitud.tipo_pago === 'credito' && (
                            <div className={`flex-1 rounded-lg border p-3 ${solicitud.confirmada_contabilidad ? 'border-green-300 bg-green-50' : 'border-base-300 bg-base-200'}`}>
                                <div className="flex items-center gap-2">
                                    {solicitud.confirmada_contabilidad && <CheckCircleIcon className="size-4 text-green-600" />}
                                    <span className="text-sm font-medium">Contabilidad</span>
                                </div>
                                {solicitud.confirmada_contabilidad ? (
                                    <div className="mt-1 text-xs text-base-content/60">
                                        {solicitud.confirmador_contabilidad?.name} - <FormattedDate value={solicitud.confirmada_contabilidad_at} />
                                    </div>
                                ) : (
                                    <div className="mt-1 text-xs text-base-content/50">
                                        {solicitud.confirmada_costos ? 'Pendiente' : 'Esperando confirmación de costos'}
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                )}

                {/* Tabs */}
                <div role="tablist" className="tabs tabs-bordered mb-6">
                    <input type="radio" name="solicitud_tabs" role="tab" className="tab" aria-label="Datos" defaultChecked />
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
                                    <p className="font-medium"><FormattedDate value={solicitud.fecha_pago_solicitada} /></p>
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
                                                <th>Centro de Costos</th>
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

                    <input type="radio" name="solicitud_tabs" role="tab" className="tab" aria-label="Documentos" />
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

                    <input type="radio" name="solicitud_tabs" role="tab" className="tab" aria-label="Aprobaciones" />
                    <div role="tabpanel" className="tab-content py-4">
                        {solicitud.aprobaciones && solicitud.aprobaciones.length > 0 ? (() => {
                            const niveles = [...new Set(solicitud.aprobaciones.map((a) => a.nivel))].sort((a, b) => a - b);
                            return (
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
                                            {niveles.map((nivel) => {
                                                const aprobacionesNivel = solicitud.aprobaciones!.filter((a) => a.nivel === nivel);
                                                const aprobada = aprobacionesNivel.find((a) => a.estatus === 'aprobada');
                                                const rechazada = aprobacionesNivel.find((a) => a.estatus === 'rechazada');
                                                const resultado = aprobada ?? rechazada;
                                                const pendiente = !resultado;
                                                const esTurnoActual = pendiente && !niveles.some(
                                                    (n) => n < nivel && !solicitud.aprobaciones!.some((a) => a.nivel === n && a.estatus === 'aprobada'),
                                                );

                                                return (
                                                    <tr key={nivel} className={esTurnoActual ? 'bg-warning/10' : ''}>
                                                        <td>{nivel}</td>
                                                        <td>{resultado ? resultado.aprobador?.name ?? 'Sin asignar' : 'Pendiente'}</td>
                                                        <td>
                                                            <span className={`badge ${resultado?.estatus === 'aprobada' ? 'badge-success' : resultado?.estatus === 'rechazada' ? 'badge-error' : 'badge-warning'}`}>
                                                                {resultado?.estatus ?? 'pendiente'}
                                                            </span>
                                                            {esTurnoActual && <span className="ml-2 text-xs text-warning">Turno actual</span>}
                                                        </td>
                                                        <td><FormattedDate value={resultado?.fecha_respuesta} /></td>
                                                        <td>{resultado?.observaciones ?? '-'}</td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            );
                        })() : (
                            <p className="text-base-content/60">No hay aprobaciones registradas.</p>
                        )}
                    </div>
                </div>

                <div className="mt-8">
                    <h2 className="text-lg font-medium mb-3">Historial</h2>
                    <ActivityTimeline activities={solicitud.activities ?? []} />
                </div>

                <CancelarModal
                    open={showCancelarModal}
                    onClose={() => setShowCancelarModal(false)}
                    url={`/admin/costos/solicitudes-pago/${solicitud.id}/cancelar`}
                    title={`Cancelar solicitud ${solicitud.folio}`}
                    description="La solicitud quedará cancelada y se revertirá su impacto presupuestal si estaba aprobada."
                    submitLabel="Cancelar solicitud"
                />
            </div>
        </AppLayout>
    );
}
