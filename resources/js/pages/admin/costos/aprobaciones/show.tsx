import { DocumentoUpload } from '@/components/costos/documento-upload';
import { Button } from '@/components/ui/button';
import { FormattedDate } from '@/components/ui/formatted-date';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosAprobacionSolicitud, CostosSolicitudPago, CostosSolicitudPagoEstatus } from '@/types/models';
import { SOLICITUD_PAGO_ESTATUS_COLORS, SOLICITUD_PAGO_ESTATUS_LABELS, TIPO_MONEDA_LABELS } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangleIcon } from 'lucide-react';
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

function ObservacionesModal({ aprobacionId, tipo, onClose }: { aprobacionId: number; tipo: 'aprobar' | 'rechazar'; onClose: () => void }) {
    const esAprobacion = tipo === 'aprobar';
    const minLen = esAprobacion ? 1 : 10;
    const { data, setData, post, processing, errors, reset } = useForm({ observaciones: '' });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/costos/aprobaciones/${aprobacionId}/${tipo}`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    const tooShort = data.observaciones.trim().length < minLen;

    return (
        <dialog className="modal modal-open">
            <div className="modal-box w-11/12 max-w-4xl">
                <h2 className="text-2xl font-bold">{esAprobacion ? 'Aprobar solicitud' : 'Rechazar solicitud'}</h2>
                <p className="mt-1 text-sm text-base-content/60">
                    {esAprobacion
                        ? 'Agregue sus observaciones para aprobar esta solicitud.'
                        : 'El rechazo cancelará definitivamente la solicitud. Mínimo 10 caracteres.'}
                </p>
                <form onSubmit={handleSubmit} className="mt-6">
                    <div className="form-control">
                        <label className="mb-2 text-sm font-medium">Observaciones</label>
                        <textarea
                            className={`textarea textarea-bordered w-full ${errors.observaciones ? 'textarea-error' : ''}`}
                            rows={6}
                            placeholder={esAprobacion ? 'Escriba sus observaciones...' : 'Motivo del rechazo...'}
                            value={data.observaciones}
                            onChange={(e) => setData('observaciones', e.target.value)}
                            required
                            maxLength={500}
                        />
                        {errors.observaciones && (
                            <span className="mt-1 text-xs text-error">{errors.observaciones}</span>
                        )}
                    </div>
                    <div className="modal-action">
                        <button type="button" className="btn" onClick={onClose} disabled={processing}>
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            className={`btn ${esAprobacion ? 'bg-green-600 hover:bg-green-700 text-white' : 'btn-error'}`}
                            disabled={processing || tooShort}
                        >
                            {esAprobacion ? 'Aprobar' : 'Rechazar'}
                        </button>
                    </div>
                </form>
            </div>
            <div className="modal-backdrop" onClick={onClose} />
        </dialog>
    );
}

export default function AprobacionesShow({ aprobacion, solicitud }: Props) {
    const [modalTipo, setModalTipo] = useState<'aprobar' | 'rechazar' | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/solicitudes-pago' },
        { title: 'Mis Aprobaciones', href: '/admin/costos/aprobaciones' },
        { title: solicitud.folio, href: `/admin/costos/aprobaciones/${aprobacion.id}` },
    ];

    const currentStep = getStepIndex(solicitud.estatus);
    const isPending = aprobacion.estatus === 'pendiente';

    const tieneSobrepresupuesto = solicitud.detalles?.some((d) => {
        if (!d.obra_rubro) return false;
        const disponible = Number(d.obra_rubro.presupuestado) - Number(d.obra_rubro.acumulado);
        return Number(d.subtotal) > disponible;
    }) ?? false;

    const handleAprobar = () => setModalTipo('aprobar');

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
                                <Button variant="destructive" onClick={() => setModalTipo('rechazar')}>
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

                {tieneSobrepresupuesto && (
                    <div className="alert alert-warning mb-6">
                        <AlertTriangleIcon className="size-5" />
                        <span>Esta solicitud contiene centros de costos que exceden el presupuesto disponible. Revise los detalles antes de aprobar.</span>
                    </div>
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
                                                <th>Obra</th>
                                                <th>Centro de Costos</th>
                                                <th>Concepto</th>
                                                <th className="text-right">Cantidad</th>
                                                <th className="text-right">P. Unitario</th>
                                                <th className="text-right">Subtotal</th>
                                                <th className="text-right">Presupuesto</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {solicitud.detalles.map((d) => {
                                                const presupuestado = Number(d.obra_rubro?.presupuestado ?? 0);
                                                const acumulado = Number(d.obra_rubro?.acumulado ?? 0);
                                                const disponible = presupuestado - acumulado;
                                                const subtotal = Number(d.subtotal);
                                                const excede = subtotal > disponible;
                                                const sobregiro = disponible <= 0;
                                                const porcentajeUsado = presupuestado > 0 ? (acumulado / presupuestado) * 100 : 0;
                                                const fmt = (n: number) => n.toLocaleString('es-MX', { minimumFractionDigits: 2 });

                                                return (
                                                    <tr key={d.id} className={sobregiro ? 'bg-error/5' : excede ? 'bg-warning/5' : ''}>
                                                        <td className="text-sm">{d.obra_rubro?.obra?.descripcion ?? '-'}</td>
                                                        <td>{d.obra_rubro?.rubro?.codigo ?? '-'} - {d.obra_rubro?.rubro?.descripcion ?? ''}</td>
                                                        <td>{d.concepto}</td>
                                                        <td className="text-right">{fmt(Number(d.cantidad))}</td>
                                                        <td className="text-right">${fmt(Number(d.precio_unitario))}</td>
                                                        <td className="text-right">${fmt(subtotal)}</td>
                                                        <td className="text-right min-w-48">
                                                            {d.obra_rubro ? (
                                                                <div className="space-y-1">
                                                                    <div className="flex items-center justify-end gap-1">
                                                                        {(sobregiro || excede) && <AlertTriangleIcon className={`size-3.5 ${sobregiro ? 'text-error' : 'text-warning'}`} />}
                                                                        <span className={`text-xs font-semibold ${sobregiro ? 'text-error' : excede ? 'text-warning' : 'text-success'}`}>
                                                                            {sobregiro ? `SOBREGIRO: -$${fmt(Math.abs(disponible))}` : `Disp: $${fmt(disponible)}`}
                                                                        </span>
                                                                    </div>
                                                                    <div className="w-full bg-base-300 rounded-full h-1.5">
                                                                        <div
                                                                            className={`h-1.5 rounded-full ${sobregiro ? 'bg-error' : porcentajeUsado > 80 ? 'bg-warning' : 'bg-success'}`}
                                                                            style={{ width: `${Math.min(porcentajeUsado, 100)}%` }}
                                                                        />
                                                                    </div>
                                                                    <span className="text-[10px] text-base-content/50 block text-right">
                                                                        ${fmt(acumulado)} / ${fmt(presupuestado)}
                                                                    </span>
                                                                </div>
                                                            ) : '-'}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colSpan={5} className="text-right font-bold">Total</td>
                                                <td className="text-right font-bold">${Number(solicitud.monto_total).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</td>
                                                <td />
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
            </div>

            {modalTipo && (
                <ObservacionesModal aprobacionId={aprobacion.id} tipo={modalTipo} onClose={() => setModalTipo(null)} />
            )}
        </AppLayout>
    );
}
