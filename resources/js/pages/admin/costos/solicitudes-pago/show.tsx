import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircleIcon, FileTextIcon } from 'lucide-react';
import { useState } from 'react';
import { ActivityTimeline } from '@/components/costos/activity-timeline';
import { CancelarModal } from '@/components/costos/cancelar-modal';
import { DocumentoUpload } from '@/components/costos/documento-upload';
import { formatMoney as fmtMonto } from '@/components/costos/monto';
import { ReasignarModal } from '@/components/costos/reasignar-modal';
import { Button } from '@/components/ui/button';
import { FormattedDate } from '@/components/ui/formatted-date';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, SharedData } from '@/types';
import type { CostosObraRubro, CostosSolicitudPago, CostosSolicitudPagoEstatus, PresupuestoOption } from '@/types/models';
import { SOLICITUD_PAGO_ESTATUS_COLORS, SOLICITUD_PAGO_ESTATUS_LABELS, TIPO_MONEDA_LABELS } from '@/types/models';

type DocumentoPrevio = { label: string; url: string };

/**
 * Sobregiro del centro de costo del renglón: cuánto excede el gasto acumulado
 * al presupuesto y su porcentaje. Devuelve null si está dentro de presupuesto.
 */
function calcularSobregiro(obraRubro?: CostosObraRubro): { monto: number; pct: number | null } | null {
    const presupuestado = Number(obraRubro?.presupuestado ?? 0);
    const acumulado = Number(obraRubro?.acumulado ?? 0);
    const sobregiro = acumulado - presupuestado;

    if (sobregiro <= 0) {
        return null;
    }

    return {
        monto: sobregiro,
        pct: presupuestado > 0 ? (sobregiro / presupuestado) * 100 : null,
    };
}

type Props = {
    solicitud: CostosSolicitudPago;
    documentosPrevios?: DocumentoPrevio[];
    presupuestos?: PresupuestoOption[];
    obraRubros?: CostosObraRubro[];
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

export default function SolicitudesPagoShow({ solicitud, documentosPrevios = [], presupuestos = [], obraRubros = [] }: Props) {
    const { can } = useCan();
    // El operador del módulo ve el detalle completo; el solicitante que llega
    // por propiedad solo ve Datos y Documentos.
    const esOperador = can('costos.solicitudes-pago.ver-todas');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/solicitudes-pago' },
        { title: 'Solicitudes Pago', href: '/admin/costos/solicitudes-pago' },
        { title: solicitud.folio, href: `/admin/costos/solicitudes-pago/${solicitud.id}` },
    ];

    const currentStep = getStepIndex(solicitud.estatus);
    const [showCancelarModal, setShowCancelarModal] = useState(false);
    const [showReasignarModal, setShowReasignarModal] = useState(false);
    const [cargandoCatalogo, setCargandoCatalogo] = useState(false);

    const puedeReasignar =
        Boolean(solicitud.puede_reasignar) &&
        can('costos.centros-costos.reasignar') &&
        (solicitud.detalles?.length ?? 0) > 0;

    // Lo cargado al presupuesto es la suma del desglose, no el monto de la
    // solicitud: son dos números distintos y el desglose puede sumar menos (se
    // comprueba por partes).
    const cargadoCostos = (solicitud.detalles ?? []).reduce((acumulado, d) => acumulado + Number(d.subtotal ?? 0), 0);

    // El catálogo de centros de costos no viaja con el show (pesa demasiado):
    // al abrir el modal se piden los presupuestos y los centros de los que la
    // solicitud ya usa, una sola vez por visita.
    const abrirReasignar = () => {
        setShowReasignarModal(true);

        if (presupuestos.length > 0) {
            return;
        }

        setCargandoCatalogo(true);
        router.reload({ only: ['presupuestos', 'obraRubros'], onFinish: () => setCargandoCatalogo(false) });
    };

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

    // Operador del módulo cancela cualquiera; el solicitante cancela las suyas
    // con su permiso. Solo en estados cancelables y sin pago asociado.
    const { auth } = usePage<SharedData>().props;
    const esPropia = String(auth.user?.id) === solicitud.solicitante_id;
    const puedeCancelar =
        !solicitud.pago &&
        (solicitud.estatus === 'borrador' || solicitud.estatus === 'pendiente_firma' || solicitud.estatus === 'aprobada') &&
        (can('costos.solicitudes-pago.editar') || (esPropia && can('costos.solicitudes-pago.cancelar-propia')));

    const handleEnviarAprobacion = () => {
        if (confirm('¿Enviar esta solicitud a aprobación? Ya no podrás editarla y se apartará el presupuesto.')) {
            router.post(`/admin/costos/solicitudes-pago/${solicitud.id}/enviar-aprobacion`);
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
                            <>
                                <Button variant="outline" asChild>
                                    <a href={`/admin/costos/solicitudes-pago/${solicitud.id}/pdf`}>Generar Formato PDF</a>
                                </Button>
                                {esPropia && (
                                    <>
                                        <Button variant="outline" asChild>
                                            <Link href={`/admin/costos/solicitudes-pago/${solicitud.id}/edit`}>Editar</Link>
                                        </Button>
                                        <Button onClick={handleEnviarAprobacion}>Enviar a aprobación</Button>
                                    </>
                                )}
                            </>
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
                        {puedeReasignar && (
                            <Button variant="outline" onClick={abrirReasignar}>Reasignar centros de costos</Button>
                        )}
                        {puedeCancelar && (
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
                        <div className={`flex-1 rounded-lg border p-3 ${solicitud.confirmada_costos ? 'border-success/40 bg-success/10' : 'border-base-300 bg-base-200'}`}>
                            <div className="flex items-center gap-2">
                                {solicitud.confirmada_costos && <CheckCircleIcon className="size-4 text-success" />}
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
                            <div className={`flex-1 rounded-lg border p-3 ${solicitud.confirmada_contabilidad ? 'border-success/40 bg-success/10' : 'border-base-300 bg-base-200'}`}>
                                <div className="flex items-center gap-2">
                                    {solicitud.confirmada_contabilidad && <CheckCircleIcon className="size-4 text-success" />}
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
                                    <span className="text-sm text-base-content/60">Beneficiario</span>
                                    <p className="font-medium">{solicitud.proveedor?.razon_social ?? 'Sin proveedor'}</p>
                                </div>
                                <div>
                                    <span className="text-sm text-base-content/60">Tipo de Solicitud</span>
                                    <p className="font-medium">{solicitud.tipo_solicitud?.titulo ?? '-'}</p>
                                </div>
                                <div>
                                    <span className="text-sm text-base-content/60">Elaboró</span>
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
                                    <p className="text-xl font-bold">{fmtMonto(solicitud.monto_total, solicitud.tipo_moneda)}</p>
                                </div>
                            </div>
                        </div>

                        <div className="mt-4 space-y-3">
                            <div>
                                <span className="text-sm text-base-content/60">Concepto</span>
                                <p>{solicitud.concepto}</p>
                            </div>
                            {solicitud.comentarios && (
                                <div>
                                    <span className="text-sm text-base-content/60">Comentarios</span>
                                    <p className="whitespace-pre-line">{solicitud.comentarios}</p>
                                </div>
                            )}
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
                                                <th>Presupuesto</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {solicitud.detalles.map((d) => {
                                                const sobregiro = calcularSobregiro(d.obra_rubro);
                                                return (
                                                <tr key={d.id} className={d.sobre_obra_cerrada ? 'bg-warning/10' : ''}>
                                                    <td>
                                                        <div className="font-medium">{d.obra_rubro?.presupuesto?.op_mostrar ?? '-'}</div>
                                                        <div className="text-xs text-base-content/60">{d.obra_rubro?.presupuesto?.descripcion_mostrar ?? ''}</div>
                                                    </td>
                                                    <td>
                                                        {d.obra_rubro?.rubro?.codigo ?? '-'} - {d.obra_rubro?.rubro?.descripcion ?? ''}
                                                        {d.sobre_obra_cerrada && (
                                                            <span className="badge badge-warning badge-xs ml-1" title="Carga sobre obra/adicional cerrado">⚠ cerrada</span>
                                                        )}
                                                    </td>
                                                    <td>{d.concepto}</td>
                                                    <td className="text-right">{Number(d.cantidad).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</td>
                                                    <td className="text-right">{fmtMonto(d.precio_unitario, solicitud.tipo_moneda)}</td>
                                                    <td className="text-right">{fmtMonto(d.subtotal, solicitud.tipo_moneda)}</td>
                                                    <td>
                                                        {sobregiro ? (
                                                            <div className="whitespace-nowrap">
                                                                <span className="badge badge-error badge-sm">Sobregirado</span>
                                                                <div className="mt-1 text-xs text-error">
                                                                    ${sobregiro.monto.toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                                                    {sobregiro.pct !== null && ` (${sobregiro.pct.toLocaleString('es-MX', { maximumFractionDigits: 1 })}%)`}
                                                                </div>
                                                            </div>
                                                        ) : (
                                                            <span className="badge badge-success badge-sm">Dentro</span>
                                                        )}
                                                    </td>
                                                </tr>
                                                );
                                            })}
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colSpan={5} className="text-right font-bold">Cargado a costos</td>
                                                <td className="text-right font-bold">{fmtMonto(cargadoCostos, solicitud.tipo_moneda)}</td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        )}
                    </div>

                    <input type="radio" name="solicitud_tabs" role="tab" className="tab" aria-label="Documentos" />
                    <div role="tabpanel" className="tab-content py-4">
                        {documentosPrevios.length > 0 && (
                            <div className="mb-6">
                                <h3 className="mb-2 text-sm font-semibold text-base-content/70">Documentos previos</h3>
                                <div className="space-y-2">
                                    {documentosPrevios.map((doc) => (
                                        <a
                                            key={doc.url}
                                            href={doc.url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="flex items-center gap-2 rounded-lg border border-base-300 bg-base-200 p-3 text-sm transition hover:bg-base-300"
                                        >
                                            <FileTextIcon className="size-4 text-base-content/60" />
                                            <span className="font-medium">{doc.label}</span>
                                            <span className="ml-auto text-xs text-base-content/50">Ver PDF</span>
                                        </a>
                                    ))}
                                </div>
                            </div>
                        )}
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

                    {esOperador && (
                    <input type="radio" name="solicitud_tabs" role="tab" className="tab" aria-label="Aprobaciones" />
                    )}
                    {esOperador && (
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
                                                        <td>{nivel === 0 ? 'Firma adicional' : nivel}</td>
                                                        <td>{resultado ? (resultado.aprobador?.name ?? 'Sin asignar') : ([...new Set(aprobacionesNivel.map((a) => a.aprobador?.name).filter(Boolean))].join(' / ') || 'Pendiente')}</td>
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
                    )}
                </div>

                {esOperador && (
                    <div className="mt-8">
                        <h2 className="text-lg font-medium mb-3">Historial</h2>
                        <ActivityTimeline activities={solicitud.activities ?? []} />
                    </div>
                )}

                <CancelarModal
                    open={showCancelarModal}
                    onClose={() => setShowCancelarModal(false)}
                    url={`/admin/costos/solicitudes-pago/${solicitud.id}/cancelar`}
                    title={`Cancelar solicitud ${solicitud.folio}`}
                    description="La solicitud quedará cancelada y se revertirá su impacto presupuestal si estaba aprobada."
                    submitLabel="Cancelar solicitud"
                />

                {puedeReasignar && (
                    <ReasignarModal
                        open={showReasignarModal}
                        onClose={() => setShowReasignarModal(false)}
                        url={`/admin/costos/solicitudes-pago/${solicitud.id}/reasignar`}
                        centrosCostosUrl={`/admin/costos/solicitudes-pago/${solicitud.id}/centros-costos`}
                        presupuestos={presupuestos}
                        obraRubros={obraRubros}
                        cargandoCatalogo={cargandoCatalogo}
                        detallesActuales={solicitud.detalles ?? []}
                        totalBloqueado={solicitud.estatus === 'pagada'}
                        montoSolicitud={Number(solicitud.monto_total)}
                    />
                )}
            </div>
        </AppLayout>
    );
}
