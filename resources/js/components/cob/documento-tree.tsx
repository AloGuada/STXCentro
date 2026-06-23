import type { CobDocumentoArchivo, CobDocumentoCarpeta, CobDocumentoSeccion } from '@/types/models';
import { router } from '@inertiajs/react';
import {
    ChevronDownIcon,
    ChevronRightIcon,
    DownloadIcon,
    EyeIcon,
    EyeOffIcon,
    FileIcon,
    FolderIcon,
    FolderPlusIcon,
    Trash2Icon,
    UploadIcon,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';

type Props = {
    proyectoId: number;
    secciones: CobDocumentoSeccion[];
    carpetas: CobDocumentoCarpeta[];
    archivos: CobDocumentoArchivo[];
    readOnly?: boolean;
    onOpenArchivo: (archivo: CobDocumentoArchivo) => void;
};

type UploadTarget = { seccion_id: number; carpeta_id: number | null };

function formatSize(bytes: number | null): string {
    if (!bytes) return '';
    const kb = bytes / 1024;
    if (kb < 1024) return `${kb.toFixed(0)} KB`;
    return `${(kb / 1024).toFixed(1)} MB`;
}

export function DocumentoTree({ proyectoId, secciones, carpetas, archivos, readOnly = false, onOpenArchivo }: Props) {
    const [expanded, setExpanded] = useState<Set<string>>(new Set());
    const [uploading, setUploading] = useState(false);
    const fileInput = useRef<HTMLInputElement>(null);
    const pendingTarget = useRef<UploadTarget | null>(null);

    const carpetasByParent = useMemo(() => {
        const map = new Map<string, CobDocumentoCarpeta[]>();
        for (const c of carpetas) {
            const key = c.parent_id == null ? `s${c.seccion_id}` : `c${c.parent_id}`;
            const list = map.get(key) ?? [];
            list.push(c);
            map.set(key, list);
        }
        return map;
    }, [carpetas]);

    const archivosByCarpeta = useMemo(() => {
        const map = new Map<string, CobDocumentoArchivo[]>();
        for (const a of archivos) {
            const key = a.carpeta_id == null ? `s${a.seccion_id}` : `c${a.carpeta_id}`;
            const list = map.get(key) ?? [];
            list.push(a);
            map.set(key, list);
        }
        return map;
    }, [archivos]);

    const toggle = (key: string) => {
        setExpanded((prev) => {
            const next = new Set(prev);
            next.has(key) ? next.delete(key) : next.add(key);
            return next;
        });
    };

    const toggleSeccionEstatus = (seccion: CobDocumentoSeccion) => {
        const estatus = seccion.estatus === 'completado' ? 'pendiente' : 'completado';
        router.put(
            `/admin/cob/proyectos/${proyectoId}/secciones/${seccion.id}/estatus`,
            { estatus },
            { preserveScroll: true, preserveState: true },
        );
    };

    const toggleSeccionVisible = (seccion: CobDocumentoSeccion) => {
        router.put(
            `/admin/cob/proyectos/${proyectoId}/secciones/${seccion.id}/visibilidad`,
            { visible: seccion.visible === false },
            { preserveScroll: true, preserveState: true },
        );
    };

    const crearCarpeta = (seccionId: number, parentId: number | null) => {
        const nombre = window.prompt('Nombre de la carpeta:');
        if (!nombre?.trim()) return;
        router.post(
            `/admin/cob/proyectos/${proyectoId}/documentos/carpetas`,
            { seccion_id: seccionId, parent_id: parentId, nombre: nombre.trim() },
            { preserveScroll: true, preserveState: true },
        );
    };

    const renombrarCarpeta = (carpeta: CobDocumentoCarpeta) => {
        const nombre = window.prompt('Nuevo nombre de la carpeta:', carpeta.nombre);
        if (!nombre?.trim() || nombre.trim() === carpeta.nombre) return;
        router.put(
            `/admin/cob/proyectos/${proyectoId}/documentos/carpetas/${carpeta.id}`,
            { seccion_id: carpeta.seccion_id, nombre: nombre.trim() },
            { preserveScroll: true, preserveState: true },
        );
    };

    const eliminarCarpeta = (carpeta: CobDocumentoCarpeta) => {
        if (!window.confirm(`¿Eliminar la carpeta "${carpeta.nombre}" y todo su contenido?`)) return;
        router.delete(`/admin/cob/proyectos/${proyectoId}/documentos/carpetas/${carpeta.id}`, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const eliminarArchivo = (archivo: CobDocumentoArchivo) => {
        if (!window.confirm(`¿Eliminar el archivo "${archivo.nombre_original}"?`)) return;
        router.delete(`/admin/cob/proyectos/${proyectoId}/documentos/archivos/${archivo.id}`, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const pedirArchivos = (target: UploadTarget) => {
        pendingTarget.current = target;
        fileInput.current?.click();
    };

    const onFilesSelected = (e: React.ChangeEvent<HTMLInputElement>) => {
        const files = e.target.files;
        const target = pendingTarget.current;
        if (!files || files.length === 0 || !target) return;

        setUploading(true);
        router.post(
            `/admin/cob/proyectos/${proyectoId}/documentos/archivos`,
            {
                seccion_id: target.seccion_id,
                carpeta_id: target.carpeta_id,
                archivos: Array.from(files),
            },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    setUploading(false);
                    pendingTarget.current = null;
                    if (fileInput.current) fileInput.current.value = '';
                },
            },
        );
    };

    const renderArchivo = (archivo: CobDocumentoArchivo, depth: number) => (
        <div
            key={`a${archivo.id}`}
            className="group flex items-center gap-2 rounded px-2 py-1 hover:bg-base-200"
            style={{ paddingLeft: `${depth * 1.25 + 0.5}rem` }}
        >
            <FileIcon className="size-4 shrink-0 text-base-content/50" />
            <button type="button" className="link link-hover truncate text-left text-sm" onClick={() => onOpenArchivo(archivo)}>
                {archivo.nombre_original}
            </button>
            {archivo.size != null && <span className="text-xs text-base-content/40">{formatSize(archivo.size)}</span>}
            <div className="ml-auto flex items-center gap-1 opacity-0 group-hover:opacity-100">
                <button type="button" className="btn btn-ghost btn-xs" title="Ver" onClick={() => onOpenArchivo(archivo)}>
                    <EyeIcon className="size-3.5" />
                </button>
                <a
                    href={`/admin/cob/documentos/archivos/${archivo.id}/descargar`}
                    className="btn btn-ghost btn-xs"
                    title="Descargar"
                >
                    <DownloadIcon className="size-3.5" />
                </a>
                {!readOnly && (
                    <button type="button" className="btn btn-ghost btn-xs text-error" title="Eliminar" onClick={() => eliminarArchivo(archivo)}>
                        <Trash2Icon className="size-3.5" />
                    </button>
                )}
            </div>
        </div>
    );

    const renderCarpeta = (carpeta: CobDocumentoCarpeta, depth: number) => {
        const key = `c${carpeta.id}`;
        const isOpen = expanded.has(key);
        const subcarpetas = carpetasByParent.get(key) ?? [];
        const subArchivos = archivosByCarpeta.get(key) ?? [];

        return (
            <div key={key}>
                <div
                    className="group flex items-center gap-1 rounded px-2 py-1 hover:bg-base-200"
                    style={{ paddingLeft: `${depth * 1.25 + 0.5}rem` }}
                >
                    <button type="button" className="flex min-w-0 items-center gap-1.5 text-left" onClick={() => toggle(key)}>
                        {isOpen ? <ChevronDownIcon className="size-4 shrink-0" /> : <ChevronRightIcon className="size-4 shrink-0" />}
                        <FolderIcon className="size-4 shrink-0 text-warning" />
                        <span className="truncate text-sm font-medium">{carpeta.nombre}</span>
                    </button>
                    {!readOnly && (
                        <div className="ml-auto flex items-center gap-1 opacity-0 group-hover:opacity-100">
                            <button type="button" className="btn btn-ghost btn-xs" title="Nueva subcarpeta" onClick={() => crearCarpeta(carpeta.seccion_id, carpeta.id)}>
                                <FolderPlusIcon className="size-3.5" />
                            </button>
                            <button type="button" className="btn btn-ghost btn-xs" title="Subir archivo" onClick={() => pedirArchivos({ seccion_id: carpeta.seccion_id, carpeta_id: carpeta.id })}>
                                <UploadIcon className="size-3.5" />
                            </button>
                            <button type="button" className="btn btn-ghost btn-xs" title="Renombrar" onClick={() => renombrarCarpeta(carpeta)}>
                                <span className="text-xs">✎</span>
                            </button>
                            <button type="button" className="btn btn-ghost btn-xs text-error" title="Eliminar carpeta" onClick={() => eliminarCarpeta(carpeta)}>
                                <Trash2Icon className="size-3.5" />
                            </button>
                        </div>
                    )}
                </div>
                {isOpen && (
                    <div>
                        {subcarpetas.map((c) => renderCarpeta(c, depth + 1))}
                        {subArchivos.map((a) => renderArchivo(a, depth + 1))}
                        {subcarpetas.length === 0 && subArchivos.length === 0 && (
                            <p className="py-1 text-xs text-base-content/40" style={{ paddingLeft: `${(depth + 1) * 1.25 + 0.5}rem` }}>
                                Vacía
                            </p>
                        )}
                    </div>
                )}
            </div>
        );
    };

    if (secciones.length === 0) {
        return <p className="text-sm text-base-content/60">No hay secciones de documentación configuradas.</p>;
    }

    return (
        <div className="rounded-box border border-base-300">
            {!readOnly && <input ref={fileInput} type="file" multiple className="hidden" onChange={onFilesSelected} />}
            {uploading && (
                <div className="border-b border-base-300 bg-info/10 px-3 py-1.5 text-xs text-info">Subiendo archivos…</div>
            )}
            {secciones.map((seccion) => {
                const key = `s${seccion.id}`;
                const visible = seccion.visible !== false;
                const isOpen = visible && expanded.has(key);
                const rootCarpetas = carpetasByParent.get(key) ?? [];
                const rootArchivos = archivosByCarpeta.get(key) ?? [];
                const total = rootCarpetas.length + rootArchivos.length;

                return (
                    <div key={key} className="border-b border-base-300 last:border-b-0">
                        <div className={`group flex items-center gap-1 bg-base-200/50 px-2 py-1.5 hover:bg-base-200 ${visible ? '' : 'opacity-60'}`}>
                            <button
                                type="button"
                                className="flex min-w-0 items-center gap-1.5 text-left"
                                onClick={() => visible && toggle(key)}
                                disabled={!visible}
                            >
                                {isOpen ? <ChevronDownIcon className="size-4 shrink-0" /> : <ChevronRightIcon className="size-4 shrink-0" />}
                                <FolderIcon className="size-4 shrink-0 text-primary" />
                                <span className="truncate text-sm font-semibold">{seccion.nombre}</span>
                                {visible ? (
                                    <span className="text-xs text-base-content/40">({total})</span>
                                ) : (
                                    <span className="text-xs italic text-base-content/40">oculta</span>
                                )}
                            </button>
                            {visible && (
                                <button
                                    type="button"
                                    className={`badge badge-sm ml-2 ${seccion.estatus === 'completado' ? 'badge-success' : 'badge-warning'} ${readOnly ? 'cursor-default' : 'cursor-pointer'}`}
                                    onClick={readOnly ? undefined : () => toggleSeccionEstatus(seccion)}
                                    disabled={readOnly}
                                    title={readOnly ? undefined : 'Cambiar estatus de la sección'}
                                >
                                    {seccion.estatus === 'completado' ? 'Completado' : 'Pendiente'}
                                </button>
                            )}
                            {!readOnly && (
                                <div className="ml-auto flex items-center gap-1">
                                    <button
                                        type="button"
                                        className="btn btn-ghost btn-xs"
                                        title={visible ? 'Ocultar sección en este proyecto' : 'Mostrar sección en este proyecto'}
                                        onClick={() => toggleSeccionVisible(seccion)}
                                    >
                                        {visible ? <EyeIcon className="size-3.5" /> : <EyeOffIcon className="size-3.5" />}
                                    </button>
                                    {visible && (
                                        <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100">
                                            <button type="button" className="btn btn-ghost btn-xs" title="Nueva carpeta" onClick={() => crearCarpeta(seccion.id, null)}>
                                                <FolderPlusIcon className="size-3.5" />
                                            </button>
                                            <button type="button" className="btn btn-ghost btn-xs" title="Subir archivo" onClick={() => pedirArchivos({ seccion_id: seccion.id, carpeta_id: null })}>
                                                <UploadIcon className="size-3.5" />
                                            </button>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                        {isOpen && (
                            <div className="py-1">
                                {rootCarpetas.map((c) => renderCarpeta(c, 1))}
                                {rootArchivos.map((a) => renderArchivo(a, 1))}
                                {total === 0 && (
                                    <p className="px-2 py-1 text-xs text-base-content/40" style={{ paddingLeft: '1.75rem' }}>
                                        Sin documentos
                                    </p>
                                )}
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
