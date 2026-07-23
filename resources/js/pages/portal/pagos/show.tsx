import { formatMoney as fmtMonto } from '@/components/costos/monto';
import PortalLayout from '@/layouts/portal/portal-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosPago, CostosPagoEstatus } from '@/types/models';
import { PAGO_ESTATUS_COLORS, PAGO_ESTATUS_LABELS } from '@/types/models';
import { Head } from '@inertiajs/react';

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

export default function PortalPagoShow({ pago }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/portal' },
        { title: 'Pagos', href: '/portal/pagos' },
        { title: pago.folio, href: `/portal/pagos/${pago.id}` },
    ];

    const formatMoney = (n: number) => fmtMonto(n, pago.moneda);
    const parciales = pago.pagos_parciales ?? [];

    return (
        <PortalLayout breadcrumbs={breadcrumbs}>
            <Head title={pago.folio} />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">{pago.folio}</h1>
                    <span className={`badge ${PAGO_ESTATUS_COLORS[pago.estatus]} mt-1`}>{PAGO_ESTATUS_LABELS[pago.estatus]}</span>
                </div>

                {/* Stepper */}
                <ul className="steps steps-horizontal w-full mb-8">
                    {(pago.tipo_pago === 'credito' ? creditoSteps : contadoSteps).map((step, i) => (
                        <li key={step.key} className={`step ${i <= getStepIndex(pago.estatus, pago.tipo_pago) ? 'step-primary' : ''}`}>
                            {step.label}
                        </li>
                    ))}
                </ul>

                <div className="grid grid-cols-2 gap-6 mb-6">
                    <div className="space-y-3">
                        <div>
                            <span className="text-sm text-base-content/60">Monto</span>
                            <p className="font-medium text-lg">{formatMoney(pago.monto_pago)}</p>
                        </div>
                        <div>
                            <span className="text-sm text-base-content/60">Tipo de Pago</span>
                            <p className="font-medium">{pago.tipo_pago === 'contado' ? 'Contado' : 'Credito'}</p>
                        </div>
                        <div>
                            <span className="text-sm text-base-content/60">Moneda</span>
                            <p className="font-medium">{pago.moneda?.toUpperCase()}</p>
                        </div>
                    </div>
                    <div className="space-y-3">
                        <div>
                            <span className="text-sm text-base-content/60">Fecha Pago Programada</span>
                            <p className="font-medium">
                                {pago.fecha_pago_programada ? new Date(pago.fecha_pago_programada).toLocaleDateString() : '-'}
                            </p>
                        </div>
                        <div>
                            <span className="text-sm text-base-content/60">Fecha Pago Realizada</span>
                            <p className="font-medium">
                                {pago.fecha_pago_realizada ? new Date(pago.fecha_pago_realizada).toLocaleDateString() : '-'}
                            </p>
                        </div>
                        {pago.referencia_pago && (
                            <div>
                                <span className="text-sm text-base-content/60">Referencia</span>
                                <p className="font-medium">{pago.referencia_pago}</p>
                            </div>
                        )}
                    </div>
                </div>

                {pago.notas && (
                    <div className="mb-6">
                        <span className="text-sm text-base-content/60">Notas</span>
                        <p>{pago.notas}</p>
                    </div>
                )}

                {/* Parcialidades */}
                {parciales.length > 0 && (
                    <div>
                        <h2 className="text-lg font-medium mb-3">Parcialidades</h2>
                        <div className="overflow-x-auto">
                            <table className="table table-sm">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Folio</th>
                                        <th className="text-right">Monto</th>
                                        <th>Fecha Programada</th>
                                        <th>Fecha Pago</th>
                                        <th>Estatus</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {parciales.map((p) => (
                                        <tr key={p.id}>
                                            <td>{p.numero_parcialidad}</td>
                                            <td>{p.folio}</td>
                                            <td className="text-right">{formatMoney(p.monto_pago)}</td>
                                            <td>
                                                {p.fecha_pago_programada
                                                    ? new Date(p.fecha_pago_programada).toLocaleDateString()
                                                    : '-'}
                                            </td>
                                            <td>
                                                {p.fecha_pago_realizada
                                                    ? new Date(p.fecha_pago_realizada).toLocaleDateString()
                                                    : '-'}
                                            </td>
                                            <td>
                                                <span className={`badge ${PAGO_ESTATUS_COLORS[p.estatus]}`}>
                                                    {PAGO_ESTATUS_LABELS[p.estatus]}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </PortalLayout>
    );
}
