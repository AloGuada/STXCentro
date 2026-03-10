import DriveLayout from '@/layouts/drive/drive-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { FileText, FolderOpen, HardDrive } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/drive' }];

type Carpeta = {
    id: number;
    nombre: string;
    descripcion: string | null;
    archivos_count: number;
    archivos_sum_size: number | null;
};

type Archivo = {
    id: number;
    nombre_original: string;
    mime: string | null;
    size: number | null;
    carpeta: { id: number; nombre: string } | null;
    created_at: string;
};

type Props = {
    carpetas: Carpeta[];
    archivosRecientes: Archivo[];
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

export default function DriveDashboard({ carpetas, archivosRecientes }: Props) {
    return (
        <DriveLayout breadcrumbs={breadcrumbs}>
            <Head title="Drive - Dashboard" />

            <div className="p-6">
                <h1 className="text-2xl font-semibold mb-6">Mis Carpetas</h1>

                {carpetas.length === 0 ? (
                    <div className="text-center py-12 text-base-content/60">
                        <FolderOpen className="size-12 mx-auto mb-4 opacity-40" />
                        <p>No tiene carpetas asignadas.</p>
                        <p className="text-sm">Contacte al administrador para obtener acceso.</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                        {carpetas.map((carpeta) => (
                            <Link
                                key={carpeta.id}
                                href={`/drive/carpetas/${carpeta.id}`}
                                className="card bg-base-200 hover:bg-base-300 transition-colors cursor-pointer"
                            >
                                <div className="card-body">
                                    <div className="flex items-start gap-3">
                                        <FolderOpen className="size-8 text-primary shrink-0 mt-1" />
                                        <div className="min-w-0">
                                            <h2 className="card-title text-base truncate">{carpeta.nombre}</h2>
                                            {carpeta.descripcion && (
                                                <p className="text-sm text-base-content/60 truncate">{carpeta.descripcion}</p>
                                            )}
                                            <div className="flex gap-4 mt-2 text-xs text-base-content/50">
                                                <span className="flex items-center gap-1">
                                                    <FileText className="size-3" />
                                                    {carpeta.archivos_count} archivo(s)
                                                </span>
                                                <span className="flex items-center gap-1">
                                                    <HardDrive className="size-3" />
                                                    {formatSize(carpeta.archivos_sum_size)}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}

                {archivosRecientes.length > 0 && (
                    <>
                        <h2 className="text-lg font-medium mb-3">Archivos Recientes</h2>
                        <div className="overflow-x-auto">
                            <table className="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Archivo</th>
                                        <th>Carpeta</th>
                                        <th className="text-right">Tamaño</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {archivosRecientes.map((archivo) => (
                                        <tr key={archivo.id} className="hover">
                                            <td className="max-w-xs truncate">{archivo.nombre_original}</td>
                                            <td>{archivo.carpeta?.nombre ?? '-'}</td>
                                            <td className="text-right">{formatSize(archivo.size)}</td>
                                            <td>{new Date(archivo.created_at).toLocaleDateString('es-MX')}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </div>
        </DriveLayout>
    );
}
