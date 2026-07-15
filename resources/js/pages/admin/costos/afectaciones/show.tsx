import { Head, Link, router } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { useRef, useState } from 'react';
import { CancelarModal } from '@/components/costos/cancelar-modal';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    CostosAfectacionEstatus,
    CostosAfectacionPresupuestal,
} from '@/types/models';
import {
    AFECTACION_ESTATUS_COLORS,
    AFECTACION_ESTATUS_LABELS,
} from '@/types/models';

type Props = {
    afectacion: CostosAfectacionPresupuestal;
};

const fmtMoney = (n: number) =>
    n.toLocaleString('es-MX', { minimumFractionDigits: 2 });

const steps: { key: CostosAfectacionEstatus; label: string }[] = [
    { key: 'borrador', label: 'Borrador' },
    { key: 'pendiente_firma', label: 'Pendiente Firma' },
    { key: 'aprobada', label: 'Aprobada' },
];

function getStepIndex(estatus: CostosAfectacionEstatus): number {
    if (estatus === 'cancelada') {
        return -1;
    }
    return steps.findIndex((s) => s.key === estatus);
}

export default function AfectacionesShow({ afectacion }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/afectaciones' },
        { title: 'Afectaciones', href: '/admin/costos/afectaciones' },
        {
            title: afectacion.folio,
            href: `/admin/costos/afectaciones/${afectacion.id}`,
        },
    ];

    const currentStep = getStepIndex(afectacion.estatus);
    const firmadoInputRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [showCancelarModal, setShowCancelarModal] = useState(false);
    const [showAfectarModal, setShowAfectarModal] = useState(false);
    const [afectando, setAfectando] = useState(false);

    const handleAfectar = () => {
        setAfectando(true);
        router.post(
            `/admin/costos/afectaciones/${afectacion.id}/afectar`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => setShowAfectarModal(false),
                onFinish: () => setAfectando(false),
            },
        );
    };

    const handleUploadFirmado = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) {
            return;
        }
        setUploading(true);
        router.post(
            `/admin/costos/afectaciones/${afectacion.id}/upload-firmado`,
            { archivo: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => setUploading(false),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={afectacion.folio} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            {afectacion.folio}
                        </h1>
                        <span
                            className={`mt-1 badge ${AFECTACION_ESTATUS_COLORS[afectacion.estatus]}`}
                        >
                            {AFECTACION_ESTATUS_LABELS[afectacion.estatus]}
                        </span>
                    </div>
                    <div className="flex gap-2">
                        {afectacion.estatus === 'borrador' && (
                            <>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={`/admin/costos/afectaciones/${afectacion.id}/edit`}
                                    >
                                        Editar
                                    </Link>
                                </Button>
                                <Button
                                    onClick={() => setShowAfectarModal(true)}
                                >
                                    Afectar centros de costos
                                </Button>
                            </>
                        )}
                        {afectacion.estatus === 'aprobada' && (
                            <Button variant="outline" asChild>
                                <a
                                    href={`/admin/costos/afectaciones/${afectacion.id}/pdf`}
                                >
                                    Descargar Formato PDF
                                </a>
                            </Button>
                        )}
                        {afectacion.estatus === 'pendiente_firma' && (
                            <>
                                <Button variant="outline" asChild>
                                    <a
                                        href={`/admin/costos/afectaciones/${afectacion.id}/pdf`}
                                    >
                                        Descargar Formato
                                    </a>
                                </Button>
                                <div>
                                    <input
                                        ref={firmadoInputRef}
                                        type="file"
                                        accept=".pdf"
                                        onChange={handleUploadFirmado}
                                        className="hidden"
                                    />
                                    <Button
                                        onClick={() =>
                                            firmadoInputRef.current?.click()
                                        }
                                        disabled={uploading}
                                    >
                                        {uploading && (
                                            <Loader2Icon className="size-4 animate-spin" />
                                        )}
                                        Subir Formato Firmado
                                    </Button>
                                </div>
                            </>
                        )}
                        {(afectacion.estatus === 'pendiente_firma' ||
                            afectacion.estatus === 'aprobada') && (
                            <Button
                                variant="destructive"
                                onClick={() => setShowCancelarModal(true)}
                            >
                                Cancelar
                            </Button>
                        )}
                        <Button variant="outline" asChild>
                            <Link href="/admin/costos/afectaciones">
                                Volver
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Stepper */}
                {afectacion.estatus !== 'cancelada' && (
                    <ul className="steps steps-horizontal mb-8 w-full">
                        {steps.map((step, i) => (
                            <li
                                key={step.key}
                                className={`step ${i <= currentStep ? 'step-primary' : ''}`}
                            >
                                {step.label}
                            </li>
                        ))}
                    </ul>
                )}

                {/* Tabs */}
                <div role="tablist" className="tabs-bordered mb-6 tabs">
                    <input
                        type="radio"
                        name="afectacion_tabs"
                        role="tab"
                        className="tab"
                        aria-label="Datos"
                        defaultChecked
                    />
                    <div role="tabpanel" className="tab-content py-4">
                        <div className="grid grid-cols-2 gap-6">
                            <div className="space-y-3">
                                <div>
                                    <span className="text-sm text-base-content/60">
                                        Fecha
                                    </span>
                                    <p className="font-medium">
                                        {new Date(
                                            afectacion.fecha,
                                        ).toLocaleDateString()}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-sm text-base-content/60">
                                        Tipo de Origen
                                    </span>
                                    <p className="font-medium capitalize">
                                        {afectacion.tipo_origen.replace(
                                            /_/g,
                                            ' ',
                                        )}
                                    </p>
                                </div>
                            </div>
                            <div className="space-y-3">
                                <div>
                                    <span className="text-sm text-base-content/60">
                                        Creado por
                                    </span>
                                    <p className="font-medium">
                                        {typeof afectacion.creado_por ===
                                        'object'
                                            ? afectacion.creado_por?.name
                                            : '-'}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-sm text-base-content/60">
                                        Monto Total
                                    </span>
                                    <p className="text-xl font-bold">
                                        $
                                        {Number(
                                            afectacion.monto_total,
                                        ).toLocaleString('es-MX', {
                                            minimumFractionDigits: 2,
                                        })}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="mt-4">
                            <span className="text-sm text-base-content/60">
                                Razón / Descripción
                            </span>
                            <p>{afectacion.descripcion}</p>
                        </div>

                        {/* Documentos de sustento */}
                        {afectacion.media && afectacion.media.length > 0 && (
                            <div className="mt-4">
                                <span className="text-sm text-base-content/60">
                                    Documento(s) de sustento
                                </span>
                                <ul className="mt-1 space-y-1">
                                    {afectacion.media.map((m) => (
                                        <li key={m.id}>
                                            <a
                                                href={`/storage/${m.path}`}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="link text-sm link-primary"
                                            >
                                                {m.nombre_original ??
                                                    m.descripcion ??
                                                    'documento'}
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        {/* Detalles: centros de costos a afectar */}
                        {afectacion.detalles &&
                            afectacion.detalles.length > 0 && (
                                <div className="mt-6">
                                    <h3 className="mb-3 font-medium">
                                        Centros de costos a afectar
                                    </h3>
                                    <div className="overflow-x-auto">
                                        <table className="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Centro de Costos</th>
                                                    <th className="text-right">
                                                        Monto a afectar
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {afectacion.detalles.map(
                                                    (d) => (
                                                        <tr key={d.id}>
                                                            <td>
                                                                {d.obra_rubro
                                                                    ?.rubro
                                                                    ?.codigo ??
                                                                    '-'}{' '}
                                                                -{' '}
                                                                {d.obra_rubro
                                                                    ?.rubro
                                                                    ?.descripcion ??
                                                                    ''}
                                                            </td>
                                                            <td className="text-right">
                                                                $
                                                                {Number(
                                                                    d.monto,
                                                                ).toLocaleString(
                                                                    'es-MX',
                                                                    {
                                                                        minimumFractionDigits: 2,
                                                                    },
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td className="text-right font-bold">
                                                        Total
                                                    </td>
                                                    <td className="text-right font-bold">
                                                        $
                                                        {Number(
                                                            afectacion.monto_total,
                                                        ).toLocaleString(
                                                            'es-MX',
                                                            {
                                                                minimumFractionDigits: 2,
                                                            },
                                                        )}
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            )}
                    </div>

                    <input
                        type="radio"
                        name="afectacion_tabs"
                        role="tab"
                        className="tab"
                        aria-label="Historial"
                    />
                    <div role="tabpanel" className="tab-content py-4">
                        {afectacion.historial &&
                        afectacion.historial.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Estatus Anterior</th>
                                            <th>Estatus Nuevo</th>
                                            <th>Usuario</th>
                                            <th>Observaciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {afectacion.historial.map((h) => (
                                            <tr key={h.id}>
                                                <td>
                                                    {new Date(
                                                        h.fecha,
                                                    ).toLocaleString()}
                                                </td>
                                                <td>
                                                    {h.estatus_anterior || '-'}
                                                </td>
                                                <td>{h.estatus_nuevo}</td>
                                                <td>
                                                    {h.usuario?.name ?? '-'}
                                                </td>
                                                <td>
                                                    {h.observaciones ?? '-'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-base-content/60">
                                No hay historial registrado.
                            </p>
                        )}
                    </div>

                    <input
                        type="radio"
                        name="afectacion_tabs"
                        role="tab"
                        className="tab"
                        aria-label="Centros de Costos Afectados"
                    />
                    <div role="tabpanel" className="tab-content py-4">
                        {afectacion.rubros_afectados &&
                        afectacion.rubros_afectados.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Centro de Costos</th>
                                            <th>Descripción</th>
                                            <th>Tipo</th>
                                            <th className="text-right">
                                                Monto
                                            </th>
                                            <th>Sobre Giro</th>
                                            <th>Estatus</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {afectacion.rubros_afectados.map(
                                            (ra) => (
                                                <tr key={ra.id}>
                                                    <td>
                                                        {ra.obra_rubro?.rubro
                                                            ?.codigo ?? '-'}
                                                    </td>
                                                    <td>
                                                        {ra.descripcion ?? '-'}
                                                    </td>
                                                    <td>
                                                        <span
                                                            className={`badge badge-sm ${ra.tipo_movimiento === 'cargo' ? 'badge-error' : 'badge-success'}`}
                                                        >
                                                            {ra.tipo_movimiento}
                                                        </span>
                                                    </td>
                                                    <td className="text-right">
                                                        $
                                                        {Number(
                                                            ra.monto,
                                                        ).toLocaleString(
                                                            'es-MX',
                                                            {
                                                                minimumFractionDigits: 2,
                                                            },
                                                        )}
                                                    </td>
                                                    <td>
                                                        {ra.sobre_giro ? (
                                                            <span className="badge badge-sm badge-error">
                                                                Sí
                                                            </span>
                                                        ) : (
                                                            'No'
                                                        )}
                                                    </td>
                                                    <td>{ra.estatus}</td>
                                                    <td>
                                                        {ra.fecha_aplicacion
                                                            ? new Date(
                                                                  ra.fecha_aplicacion,
                                                              ).toLocaleString()
                                                            : '-'}
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-base-content/60">
                                No hay centros de costos afectados.
                            </p>
                        )}
                    </div>
                </div>

                <CancelarModal
                    open={showCancelarModal}
                    onClose={() => setShowCancelarModal(false)}
                    url={`/admin/costos/afectaciones/${afectacion.id}/cancelar`}
                    title={`Cancelar afectación ${afectacion.folio}`}
                    description="La afectación quedará cancelada y se revertirá su impacto presupuestal si estaba aprobada."
                    submitLabel="Cancelar afectación"
                />

                {showAfectarModal && (
                    <dialog className="modal-open modal">
                        <div className="modal-box max-w-2xl">
                            <h3 className="mb-3 text-lg font-bold">
                                Afectar centros de costos
                            </h3>
                            <p className="mb-3 text-sm text-base-content/70">
                                Vas a afectar {afectacion.detalles?.length ?? 0}{' '}
                                {(afectacion.detalles?.length ?? 0) === 1
                                    ? 'centro de costos'
                                    : 'centros de costos'}{' '}
                                por un total de{' '}
                                <span className="font-semibold">
                                    ${fmtMoney(Number(afectacion.monto_total))}
                                </span>
                                . Esta acción impacta el presupuesto y no se
                                puede repetir. ¿Confirmas que las cantidades son
                                correctas?
                            </p>
                            <div className="max-h-60 overflow-y-auto rounded-lg border border-base-300">
                                <table className="table table-sm">
                                    <tbody>
                                        {(afectacion.detalles ?? []).map(
                                            (d) => (
                                                <tr key={d.id}>
                                                    <td className="text-sm">
                                                        {d.obra_rubro?.rubro
                                                            ?.codigo ??
                                                            '-'}{' '}
                                                        -{' '}
                                                        {d.obra_rubro?.rubro
                                                            ?.descripcion ?? ''}
                                                    </td>
                                                    <td className="text-right font-medium whitespace-nowrap">
                                                        $
                                                        {fmtMoney(
                                                            Number(d.monto),
                                                        )}
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td className="text-right font-bold">
                                                Total
                                            </td>
                                            <td className="text-right font-bold whitespace-nowrap">
                                                $
                                                {fmtMoney(
                                                    Number(
                                                        afectacion.monto_total,
                                                    ),
                                                )}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div className="modal-action">
                                <Button
                                    variant="outline"
                                    onClick={() => setShowAfectarModal(false)}
                                    disabled={afectando}
                                >
                                    Volver
                                </Button>
                                <Button
                                    onClick={handleAfectar}
                                    disabled={afectando}
                                >
                                    {afectando && (
                                        <Loader2Icon className="size-4 animate-spin" />
                                    )}
                                    Sí, afectar
                                </Button>
                            </div>
                        </div>
                        <div
                            className="modal-backdrop"
                            onClick={
                                afectando
                                    ? undefined
                                    : () => setShowAfectarModal(false)
                            }
                        />
                    </dialog>
                )}
            </div>
        </AppLayout>
    );
}
