import { DocumentoUpload } from '@/components/costos/documento-upload';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosAprobacionSolicitud, CostosSolicitudPago } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangleIcon, ArrowDownIcon, ArrowUpDownIcon, ArrowUpIcon, CheckIcon, EyeIcon, FileCheckIcon, FileTextIcon, PaperclipIcon, XIcon } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/solicitudes-pago' },
    { title: 'Mis Aprobaciones', href: '/admin/costos/aprobaciones' },
];

type Props = {
    pendientes: CostosAprobacionSolicitud[];
    aprobadas: CostosAprobacionSolicitud[];
    rechazadas: CostosAprobacionSolicitud[];
    soloLectura?: boolean;
    aprobador?: { id: string; name: string } | null;
    aprobadores?: { id: string; name: string }[];
};

const fmtDate = (date: string | null) =>
    date ? new Date(date).toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '-';

const fmtMoney = (n: number) => `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

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
                // Recarga completa remontando las tablas con props frescas
                // (`preserveState: false`). Un reload parcial (`only`) o con
                // estado preservado dejaba la tabla de pendientes desincronizada.
                router.get(window.location.pathname, {}, { preserveScroll: true, preserveState: false });
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

function ArchivosModal({ solicitud, onClose }: { solicitud: CostosSolicitudPago; onClose: () => void }) {
    const documentos = solicitud.tipo_solicitud?.documentos ?? [];
    const archivos = solicitud.archivos ?? [];
    const baseUrl = `/admin/costos/solicitudes-pago/${solicitud.id}/archivos`;

    return (
        <dialog className="modal modal-open">
            <div className="modal-box max-w-3xl">
                <div className="mb-4 flex items-center justify-between border-b border-base-300 pb-3">
                    <h3 className="font-medium">Archivos de {solicitud.folio}</h3>
                    <button className="btn btn-sm btn-ghost" onClick={onClose}>✕</button>
                </div>

                {documentos.length > 0 ? (
                    <div className="space-y-4">
                        {documentos.map((doc) => (
                            <DocumentoUpload
                                key={doc.id}
                                documento={doc}
                                archivos={archivos}
                                storeUrl={baseUrl}
                                destroyUrlPrefix={baseUrl}
                                readOnly
                            />
                        ))}
                    </div>
                ) : archivos.length > 0 ? (
                    <div className="space-y-2">
                        {archivos.map((a) => (
                            <a
                                key={a.id}
                                href={`/storage/${a.media?.path}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="flex items-center gap-2 rounded bg-base-200 p-2 text-sm hover:bg-base-300"
                            >
                                <FileTextIcon className="size-4 text-base-content/60" />
                                {a.media?.nombre_original ?? 'Archivo'}
                            </a>
                        ))}
                    </div>
                ) : (
                    <p className="text-base-content/60">Esta solicitud no tiene archivos.</p>
                )}
            </div>
            <div className="modal-backdrop" onClick={onClose} />
        </dialog>
    );
}

function MediaModal({ titulo, archivos, onClose }: { titulo: string; archivos: { id: number; url: string; nombre: string }[]; onClose: () => void }) {
    return (
        <dialog className="modal modal-open">
            <div className="modal-box max-w-3xl">
                <div className="mb-4 flex items-center justify-between border-b border-base-300 pb-3">
                    <h3 className="font-medium">Archivos de {titulo}</h3>
                    <button className="btn btn-sm btn-ghost" onClick={onClose}>✕</button>
                </div>

                {archivos.length > 0 ? (
                    <div className="space-y-2">
                        {archivos.map((a) => (
                            <a
                                key={a.id}
                                href={a.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="flex items-center gap-2 rounded bg-base-200 p-2 text-sm hover:bg-base-300"
                            >
                                <FileTextIcon className="size-4 text-base-content/60" />
                                {a.nombre}
                            </a>
                        ))}
                    </div>
                ) : (
                    <p className="text-base-content/60">Esta requisición no tiene archivos.</p>
                )}
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

type RowDisplay = {
    folio: string;
    createdAt: string | null;
    solicitanteName: string;
    departamentoNombre: string;
    proveedor: { razon_social: string; rfc?: string | null; subLabel?: string | null } | null;
    tipoLabel: string;
    monto: number;
    // Para requisiciones: el monto es el neto a pagar (subtotal + IVA - retenciones)
    // cuando ya hay OC/selecciones; si aún no, es el mejor precio (estimado).
    montoEsNeto: boolean;
    tieneSobregiro: boolean;
    detailHref: string;
    archivosCount: number;
    // Archivos adjuntos genéricos (requisición): se listan en un modal propio.
    mediaFiles: { id: number; url: string; nombre: string }[];
    pdfUrl: string | null;
    pdfTitle: string;
    firmadoUrl: string | null;
    firmadoTitle: string;
    estatusOrigen: string | null;
};

function buildDisplay(a: CostosAprobacionSolicitud): RowDisplay | null {
    if (a.tipo === 'requisicion' && a.requisicion) {
        const req = a.requisicion;
        const mejor = req.mejor_proveedor;
        const cotCount = req.proveedores_cotizadores_count ?? 0;
        const subLabel = cotCount > 0
            ? `${cotCount} ${cotCount === 1 ? 'proveedor cotizó' : 'proveedores cotizaron'}`
            : 'Sin cotizaciones';

        return {
            folio: req.folio,
            createdAt: req.created_at,
            solicitanteName: req.solicitante?.name ?? '-',
            departamentoNombre: req.departamento?.descripcion ?? '',
            proveedor: mejor
                ? { razon_social: mejor.nombre_comercial || mejor.razon_social, rfc: null, subLabel }
                : { razon_social: 'Cotización parcial', rfc: null, subLabel },
            tipoLabel: 'Requisición de compras',
            // Al inicio (sin OC/selecciones) el neto es 0: se muestra el mejor
            // precio como estimado. Una vez definida la OC, se muestra el neto.
            monto: (a.requisicion_total ?? 0) > 0
                ? (a.requisicion_total ?? 0)
                : (mejor ? mejor.total : 0),
            montoEsNeto: (a.requisicion_total ?? 0) > 0,
            tieneSobregiro: Boolean(req.tiene_sobregiro),
            detailHref: `/admin/costos/requisiciones/${req.id}`,
            archivosCount: 0,
            mediaFiles: (req.media ?? []).map((m) => ({
                id: m.id,
                url: `/storage/${m.path}`,
                nombre: m.nombre_original || m.descripcion || 'Archivo',
            })),
            // El comparativo se genera una vez en aprobación; en la bandeja de
            // firmas la requisición siempre está pendiente_aprobacion, así que
            // el endpoint (estatus ≥ pendiente_aprobacion) lo permite.
            pdfUrl: `/admin/costos/requisiciones/${req.id}/pdf`,
            pdfTitle: `Comparativo ${req.folio}`,
            firmadoUrl: null,
            firmadoTitle: '',
            estatusOrigen: req.estatus,
        };
    }

    const sol = a.solicitud;
    if (!sol) return null;

    return {
        folio: sol.folio ?? '-',
        createdAt: sol.created_at ?? null,
        solicitanteName: sol.solicitante?.name ?? '-',
        departamentoNombre: sol.departamento?.descripcion ?? '',
        proveedor: sol.proveedor ? { razon_social: sol.proveedor.razon_social, rfc: sol.proveedor.rfc } : null,
        tipoLabel: sol.tipo_solicitud?.titulo ?? '-',
        monto: Number(sol.monto_total ?? 0),
        montoEsNeto: true,
        tieneSobregiro: Boolean(sol.tiene_sobregiro),
        detailHref: `/admin/costos/aprobaciones/${a.id}`,
        archivosCount: sol.archivos?.length ?? 0,
        mediaFiles: [],
        pdfUrl: sol.estatus !== 'borrador' ? `/admin/costos/solicitudes-pago/${sol.id}/pdf` : null,
        pdfTitle: `Formato ${sol.folio}`,
        firmadoUrl: sol.media ? `/storage/${sol.media.path}` : null,
        firmadoTitle: `Firmado ${sol.folio}`,
        estatusOrigen: sol.estatus ?? null,
    };
}

type SortKey = 'tipo' | 'nivel' | 'folio' | 'solicitante' | 'proveedor' | 'concepto' | 'monto' | 'fecha' | 'observaciones';

function AprobacionTable({ items, tipo, soloLectura = false }: { items: CostosAprobacionSolicitud[]; tipo: 'pendientes' | 'aprobadas' | 'rechazadas'; soloLectura?: boolean }) {
    const [modalState, setModalState] = useState<{ id: number; tipo: 'aprobar' | 'rechazar' } | null>(null);
    const [pdfModal, setPdfModal] = useState<{ url: string; title: string } | null>(null);
    const [archivosModal, setArchivosModal] = useState<CostosSolicitudPago | null>(null);
    const [mediaModal, setMediaModal] = useState<{ titulo: string; archivos: { id: number; url: string; nombre: string }[] } | null>(null);
    const [sort, setSort] = useState<{ key: SortKey; dir: 'asc' | 'desc' } | null>(null);

    // Preserva el scroll de la tabla al ir/volver del detalle (por pestaña).
    const scrollRef = useRef<HTMLDivElement>(null);
    const storageKey = `aprobaciones-scroll-${tipo}`;
    useEffect(() => {
        const el = scrollRef.current;
        const saved = el && sessionStorage.getItem(storageKey);
        if (el && saved) {
            el.scrollTop = Number(saved) || 0;
        }
    }, [storageKey]);
    const handleScroll = () => {
        if (scrollRef.current) {
            sessionStorage.setItem(storageKey, String(scrollRef.current.scrollTop));
        }
    };

    const toggleSort = (key: SortKey) =>
        setSort((prev) => (prev?.key === key ? (prev.dir === 'asc' ? { key, dir: 'desc' } : null) : { key, dir: 'asc' }));

    const rows = useMemo(() => {
        const base = items
            .map((a) => ({ a, d: buildDisplay(a) }))
            .filter((x): x is { a: CostosAprobacionSolicitud; d: RowDisplay } => x.d !== null);

        if (!sort) return base;

        const valor = ({ a, d }: { a: CostosAprobacionSolicitud; d: RowDisplay }): string | number => {
            switch (sort.key) {
                case 'tipo': return a.tipo ?? '';
                case 'nivel': return a.nivel ?? 0;
                case 'folio': return d.folio;
                case 'solicitante': return d.solicitanteName;
                case 'proveedor': return d.proveedor?.razon_social ?? '';
                case 'concepto': return d.tipoLabel;
                case 'monto': return d.monto;
                case 'fecha': return a.fecha_respuesta ?? '';
                case 'observaciones': return a.observaciones ?? '';
            }
        };

        return [...base].sort((p, q) => {
            const x = valor(p);
            const y = valor(q);
            const cmp = typeof x === 'number' && typeof y === 'number'
                ? x - y
                : String(x).localeCompare(String(y), 'es', { numeric: true });
            return sort.dir === 'asc' ? cmp : -cmp;
        });
    }, [items, sort]);

    const SortHeader = ({ label, sortKey, className }: { label: string; sortKey: SortKey; className?: string }) => (
        <th className={className}>
            <button type="button" onClick={() => toggleSort(sortKey)} className="inline-flex items-center gap-1 hover:text-base-content">
                {label}
                {sort?.key === sortKey
                    ? (sort.dir === 'asc' ? <ArrowUpIcon className="size-3" /> : <ArrowDownIcon className="size-3" />)
                    : <ArrowUpDownIcon className="size-3 opacity-40" />}
            </button>
        </th>
    );

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
            <div ref={scrollRef} onScroll={handleScroll} className="overflow-auto rounded-box border border-base-300" style={{ maxHeight: '70vh' }}>
                <table className="table">
                    <thead className="sticky top-0 z-10 bg-base-100">
                        <tr>
                            <SortHeader label="Tipo" sortKey="tipo" />
                            <SortHeader label="Folio" sortKey="folio" />
                            <SortHeader label="Nivel" sortKey="nivel" className="text-center" />
                            <SortHeader label="Solicitante" sortKey="solicitante" />
                            <SortHeader label="Proveedor" sortKey="proveedor" />
                            <SortHeader label="Concepto" sortKey="concepto" />
                            <SortHeader label="Monto" sortKey="monto" className="text-right" />
                            <th>Docs</th>
                            {tipo !== 'pendientes' && <SortHeader label="Fecha" sortKey="fecha" />}
                            {tipo !== 'pendientes' && <SortHeader label="Observaciones" sortKey="observaciones" />}
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map(({ a, d }) => {
                            const tieneSobregiro = d.tieneSobregiro;
                            const esRequisicion = a.tipo === 'requisicion';
                            const sol = !esRequisicion ? a.solicitud ?? null : null;
                            const docsSolicitados = sol?.tipo_solicitud?.documentos?.length ?? 0;

                            return (
                                <tr key={a.id} className={tieneSobregiro ? 'bg-error/10' : 'hover'}>
                                    <td>
                                        <span className={`badge badge-sm ${esRequisicion ? 'badge-info' : 'badge-ghost'}`}>
                                            {esRequisicion ? 'Requisición' : 'Pago'}
                                        </span>
                                    </td>
                                    <td>
                                        <div>
                                            <span className="font-mono text-xs font-medium">{d.folio}</span>
                                            <div className="mt-0.5 text-[11px] text-base-content/50">{fmtDate(d.createdAt)}</div>
                                        </div>
                                    </td>
                                    <td className="text-center">
                                        <span className="badge badge-ghost badge-sm">Nivel {a.nivel}</span>
                                    </td>
                                    <td>
                                        <div>
                                            <div className="text-sm">{d.solicitanteName}</div>
                                            <div className="mt-0.5 text-[11px] text-base-content/50">{d.departamentoNombre}</div>
                                        </div>
                                    </td>
                                    <td>
                                        {d.proveedor ? (
                                            <div>
                                                <div className="text-sm">{d.proveedor.razon_social}</div>
                                                {d.proveedor.rfc && (
                                                    <div className="mt-0.5 text-[11px] text-base-content/50">RFC: {d.proveedor.rfc}</div>
                                                )}
                                                {d.proveedor.subLabel && (
                                                    <div className="mt-0.5 text-[11px] text-base-content/50">{d.proveedor.subLabel}</div>
                                                )}
                                            </div>
                                        ) : (
                                            <span className="text-base-content/40">-</span>
                                        )}
                                    </td>
                                    <td><span className="text-xs text-base-content/60">{d.tipoLabel}</span></td>
                                    <td className="text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            {tieneSobregiro && (
                                                <span title="Sobregiro en centro de costos">
                                                    <AlertTriangleIcon className="size-4 text-error" />
                                                </span>
                                            )}
                                            <span className="font-medium">{fmtMoney(d.monto)}</span>
                                        </div>
                                        {!d.montoEsNeto && (
                                            <div className="mt-0.5 text-[11px] text-base-content/50" title="El neto a pagar se calcula al definir la OC">
                                                Mejor precio (estimado)
                                            </div>
                                        )}
                                        {tieneSobregiro && (
                                            <div className="mt-0.5 text-[11px] font-semibold text-error">Centro de costos en sobregiro</div>
                                        )}
                                    </td>
                                    <td>
                                        <div className="flex gap-1.5">
                                            {sol && (d.archivosCount > 0 || docsSolicitados > 0) && (
                                                <button
                                                    onClick={(e) => { e.stopPropagation(); setArchivosModal(sol); }}
                                                    className="flex size-7 items-center justify-center rounded-lg border border-base-300 text-base-content/60 transition-colors hover:bg-base-200"
                                                    title={`Ver archivos (${d.archivosCount}/${docsSolicitados || d.archivosCount})`}
                                                >
                                                    <PaperclipIcon className="size-3.5" />
                                                </button>
                                            )}
                                            {esRequisicion && d.mediaFiles.length > 0 && (
                                                <button
                                                    onClick={(e) => { e.stopPropagation(); setMediaModal({ titulo: d.folio, archivos: d.mediaFiles }); }}
                                                    className="flex size-7 items-center justify-center rounded-lg border border-base-300 text-base-content/60 transition-colors hover:bg-base-200"
                                                    title={`Ver archivos (${d.mediaFiles.length})`}
                                                >
                                                    <PaperclipIcon className="size-3.5" />
                                                </button>
                                            )}
                                            {d.pdfUrl && (
                                                <button
                                                    onClick={(e) => { e.stopPropagation(); setPdfModal({ url: d.pdfUrl!, title: d.pdfTitle }); }}
                                                    className="flex size-7 items-center justify-center rounded-lg border border-base-300 text-base-content/60 transition-colors hover:bg-base-200"
                                                    title="Ver PDF"
                                                >
                                                    <FileTextIcon className="size-3.5" />
                                                </button>
                                            )}
                                            {d.firmadoUrl && (
                                                <button
                                                    onClick={(e) => { e.stopPropagation(); setPdfModal({ url: d.firmadoUrl!, title: d.firmadoTitle }); }}
                                                    className="flex size-7 items-center justify-center rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-600 transition-colors hover:bg-emerald-100"
                                                    title="Ver PDF firmado"
                                                >
                                                    <FileCheckIcon className="size-3.5" />
                                                </button>
                                            )}
                                            {d.archivosCount === 0 && d.mediaFiles.length === 0 && !d.pdfUrl && !d.firmadoUrl && (
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
                                            {!soloLectura && tipo === 'pendientes' && (
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
                                            {!soloLectura ? (
                                                <Link
                                                    href={d.detailHref}
                                                    className="flex size-7 items-center justify-center rounded-lg border border-base-300 text-base-content/60 transition-colors hover:bg-base-200"
                                                    title="Ver detalle"
                                                >
                                                    <EyeIcon className="size-3.5" />
                                                </Link>
                                            ) : (
                                                <span className="text-xs text-base-content/40">-</span>
                                            )}
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

            {archivosModal && (
                <ArchivosModal solicitud={archivosModal} onClose={() => setArchivosModal(null)} />
            )}

            {mediaModal && (
                <MediaModal titulo={mediaModal.titulo} archivos={mediaModal.archivos} onClose={() => setMediaModal(null)} />
            )}
        </>
    );
}

export default function AprobacionesIndex({ pendientes, aprobadas, rechazadas, soloLectura = false, aprobador = null, aprobadores = [] }: Props) {
    const titulo = soloLectura ? 'Bandeja de aprobador' : 'Mis Aprobaciones';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={titulo} />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">{titulo}</h1>
                        {soloLectura && (
                            <p className="mt-1 text-sm text-base-content/60">
                                Vista de solo lectura de las aprobaciones de otro usuario. No puedes aprobar ni rechazar desde aquí.
                            </p>
                        )}
                    </div>

                    {soloLectura && (
                        <label className="flex items-center gap-2 text-sm">
                            <span className="text-base-content/60">Aprobador:</span>
                            <select
                                className="select select-bordered select-sm min-w-56"
                                value={aprobador?.id ?? ''}
                                onChange={(e) => {
                                    const id = e.target.value;
                                    router.get(`/admin/costos/aprobaciones/bandeja${id ? `/${id}` : ''}`, {}, { preserveScroll: true });
                                }}
                            >
                                <option value="">Selecciona un aprobador…</option>
                                {aprobadores.map((u) => (
                                    <option key={u.id} value={u.id}>{u.name}</option>
                                ))}
                            </select>
                        </label>
                    )}
                </div>

                {soloLectura && !aprobador ? (
                    <p className="py-12 text-center text-base-content/60">Selecciona un aprobador para ver su bandeja.</p>
                ) : (
                    <div role="tablist" className="tabs tabs-bordered mb-6">
                        <input type="radio" name="aprobaciones_tabs" role="tab" className="tab" aria-label={`Pendientes (${pendientes.length})`} defaultChecked />
                        <div role="tabpanel" className="tab-content py-4">
                            <AprobacionTable items={pendientes} tipo="pendientes" soloLectura={soloLectura} />
                        </div>

                        <input type="radio" name="aprobaciones_tabs" role="tab" className="tab" aria-label={`Aprobadas (${aprobadas.length})`} />
                        <div role="tabpanel" className="tab-content py-4">
                            <AprobacionTable items={aprobadas} tipo="aprobadas" soloLectura={soloLectura} />
                        </div>

                        <input type="radio" name="aprobaciones_tabs" role="tab" className="tab" aria-label={`Rechazadas (${rechazadas.length})`} />
                        <div role="tabpanel" className="tab-content py-4">
                            <AprobacionTable items={rechazadas} tipo="rechazadas" soloLectura={soloLectura} />
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
