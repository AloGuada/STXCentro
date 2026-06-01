import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Copy, Download, FileUp, Link2, Link2Off, Trash2, Upload, UserMinus, UserPlus } from 'lucide-react';
import { useRef, useState } from 'react';

type Archivo = {
    id: number;
    nombre_original: string;
    mime: string | null;
    size: number | null;
    descripcion: string | null;
    subido_por_type: string;
    link_token: string | null;
    link_expira_en: string | null;
    auto_eliminar_en: string | null;
    link_publico: string | null;
    created_at: string;
};

type Externo = {
    id: number;
    nombre: string;
    email: string;
    empresa: string | null;
};

type Carpeta = {
    id: number;
    nombre: string;
    descripcion: string | null;
    usuario: { id: string; name: string } | null;
};

type Props = {
    carpeta: Carpeta;
    archivos: {
        data: Archivo[];
        current_page: number;
        last_page: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    externos: Externo[];
    externosDisponibles: Externo[];
};

function formatSize(bytes: number | null): string {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    let size = bytes;
    while (size >= 1024 && i < units.length - 1) {
        size /= 1024;
        i++;
    }
    return `${size.toFixed(1)} ${units[i]}`;
}

export default function DriveCarpetaShow({ carpeta, archivos, externos, externosDisponibles }: Props) {
    const [activeTab, setActiveTab] = useState<'archivos' | 'accesos'>('archivos');
    const [showUpload, setShowUpload] = useState(false);
    const [linkModal, setLinkModal] = useState<Archivo | null>(null);
    const [showAccesoModal, setShowAccesoModal] = useState(false);
    const [selectedExternoId, setSelectedExternoId] = useState('');
    const fileInputRef = useRef<HTMLInputElement>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Drive', href: '/admin/drive' },
        { title: 'Carpetas', href: '/admin/drive/carpetas' },
        { title: carpeta.nombre, href: `/admin/drive/carpetas/${carpeta.id}` },
    ];

    const uploadForm = useForm<{ archivo: File | null; descripcion: string }>({
        archivo: null,
        descripcion: '',
    });

    const linkForm = useForm({
        link_expira_en: '',
        auto_eliminar_en: '',
    });

    function handleUpload(e: React.FormEvent) {
        e.preventDefault();
        uploadForm.post(`/admin/drive/carpetas/${carpeta.id}/archivos`, {
            forceFormData: true,
            onSuccess: () => { uploadForm.reset(); setShowUpload(false); },
        });
    }

    function handleGenerarLink(e: React.FormEvent) {
        e.preventDefault();
        if (!linkModal) return;
        linkForm.post(`/admin/drive/archivos/${linkModal.id}/generar-link`, {
            onSuccess: () => { setLinkModal(null); linkForm.reset(); },
        });
    }

    function handleRevocarLink(archivo: Archivo) {
        router.delete(`/admin/drive/archivos/${archivo.id}/revocar-link`);
    }

    function handleToggleAcceso(externoId: number) {
        router.patch(`/admin/drive/carpetas/${carpeta.id}/acceso/${externoId}`, {}, {
            onSuccess: () => { setSelectedExternoId(''); setShowAccesoModal(false); },
        });
    }

    function handleDeleteArchivo(archivo: Archivo) {
        if (confirm('¿Eliminar este archivo?')) {
            router.delete(`/admin/drive/archivos/${archivo.id}`);
        }
    }

    function copyToClipboard(text: string) {
        navigator.clipboard.writeText(text);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Drive - ${carpeta.nombre}`} />

            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-semibold">{carpeta.nombre}</h1>
                        {carpeta.descripcion && <p className="text-base-content/60 mt-1">{carpeta.descripcion}</p>}
                        {carpeta.usuario && <p className="text-xs text-base-content/40 mt-1">Creada por: {carpeta.usuario.name}</p>}
                    </div>
                    <div className="flex gap-2">
                        <button className="btn btn-primary" onClick={() => setShowUpload(!showUpload)}>
                            <Upload className="size-4" />
                            Subir Archivo
                        </button>
                        {externosDisponibles.length > 0 && (
                            <button className="btn btn-outline btn-primary" onClick={() => setShowAccesoModal(true)}>
                                <UserPlus className="size-4" />
                                Agregar Acceso
                            </button>
                        )}
                    </div>
                </div>

                {/* Upload form */}
                {showUpload && (
                    <form onSubmit={handleUpload} className="card bg-base-200 mb-6">
                        <div className="card-body">
                            <div
                                className="border-2 border-dashed rounded-lg p-6 text-center cursor-pointer border-base-300 hover:border-primary"
                                onClick={() => fileInputRef.current?.click()}
                            >
                                <FileUp className="size-8 mx-auto mb-2 opacity-40" />
                                {uploadForm.data.archivo ? (
                                    <p className="font-medium">{uploadForm.data.archivo.name}</p>
                                ) : (
                                    <p className="text-base-content/60">Clic para seleccionar archivo (máx. 50 MB)</p>
                                )}
                                <input ref={fileInputRef} type="file" className="hidden" onChange={(e) => uploadForm.setData('archivo', e.target.files?.[0] ?? null)} />
                            </div>
                            <input type="text" className="input input-bordered w-full mt-3" placeholder="Descripción (opcional)" value={uploadForm.data.descripcion} onChange={(e) => uploadForm.setData('descripcion', e.target.value)} />
                            <div className="card-actions justify-end mt-3">
                                <button type="button" className="btn btn-ghost" onClick={() => { setShowUpload(false); uploadForm.reset(); }}>Cancelar</button>
                                <button type="submit" className="btn btn-primary" disabled={!uploadForm.data.archivo || uploadForm.processing}>
                                    {uploadForm.processing ? 'Subiendo...' : 'Subir'}
                                </button>
                            </div>
                        </div>
                    </form>
                )}

                {/* Tabs */}
                <div className="tabs tabs-bordered mb-6">
                    <button className={`tab ${activeTab === 'archivos' ? 'tab-active' : ''}`} onClick={() => setActiveTab('archivos')}>
                        Archivos
                    </button>
                    <button className={`tab ${activeTab === 'accesos' ? 'tab-active' : ''}`} onClick={() => setActiveTab('accesos')}>
                        Accesos ({externos.length})
                    </button>
                </div>

                {activeTab === 'archivos' && (
                    <>
                        {archivos.data.length === 0 ? (
                            <p className="text-center py-8 text-base-content/60">No hay archivos en esta carpeta.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Archivo</th>
                                            <th>Origen</th>
                                            <th className="text-right">Tamaño</th>
                                            <th>Link</th>
                                            <th>Auto-eliminar</th>
                                            <th>Fecha</th>
                                            <th className="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {archivos.data.map((archivo) => (
                                            <tr key={archivo.id} className="hover">
                                                <td className="max-w-xs truncate font-medium">{archivo.nombre_original}</td>
                                                <td>
                                                    <span className={`badge badge-sm ${archivo.subido_por_type === 'interno' ? 'badge-info' : 'badge-warning'}`}>
                                                        {archivo.subido_por_type === 'interno' ? 'Admin' : 'Externo'}
                                                    </span>
                                                </td>
                                                <td className="text-right">{formatSize(archivo.size)}</td>
                                                <td>
                                                    {archivo.link_token ? (
                                                        <div className="flex items-center gap-1">
                                                            <span className="badge badge-success badge-sm">Activo</span>
                                                            <button className="btn btn-ghost btn-xs" onClick={() => copyToClipboard(archivo.link_publico!)} title="Copiar link">
                                                                <Copy className="size-3" />
                                                            </button>
                                                        </div>
                                                    ) : (
                                                        <span className="text-base-content/40">-</span>
                                                    )}
                                                </td>
                                                <td>
                                                    {archivo.auto_eliminar_en
                                                        ? new Date(archivo.auto_eliminar_en).toLocaleDateString('es-MX')
                                                        : '-'}
                                                </td>
                                                <td>{new Date(archivo.created_at).toLocaleDateString('es-MX')}</td>
                                                <td>
                                                    <div className="flex justify-center gap-1">
                                                        <a href={`/admin/drive/archivos/${archivo.id}/descargar`} className="btn btn-ghost btn-xs" title="Descargar">
                                                            <Download className="size-4" />
                                                        </a>
                                                        {archivo.link_token ? (
                                                            <button className="btn btn-ghost btn-xs text-warning" onClick={() => handleRevocarLink(archivo)} title="Revocar link">
                                                                <Link2Off className="size-4" />
                                                            </button>
                                                        ) : (
                                                            <button className="btn btn-ghost btn-xs text-success" onClick={() => { setLinkModal(archivo); linkForm.reset(); }} title="Generar link">
                                                                <Link2 className="size-4" />
                                                            </button>
                                                        )}
                                                        <button className="btn btn-ghost btn-xs text-error" onClick={() => handleDeleteArchivo(archivo)} title="Eliminar">
                                                            <Trash2 className="size-4" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </>
                )}

                {activeTab === 'accesos' && (
                    <>
                        {externos.length === 0 ? (
                            <p className="text-center py-8 text-base-content/60">Ningún usuario externo tiene acceso a esta carpeta.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Nombre</th>
                                            <th>Email</th>
                                            <th>Empresa</th>
                                            <th className="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {externos.map((ext) => (
                                            <tr key={ext.id} className="hover">
                                                <td className="font-medium">{ext.nombre}</td>
                                                <td>{ext.email}</td>
                                                <td>{ext.empresa ?? '-'}</td>
                                                <td className="text-center">
                                                    <button className="btn btn-ghost btn-xs text-error" onClick={() => handleToggleAcceso(ext.id)} title="Quitar acceso">
                                                        <UserMinus className="size-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </>
                )}
            </div>

            {/* Acceso modal */}
            {showAccesoModal && (
                <dialog className="modal modal-open">
                    <div className="modal-box">
                        <h3 className="font-bold text-lg">Agregar Acceso</h3>
                        <p className="text-sm text-base-content/60 mt-1">Selecciona un usuario externo para darle acceso a esta carpeta.</p>

                        <div className="mt-4">
                            <select
                                className="select select-bordered w-full"
                                value={selectedExternoId}
                                onChange={(e) => setSelectedExternoId(e.target.value)}
                            >
                                <option value="">Seleccionar usuario externo...</option>
                                {externosDisponibles.map((ext) => (
                                    <option key={ext.id} value={ext.id}>
                                        {ext.nombre} — {ext.email} {ext.empresa && `(${ext.empresa})`}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="modal-action">
                            <button type="button" className="btn btn-ghost" onClick={() => { setShowAccesoModal(false); setSelectedExternoId(''); }}>Cancelar</button>
                            <button
                                type="button"
                                className="btn btn-primary"
                                disabled={!selectedExternoId}
                                onClick={() => handleToggleAcceso(Number(selectedExternoId))}
                            >
                                Agregar
                            </button>
                        </div>
                    </div>
                    <form method="dialog" className="modal-backdrop">
                        <button onClick={() => { setShowAccesoModal(false); setSelectedExternoId(''); }}>close</button>
                    </form>
                </dialog>
            )}

            {/* Link generation modal */}
            {linkModal && (
                <dialog className="modal modal-open">
                    <div className="modal-box">
                        <h3 className="font-bold text-lg">Generar Link Público</h3>
                        <p className="text-sm text-base-content/60 mt-1">Para: {linkModal.nombre_original}</p>

                        <form onSubmit={handleGenerarLink} className="mt-4 space-y-4">
                            <div className="grid gap-2">
                                <label className="label-text">Fecha de expiración del link (opcional)</label>
                                <input
                                    type="datetime-local"
                                    className="input input-bordered w-full"
                                    value={linkForm.data.link_expira_en}
                                    onChange={(e) => linkForm.setData('link_expira_en', e.target.value)}
                                />
                            </div>

                            <div className="grid gap-2">
                                <label className="label-text">Fecha de auto-eliminación del archivo (opcional)</label>
                                <input
                                    type="datetime-local"
                                    className="input input-bordered w-full"
                                    value={linkForm.data.auto_eliminar_en}
                                    onChange={(e) => linkForm.setData('auto_eliminar_en', e.target.value)}
                                />
                            </div>

                            <div className="modal-action">
                                <button type="button" className="btn btn-ghost" onClick={() => setLinkModal(null)}>Cancelar</button>
                                <button type="submit" className="btn btn-primary" disabled={linkForm.processing}>
                                    Generar Link
                                </button>
                            </div>
                        </form>
                    </div>
                    <form method="dialog" className="modal-backdrop">
                        <button onClick={() => setLinkModal(null)}>close</button>
                    </form>
                </dialog>
            )}
        </AppLayout>
    );
}
