import DriveLayout from '@/layouts/drive/drive-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Download, FileUp, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';

type Archivo = {
    id: number;
    nombre_original: string;
    mime: string | null;
    size: number | null;
    descripcion: string | null;
    subido_por_type: string;
    subido_por_id: string;
    created_at: string;
};

type Carpeta = {
    id: number;
    nombre: string;
    descripcion: string | null;
};

type PaginatedArchivos = {
    data: Archivo[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};

type Props = {
    carpeta: Carpeta;
    archivos: PaginatedArchivos;
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

export default function DriveCarpetaShow({ carpeta, archivos }: Props) {
    const [showUpload, setShowUpload] = useState(false);
    const [dragActive, setDragActive] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/drive' },
        { title: carpeta.nombre, href: `/drive/carpetas/${carpeta.id}` },
    ];

    const { data, setData, post, processing, reset, errors } = useForm<{
        archivo: File | null;
        carpeta_id: number;
        descripcion: string;
    }>({
        archivo: null,
        carpeta_id: carpeta.id,
        descripcion: '',
    });

    function handleUpload(e: React.FormEvent) {
        e.preventDefault();
        post('/drive/archivos', {
            forceFormData: true,
            onSuccess: () => {
                reset();
                setShowUpload(false);
            },
        });
    }

    function handleDrop(e: React.DragEvent) {
        e.preventDefault();
        setDragActive(false);
        const file = e.dataTransfer.files[0];
        if (file) {
            setData('archivo', file);
            setShowUpload(true);
        }
    }

    function handleDelete(archivo: Archivo) {
        if (confirm('¿Eliminar este archivo?')) {
            router.delete(`/drive/archivos/${archivo.id}`);
        }
    }

    return (
        <DriveLayout breadcrumbs={breadcrumbs}>
            <Head title={`Drive - ${carpeta.nombre}`} />

            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-semibold">{carpeta.nombre}</h1>
                        {carpeta.descripcion && (
                            <p className="text-base-content/60 mt-1">{carpeta.descripcion}</p>
                        )}
                    </div>
                    <button
                        className="btn btn-primary"
                        onClick={() => setShowUpload(!showUpload)}
                    >
                        <Upload className="size-4" />
                        Subir Archivo
                    </button>
                </div>

                {showUpload && (
                    <form onSubmit={handleUpload} className="card bg-base-200 mb-6">
                        <div className="card-body">
                            <div
                                className={`border-2 border-dashed rounded-lg p-8 text-center transition-colors ${dragActive ? 'border-primary bg-primary/5' : 'border-base-300'}`}
                                onDragOver={(e) => { e.preventDefault(); setDragActive(true); }}
                                onDragLeave={() => setDragActive(false)}
                                onDrop={handleDrop}
                                onClick={() => fileInputRef.current?.click()}
                            >
                                <FileUp className="size-8 mx-auto mb-2 opacity-40" />
                                {data.archivo ? (
                                    <p className="font-medium">{data.archivo.name}</p>
                                ) : (
                                    <p className="text-base-content/60">
                                        Arrastra un archivo aquí o haz clic para seleccionar
                                    </p>
                                )}
                                <p className="text-xs text-base-content/40 mt-1">
                                    PDF, DOC, XLS, ZIP, JPG, PNG, DWG, DXF (máx. 50 MB)
                                </p>
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    className="hidden"
                                    onChange={(e) => setData('archivo', e.target.files?.[0] ?? null)}
                                />
                            </div>
                            {errors.archivo && <p className="text-error text-sm mt-1">{errors.archivo}</p>}

                            <input
                                type="text"
                                className="input input-bordered w-full mt-3"
                                placeholder="Descripción (opcional)"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                            />

                            <div className="card-actions justify-end mt-3">
                                <button type="button" className="btn btn-ghost" onClick={() => { setShowUpload(false); reset(); }}>
                                    Cancelar
                                </button>
                                <button type="submit" className="btn btn-primary" disabled={!data.archivo || processing}>
                                    {processing ? 'Subiendo...' : 'Subir'}
                                </button>
                            </div>
                        </div>
                    </form>
                )}

                {archivos.data.length === 0 ? (
                    <div className="text-center py-12 text-base-content/60">
                        <FileUp className="size-12 mx-auto mb-4 opacity-40" />
                        <p>Esta carpeta está vacía.</p>
                    </div>
                ) : (
                    <>
                        <div className="overflow-x-auto">
                            <table className="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Archivo</th>
                                        <th>Descripción</th>
                                        <th className="text-right">Tamaño</th>
                                        <th>Fecha</th>
                                        <th className="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {archivos.data.map((archivo) => (
                                        <tr key={archivo.id} className="hover">
                                            <td className="max-w-xs truncate font-medium">{archivo.nombre_original}</td>
                                            <td className="max-w-xs truncate text-base-content/60">{archivo.descripcion ?? '-'}</td>
                                            <td className="text-right">{formatSize(archivo.size)}</td>
                                            <td>{new Date(archivo.created_at).toLocaleDateString('es-MX')}</td>
                                            <td>
                                                <div className="flex justify-center gap-1">
                                                    <a
                                                        href={`/drive/archivos/${archivo.id}/descargar`}
                                                        className="btn btn-ghost btn-xs"
                                                        title="Descargar"
                                                    >
                                                        <Download className="size-4" />
                                                    </a>
                                                    {archivo.subido_por_type === 'externo' && (
                                                        <button
                                                            className="btn btn-ghost btn-xs text-error"
                                                            onClick={() => handleDelete(archivo)}
                                                            title="Eliminar"
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {archivos.last_page > 1 && (
                            <div className="flex justify-center mt-4 join">
                                {archivos.prev_page_url && (
                                    <button className="join-item btn btn-sm" onClick={() => router.get(archivos.prev_page_url!)}>
                                        «
                                    </button>
                                )}
                                <button className="join-item btn btn-sm btn-disabled">
                                    {archivos.current_page} / {archivos.last_page}
                                </button>
                                {archivos.next_page_url && (
                                    <button className="join-item btn btn-sm" onClick={() => router.get(archivos.next_page_url!)}>
                                        »
                                    </button>
                                )}
                            </div>
                        )}
                    </>
                )}
            </div>
        </DriveLayout>
    );
}
