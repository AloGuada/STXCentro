import { CancelarModal } from '@/components/costos/cancelar-modal';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosPago, CostosPagoEstatus } from '@/types/models';
import {
    PAGO_ESTATUS_COLORS,
    PAGO_ESTATUS_LABELS,
    PAGO_TIPO_PAGO_LABELS,
    TIPO_MONEDA_LABELS,
} from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { Loader2Icon, UploadIcon } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';

type Props = {
    pago: CostosPago;
};

const contadoSteps: { key: CostosPagoEstatus; label: string }[] = [
    { key: 'pendiente', label: 'Pendiente' },
    { key: 'programado', label: 'Programado' },
    { key: 'pagado', label: 'Pagado' },
];

const creditoSteps: { key: CostosPagoEstatus; label: string }[] = [
    { key: 'pendiente', label: 'Pendiente' },
    { key: 'programado', label: 'Programado' },
    { key: 'parcial', label: 'Parcializado' },
    { key: 'pagado', label: 'Pagado' },
];

function getStepIndex(estatus: CostosPagoEstatus, tipoPago: string): number {
    const steps = tipoPago === 'credito' ? creditoSteps : contadoSteps;
    return steps.findIndex((s) => s.key === estatus);
}

function ComprobanteUpload({ url, label }: { url: string; label: string }) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);

    const handleUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setUploading(true);
        router.post(url, { comprobante: file }, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                setUploading(false);
                if (inputRef.current) inputRef.current.value = '';
            },
        });
    };

    return (
        <div>
            <input ref={inputRef} type="file" onChange={handleUpload} className="hidden" />
            <Button onClick={() => inputRef.current?.click()} disabled={uploading} size="sm">
                {uploading ? <Loader2Icon className="size-4 animate-spin" /> : <UploadIcon className="size-4" />}
                {label}
            </Button>
        </div>
    );
}

function ParcialidadesTable({ parciales }: { parciales: CostosPago[] }) {
    return (
        <div className="mt-6">
            <h3 className="mb-3 font-medium">Parcialidades</h3>
            <div className="overflow-x-auto">
                <table className="table table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Folio</th>
                            <th className="text-right">Monto</th>
                            <th>F. Programada</th>
                            <th>F. Pago</th>
                            <th>Estatus</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {parciales.map((p) => (
                            <tr key={p.id}>
                                <td>{p.numero_parcialidad}</td>
                                <td>
                                    <Link href={`/admin/costos/pagos/${p.id}`} className="link link-primary">
                                        {p.folio}
                                    </Link>
                                </td>
                                <td className="text-right">${Number(p.monto_pago).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</td>
                                <td>{p.fecha_pago_programada ? new Date(p.fecha_pago_programada).toLocaleDateString() : '-'}</td>
                                <td>{p.fecha_pago_realizada ? new Date(p.fecha_pago_realizada).toLocaleDateString() : '-'}</td>
                                <td>
                                    <span className={`badge ${PAGO_ESTATUS_COLORS[p.estatus]}`}>
                                        {PAGO_ESTATUS_LABELS[p.estatus]}
                                    </span>
                                </td>
                                <td>
                                    {p.estatus === 'programado' && (
                                        <ComprobanteUpload
                                            url={`/admin/costos/pagos/${p.id}/upload-comprobante`}
                                            label="Comprobante"
                                        />
                                    )}
                                    {p.media?.path && (
                                        <a href={`/storage/${p.media.path}`} target="_blank" rel="noreferrer" className="btn btn-ghost btn-xs">
                                            Ver
                                        </a>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export default function PagosShow({ pago }: Props) {
    const { can } = useCan();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/pagos' },
        { title: 'Pagos', href: '/admin/costos/pagos' },
        { title: pago.folio, href: `/admin/costos/pagos/${pago.id}` },
    ];

    const steps = pago.tipo_pago === 'credito' ? creditoSteps : contadoSteps;
    const currentStep = getStepIndex(pago.estatus, pago.tipo_pago);
    const parciales = pago.pagos_parciales ?? [];
    const esCredito = pago.tipo_pago === 'credito';
    const esPendiente = pago.estatus === 'pendiente';
    const esProgramado = pago.estatus === 'programado';
    const [showProgramarModal, setShowProgramarModal] = useState(false);
    const [programarProcessing, setProgramarProcessing] = useState(false);
    const [showCancelarModal, setShowCancelarModal] = useState(false);
    const puedeCancelar = can('costos.pagos.cancelar') && !['pagado', 'cancelado'].includes(pago.estatus) && !pago.media;

    const proveedor = pago.pagable && 'proveedor' in pago.pagable ? pago.pagable.proveedor : null;
    const diasCredito = proveedor?.dias_credito_default ?? 0;

    const fechaPagoEstimada = useMemo(() => {
        const base = new Date();
        base.setDate(base.getDate() + diasCredito);
        const day = base.getDay();
        if (day === 6) base.setDate(base.getDate() + 6);
        else if (day === 0) base.setDate(base.getDate() + 5);
        else if (day < 5) base.setDate(base.getDate() + (5 - day));
        return base;
    }, [diasCredito]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={pago.folio} />

            <div className="p-6">
                {/* Header */}
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">{pago.folio}</h1>
                        <span className={`badge mt-1 ${PAGO_ESTATUS_COLORS[pago.estatus]}`}>
                            {PAGO_ESTATUS_LABELS[pago.estatus]}
                        </span>
                    </div>
                    <div className="flex gap-2">
                        {pago.pago_padre_id && pago.pago_padre && (
                            <Button variant="outline" asChild>
                                <Link href={`/admin/costos/pagos/${pago.pago_padre_id}`}>
                                    Volver a Pago Padre ({pago.pago_padre.folio})
                                </Link>
                            </Button>
                        )}
                        {pago.pagable && 'folio' in pago.pagable && (
                            <Button variant="outline" asChild>
                                <Link href={`/admin/costos/solicitudes-pago/${pago.pagable_id}`}>
                                    Ver Solicitud ({pago.pagable.folio})
                                </Link>
                            </Button>
                        )}
                        <Button variant="outline" asChild>
                            <Link href="/admin/costos/pagos">Volver</Link>
                        </Button>
                        {puedeCancelar && (
                            <Button variant="destructive" onClick={() => setShowCancelarModal(true)}>
                                Cancelar pago
                            </Button>
                        )}
                    </div>
                </div>

                {/* Stepper */}
                <ul className="steps steps-horizontal w-full mb-8">
                    {steps.map((step, i) => (
                        <li key={step.key} className={`step ${i <= currentStep ? 'step-primary' : ''}`}>
                            {step.label}
                        </li>
                    ))}
                </ul>

                {/* Datos del pago */}
                <div className="grid grid-cols-2 gap-6 mb-8">
                    <div className="space-y-3">
                        <div>
                            <span className="text-sm text-base-content/60">Tipo de Pago</span>
                            <p className="font-medium">{PAGO_TIPO_PAGO_LABELS[pago.tipo_pago]}</p>
                        </div>
                        <div>
                            <span className="text-sm text-base-content/60">Moneda</span>
                            <p className="font-medium">{TIPO_MONEDA_LABELS[pago.moneda as keyof typeof TIPO_MONEDA_LABELS] ?? pago.moneda.toUpperCase()}</p>
                        </div>
                        {pago.tipo_cambio !== 1 && (
                            <div>
                                <span className="text-sm text-base-content/60">Tipo de Cambio</span>
                                <p className="font-medium">{Number(pago.tipo_cambio).toFixed(4)}</p>
                            </div>
                        )}
                    </div>
                    <div className="space-y-3">
                        <div>
                            <span className="text-sm text-base-content/60">Monto</span>
                            <p className="text-xl font-bold">${Number(pago.monto_pago).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</p>
                        </div>
                        <div>
                            <span className="text-sm text-base-content/60">Fecha Programada</span>
                            <p className="font-medium">{pago.fecha_pago_programada ? new Date(pago.fecha_pago_programada).toLocaleDateString() : '-'}</p>
                        </div>
                        {pago.fecha_pago_maxima && (
                            <div>
                                <span className="text-sm text-base-content/60">Fecha Maxima</span>
                                <p className="font-medium">{new Date(pago.fecha_pago_maxima).toLocaleDateString()}</p>
                            </div>
                        )}
                        {pago.fecha_pago_realizada && (
                            <div>
                                <span className="text-sm text-base-content/60">Fecha Realizada</span>
                                <p className="font-medium">{new Date(pago.fecha_pago_realizada).toLocaleDateString()}</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Programar pago (pendiente) */}
                {esPendiente && can('costos.pagos.programar') && (
                    <div className="mb-8">
                        <Button onClick={() => setShowProgramarModal(true)}>
                            Programar Pago
                        </Button>
                    </div>
                )}

                {/* Programado sin parcialidades: parcializar o pagar en una exhibición.
                    Aplica a contado y crédito por igual. */}
                {esProgramado && parciales.length === 0 && (
                    <div className="mb-8 space-y-2">
                        <p className="text-sm text-base-content/60">Seleccione cómo liquidar este pago:</p>
                        <div className="flex gap-3">
                            <Button asChild>
                                <Link href={`/admin/costos/pagos/${pago.id}/parcializar`}>Parcializar</Link>
                            </Button>
                            <ComprobanteUpload
                                url={`/admin/costos/pagos/${pago.id}/upload-comprobante`}
                                label="Pagar en Una Exhibición"
                            />
                        </div>
                    </div>
                )}

                {/* Comprobante existente */}
                {pago.media?.path && (
                    <div className="mb-8">
                        <span className="text-sm text-base-content/60">Comprobante</span>
                        <p>
                            <a href={`/storage/${pago.media.path}`} target="_blank" rel="noreferrer" className="link link-primary">
                                Ver comprobante
                            </a>
                        </p>
                    </div>
                )}

                {/* Tabla de parcialidades */}
                {parciales.length > 0 && (
                    <ParcialidadesTable parciales={parciales} />
                )}

                {/* Notas */}
                {pago.notas && (
                    <div className="mt-6">
                        <span className="text-sm text-base-content/60">Notas</span>
                        <p>{pago.notas}</p>
                    </div>
                )}

                {/* Programar Pago Modal */}
                {showProgramarModal && (
                    <dialog className="modal modal-open">
                        <div className="modal-box">
                            <h3 className="font-bold text-lg mb-4">Confirmar Programación de Pago</h3>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-base-content/60">Folio</span>
                                    <span className="font-medium">{pago.folio}</span>
                                </div>
                                {proveedor && (
                                    <div className="flex justify-between">
                                        <span className="text-base-content/60">Proveedor</span>
                                        <span className="font-medium">{proveedor.razon_social}</span>
                                    </div>
                                )}
                                <div className="flex justify-between">
                                    <span className="text-base-content/60">Monto</span>
                                    <span className="font-medium">${Number(pago.monto_pago).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-base-content/60">Tipo de Pago</span>
                                    <span className="font-medium">
                                        {diasCredito > 0 ? `Crédito (${diasCredito} días)` : 'Contado'}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-base-content/60">Fecha estimada de pago</span>
                                    <span className="font-medium">
                                        Viernes {fechaPagoEstimada.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })}
                                    </span>
                                </div>
                            </div>
                            <p className="mt-4 text-sm text-base-content/60">
                                Se programará el pago para el viernes indicado y se notificará al proveedor por correo.
                            </p>
                            <div className="modal-action">
                                <Button variant="outline" onClick={() => setShowProgramarModal(false)} disabled={programarProcessing}>
                                    Cancelar
                                </Button>
                                <Button
                                    disabled={programarProcessing}
                                    onClick={() => {
                                        setProgramarProcessing(true);
                                        router.post(
                                            `/admin/costos/pagos/${pago.id}/programar`,
                                            {},
                                            {
                                                preserveScroll: true,
                                                onFinish: () => {
                                                    setProgramarProcessing(false);
                                                    setShowProgramarModal(false);
                                                },
                                            },
                                        );
                                    }}
                                >
                                    {programarProcessing && <Loader2Icon className="size-4 animate-spin" />}
                                    Confirmar
                                </Button>
                            </div>
                        </div>
                        <div className="modal-backdrop" onClick={() => setShowProgramarModal(false)}></div>
                    </dialog>
                )}

                <CancelarModal
                    open={showCancelarModal}
                    onClose={() => setShowCancelarModal(false)}
                    url={`/admin/costos/pagos/${pago.id}/cancelar`}
                    title={`Cancelar pago ${pago.folio}`}
                    description="El pago quedará cancelado. No se puede cancelar un pago con comprobante subido."
                    submitLabel="Cancelar pago"
                />
            </div>
        </AppLayout>
    );
}
