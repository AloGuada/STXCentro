import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosAprobacionSolicitud } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangleIcon, CheckIcon, EyeIcon, FileCheckIcon, FileTextIcon, PaperclipIcon, XIcon } from 'lucide-react';
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

const fmtDate = (date: string | null) =>
    date ? new Date(date).toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '-';

const fmtMoney = (n: number) => `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

function ObservacionesModal({ aprobacionId, tipo, onClose }: { aprobacionId: number; tipo: 'aprobar' | 'rechazar'; onClose: () => void }) {
    const [observaciones, setObservaciones] = useState('');
    const [processing, setProcessing] = useState(false);

    const esAprobacion = tipo === 'aprobar';

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        router.post(`/admin/costos/aprobaciones/${aprobacionId}/${tipo}`, { observaciones }, {
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
                <h3 className="text-lg font-bold">{esAprobacion ? 'Aprobar solicitud' : 'Rechazar solicitud'}</h3>
                <p className="py-2 text-sm text-base-content/60">
                    {esAprobacion
                        ? 'Agregue sus observaciones para aprobar esta solicitud.'
                        : 'El rechazo cancelará definitivamente la solicitud.'}
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
                        <button
                            type="submit"
                            className={`btn ${esAprobacion ? 'bg-green-600 hover:bg-green-700 text-white' : 'btn-error'}`}
                            disabled={processing || !observaciones.trim()}
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

function PdfModal({ url, title, onClose }: { url: string; title: string; onClose: () => void }) {
    return (
        <dialog className="modal modal-open">
            <div className="modal-box max-w-4xl h-[85vh] p-0 flex flex-col">
                <div className="flex items-center justify-between px-4 py-3 border-b border-base-300">
                    <h3 className="font-medium text-sm">{title}</h3>
                    <button className="btn btn-sm btn-ghost" onClick={onClose}>✕</button>
                </div>
                <iframe src={url} className="flex-1 w-full" title={title} />
            </div>
            <div className="modal-backdrop" onClick={onClose} />
        </dialog>
    );
}

function AprobacionTable({ items, tipo }: { items: CostosAprobacionSolicitud[]; tipo: 'pendientes' | 'aprobadas' | 'rechazadas' }) {
    const [modalState, setModalState] = useState<{ id: number; tipo: 'aprobar' | 'rechazar' } | null>(null);
    const [pdfModal, setPdfModal] = useState<{ url: string; title: string } | null>(null);

    if (items.length === 0) {
        const mensajes = {
            pendientes: 'No tienes aprobaciones pendientes.',
            aprobadas: 'No has aprobado solicitudes.',
            rechazadas: 'No has rechazado solicitudes.',
        };
        return <p className="text-base-content/60 py-8 text-center">{mensajes[tipo]}</p>;
    }

    return (
        <>
            <div className="overflow-auto rounded-box border border-base-300" style={{ maxHeight: '70vh' }}>
                <table className="table">
                    <thead className="sticky top-0 z-10 bg-base-100">
                        <tr>
                            <th>Folio</th>
                            <th>Solicitante</th>
                            <th>Proveedor</th>
                            <th>Tipo Solicitud</th>
                            <th className="text-right">Monto</th>
                            <th>Docs</th>
                            {tipo !== 'pendientes' && <th>Fecha</th>}
                            {tipo !== 'pendientes' && <th>Observaciones</th>}
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((a) => {
                            const sol = a.solicitud;
                            const montoSobregiro = tipo === 'pendientes'
                                ? (sol?.detalles ?? []).reduce((sum, d) => {
                                    if (!d.obra_rubro) return sum;
                                    const disponible = Number(d.obra_rubro.presupuestado) - Number(d.obra_rubro.acumulado);
                                    const exceso = Number(d.subtotal) - disponible;
                                    return exceso > 0 ? sum + exceso : sum;
                                }, 0)
                                : 0;
                            const tieneSobregiro = montoSobregiro > 0;
                            return (
                                <tr key={a.id} className={tieneSobregiro ? 'bg-error/10' : 'hover'}>
                                    <td>
                                        <div>
                                            <span className="font-mono text-xs font-medium">{sol?.folio ?? '-'}</span>
                                            <div className="mt-0.5 text-[11px] text-base-content/50">{fmtDate(sol?.created_at ?? null)}</div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <div className="text-sm">{sol?.solicitante?.name ?? '-'}</div>
                                            <div className="mt-0.5 text-[11px] text-base-content/50">{sol?.departamento?.descripcion ?? ''}</div>
                                        </div>
                                    </td>
                                    <td>
                                        {sol?.proveedor ? (
                                            <div>
                                                <div className="text-sm">{sol.proveedor.razon_social}</div>
                                                <div className="mt-0.5 text-[11px] text-base-content/50">RFC: {sol.proveedor.rfc}</div>
                                            </div>
                                        ) : (
                                            <span className="text-base-content/40">-</span>
                                        )}
                                    </td>
                                    <td><span className="text-xs text-base-content/60">{sol?.tipo_solicitud?.titulo ?? '-'}</span></td>
                                    <td className="text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            {tieneSobregiro && <AlertTriangleIcon className="size-4 text-error" title="Sobregiro en rubro" />}
                                            <span className="font-medium">{fmtMoney(sol?.monto_total ?? 0)}</span>
                                        </div>
                                        {tieneSobregiro && (
                                            <div className="mt-0.5 text-[11px] font-semibold text-error">Sobregiro: {fmtMoney(montoSobregiro)}</div>
                                        )}
                                    </td>
                                    <td>
                                        <div className="flex gap-1.5">
                                            {(sol?.archivos?.length ?? 0) > 0 && (
                                                <span className="flex size-7 items-center justify-center rounded-lg border border-base-300 text-base-content/60" title={`${sol!.archivos!.length} archivo(s)`}>
                                                    <PaperclipIcon className="size-3.5" />
                                                </span>
                                            )}
                                            {sol?.estatus !== 'borrador' && (
                                                <button
                                                    onClick={(e) => { e.stopPropagation(); setPdfModal({ url: `/admin/costos/solicitudes-pago/${sol?.id}/pdf`, title: `Formato ${sol?.folio}` }); }}
                                                    className="flex size-7 items-center justify-center rounded-lg border border-base-300 text-base-content/60 transition-colors hover:bg-base-200"
                                                    title="Ver PDF"
                                                >
                                                    <FileTextIcon className="size-3.5" />
                                                </button>
                                            )}
                                            {sol?.media && (
                                                <button
                                                    onClick={(e) => { e.stopPropagation(); setPdfModal({ url: `/storage/${sol.media!.path}`, title: `Firmado ${sol?.folio}` }); }}
                                                    className="flex size-7 items-center justify-center rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-600 transition-colors hover:bg-emerald-100"
                                                    title="Ver PDF firmado"
                                                >
                                                    <FileCheckIcon className="size-3.5" />
                                                </button>
                                            )}
                                            {(sol?.archivos?.length ?? 0) === 0 && sol?.estatus === 'borrador' && !sol?.media && (
                                                <span className="text-xs text-base-content/40">-</span>
                                            )}
                                        </div>
                                    </td>
                                    {tipo !== 'pendientes' && (
                                        <td><span className="text-xs text-base-content/60">{fmtDate(a.fecha_respuesta)}</span></td>
                                    )}
                                    {tipo !== 'pendientes' && (
                                        <td><span className="max-w-xs truncate text-xs text-base-content/60">{a.observaciones ?? '-'}</span></td>
                                    )}
                                    <td>
                                        <div className="flex gap-1.5">
                                            {tipo === 'pendientes' && (
                                                <>
                                                    <button
                                                        onClick={() => setModalState({ id: a.id, tipo: 'aprobar' })}
                                                        className="flex size-7 items-center justify-center rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-600 transition-colors hover:bg-emerald-100"
                                                        title="Aprobar"
                                                    >
                                                        <CheckIcon className="size-3.5" />
                                                    </button>
                                                    <button
                                                        onClick={() => setModalState({ id: a.id, tipo: 'rechazar' })}
                                                        className="flex size-7 items-center justify-center rounded-lg border border-red-300 bg-red-50 text-red-600 transition-colors hover:bg-red-100"
                                                        title="Rechazar"
                                                    >
                                                        <XIcon className="size-3.5" />
                                                    </button>
                                                </>
                                            )}
                                            <Link
                                                href={`/admin/costos/aprobaciones/${a.id}`}
                                                className="flex size-7 items-center justify-center rounded-lg border border-base-300 text-base-content/60 transition-colors hover:bg-base-200"
                                                title="Ver detalle"
                                            >
                                                <EyeIcon className="size-3.5" />
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {modalState && (
                <ObservacionesModal
                    aprobacionId={modalState.id}
                    tipo={modalState.tipo}
                    onClose={() => setModalState(null)}
                />
            )}

            {pdfModal && (
                <PdfModal url={pdfModal.url} title={pdfModal.title} onClose={() => setPdfModal(null)} />
            )}
        </>
    );
}

export default function AprobacionesIndex({ pendientes, aprobadas, rechazadas }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mis Aprobaciones" />

            <div className="p-6">
                <h1 className="mb-6 text-2xl font-semibold">Mis Aprobaciones</h1>

                <div role="tablist" className="tabs tabs-bordered mb-6">
                    <input type="radio" name="aprobaciones_tabs" role="tab" className="tab" aria-label={`Pendientes (${pendientes.length})`} defaultChecked />
                    <div role="tabpanel" className="tab-content py-4">
                        <AprobacionTable items={pendientes} tipo="pendientes" />
                    </div>

                    <input type="radio" name="aprobaciones_tabs" role="tab" className="tab" aria-label={`Aprobadas (${aprobadas.length})`} />
                    <div role="tabpanel" className="tab-content py-4">
                        <AprobacionTable items={aprobadas} tipo="aprobadas" />
                    </div>

                    <input type="radio" name="aprobaciones_tabs" role="tab" className="tab" aria-label={`Rechazadas (${rechazadas.length})`} />
                    <div role="tabpanel" className="tab-content py-4">
                        <AprobacionTable items={rechazadas} tipo="rechazadas" />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
