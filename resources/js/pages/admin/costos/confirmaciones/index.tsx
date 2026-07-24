import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosPuntoControl } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { BadgeCheckIcon, CheckIcon, EyeIcon, FileTextIcon, ReceiptIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/solicitudes-pago' },
    { title: 'Por confirmar', href: '/admin/costos/confirmaciones' },
];

type Props = {
    costos: CostosPuntoControl[];
    contabilidad: CostosPuntoControl[];
};

const fmtDate = (date: string | null) =>
    date ? new Date(date).toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '-';

const fmtMoney = (n: number, moneda: string) =>
    `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2 })} ${moneda.toUpperCase()}`;

function ConfirmarModal({ item, onClose }: { item: CostosPuntoControl; onClose: () => void }) {
    const [processing, setProcessing] = useState(false);
    const esCostos = item.paso === 'costos';
    const titulo = esCostos ? 'Confirmar Costos' : 'Confirmar Contabilidad';

    const handleConfirm = () => {
        setProcessing(true);
        router.post(
            item.accion_url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    onClose();
                    // Remonta las tablas con props frescas (preserveState: false)
                    // para que la fila confirmada desaparezca de la bandeja.
                    router.get(window.location.pathname, {}, { preserveScroll: true, preserveState: false });
                },
            },
        );
    };

    return (
        <dialog className="modal modal-open">
            <div className="modal-box">
                <h2 className="text-xl font-bold">{titulo}</h2>
                <p className="mt-2 text-sm text-base-content/70">
                    {item.tipo === 'solicitud_pago' ? 'Solicitud de pago' : 'Factura'} <strong>{item.folio}</strong>
                    {item.proveedor ? ` — ${item.proveedor}` : ''}.
                </p>
                <p className="mt-1 text-sm text-base-content/60">
                    {esCostos
                        ? 'Confirmas la validación de costos de este documento.'
                        : 'Aceptas contablemente este documento y se programa el pago.'}
                </p>
                <div className="modal-action">
                    <button type="button" className="btn" onClick={onClose} disabled={processing}>
                        Cancelar
                    </button>
                    <button
                        type="button"
                        className="btn bg-green-600 text-white hover:bg-green-700"
                        onClick={handleConfirm}
                        disabled={processing}
                    >
                        {titulo}
                    </button>
                </div>
            </div>
            <div className="modal-backdrop" onClick={onClose} />
        </dialog>
    );
}

function PuntoControlTable({ items }: { items: CostosPuntoControl[] }) {
    const [confirmar, setConfirmar] = useState<CostosPuntoControl | null>(null);

    if (items.length === 0) {
        return <p className="py-12 text-center text-base-content/60">No hay documentos por confirmar.</p>;
    }

    return (
        <>
            <div className="overflow-x-auto">
                <table className="table">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Folio</th>
                            <th>Proveedor</th>
                            <th>Concepto</th>
                            <th className="text-right">Monto</th>
                            <th>Fecha</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((item) => (
                            <tr key={`${item.tipo}-${item.id}`}>
                                <td>
                                    <span
                                        className={`badge badge-sm gap-1 ${item.tipo === 'solicitud_pago' ? 'badge-info' : 'badge-secondary'}`}
                                    >
                                        {item.tipo === 'solicitud_pago' ? (
                                            <>
                                                <FileTextIcon className="h-3 w-3" /> Pago
                                            </>
                                        ) : (
                                            <>
                                                <ReceiptIcon className="h-3 w-3" /> Factura
                                            </>
                                        )}
                                    </span>
                                </td>
                                <td className="font-medium">{item.folio}</td>
                                <td>{item.proveedor ?? '-'}</td>
                                <td className="max-w-xs truncate">{item.concepto ?? '-'}</td>
                                <td className="whitespace-nowrap text-right">{fmtMoney(item.monto, item.moneda)}</td>
                                <td className="whitespace-nowrap">{fmtDate(item.fecha)}</td>
                                <td>
                                    <div className="flex justify-end gap-2">
                                        <Link href={item.detalle_href} className="btn btn-ghost btn-sm" title="Ver detalle">
                                            <EyeIcon className="h-4 w-4" />
                                        </Link>
                                        <button
                                            type="button"
                                            className="btn btn-sm gap-1 bg-green-600 text-white hover:bg-green-700"
                                            onClick={() => setConfirmar(item)}
                                        >
                                            <CheckIcon className="h-4 w-4" /> Confirmar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {confirmar && <ConfirmarModal item={confirmar} onClose={() => setConfirmar(null)} />}
        </>
    );
}

export default function ConfirmacionesIndex({ costos, contabilidad }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Por confirmar" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="flex items-center gap-2 text-2xl font-semibold">
                        <BadgeCheckIcon className="h-6 w-6" /> Por confirmar
                    </h1>
                    <p className="mt-1 text-sm text-base-content/60">
                        Puntos de control de Costos y Contabilidad de documentos que ya pasaron la cadena de aprobación.
                    </p>
                </div>

                <div role="tablist" className="tabs tabs-bordered mb-6">
                    <input
                        type="radio"
                        name="confirmaciones_tabs"
                        role="tab"
                        className="tab"
                        aria-label={`Costos (${costos.length})`}
                        defaultChecked
                    />
                    <div role="tabpanel" className="tab-content py-4">
                        <PuntoControlTable items={costos} />
                    </div>

                    <input
                        type="radio"
                        name="confirmaciones_tabs"
                        role="tab"
                        className="tab"
                        aria-label={`Contabilidad (${contabilidad.length})`}
                    />
                    <div role="tabpanel" className="tab-content py-4">
                        <PuntoControlTable items={contabilidad} />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
