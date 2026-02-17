import { DocumentoUpload } from '@/components/costos/documento-upload';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosSolicitudPago, CostosSolicitudPagoEstatus } from '@/types/models';
import { SOLICITUD_PAGO_ESTATUS_COLORS, SOLICITUD_PAGO_ESTATUS_LABELS, TIPO_MONEDA_LABELS } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { Loader2Icon } from 'lucide-react';

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
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/solicitudes-pago' },
        { title: 'Solicitudes Pago', href: '/admin/costos/solicitudes-pago' },
        { title: solicitud.folio, href: `/admin/costos/solicitudes-pago/${solicitud.id}` },
    ];

    const currentStep = getStepIndex(solicitud.estatus);
    const firmadoInputRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);

    const handleUploadFirmado = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) {
            return;
        }
        setUploading(true);
        router.post(`/admin/costos/solicitudes-pago/${solicitud.id}/upload-firmado`, { archivo: file }, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => setUploading(false),
        });
    };

    const handleCancelar = () => {
        if (confirm('¿Estás seguro de cancelar esta solicitud?')) {
            router.post(`/admin/costos/solicitudes-pago/${solicitud.id}/cancelar`);
        }
    };

    const handleMarcarPagada = () => {
        if (confirm('¿Marcar esta solicitud como pagada?')) {
            router.post(`/admin/costos/solicitudes-pago/${solicitud.id}/marcar-pagada`);
        }
    };

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
                            <>
                                <Button variant="outline" asChild>
                                    <a href={`/admin/costos/solicitudes-pago/${solicitud.id}/pdf`}>Descargar Formato</a>
                                </Button>
                                <div>
                                    <input
                                        ref={firmadoInputRef}
                                        type="file"
                                        accept=".pdf"
                                        onChange={handleUploadFirmado}
                                        className="hidden"
                                    />
                                    <Button onClick={() => firmadoInputRef.current?.click()} disabled={uploading}>
                                        {uploading && <Loader2Icon className="size-4 animate-spin" />}
                                        Subir Formato Firmado
                                    </Button>
                                </div>
                            </>
                        )}
                        {solicitud.estatus === 'aprobada' && (
                            <>
                                <Button onClick={handleMarcarPagada}>Marcar como Pagada</Button>
                                <Button variant="destructive" onClick={handleCancelar}>Cancelar</Button>
                            </>
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
                                        readOnly={solicitud.estatus === 'cancelada' || solicitud.estatus === 'pagada'}
                                    />
                                ))}
                            </div>
                        ) : (
                            <p className="text-base-content/60">Este tipo de solicitud no requiere documentos.</p>
                        )}
                    </div>

                    <input type="radio" name="solicitud_tabs" role="tab" className="tab" aria-label="Aprobaciones" />
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
        </AppLayout>
    );
}
