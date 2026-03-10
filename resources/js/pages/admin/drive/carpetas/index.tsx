import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Edit, Eye, Plus, Trash2 } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Drive', href: '/admin/drive' },
    { title: 'Carpetas', href: '/admin/drive/carpetas' },
];

type Carpeta = {
    id: number;
    nombre: string;
    descripcion: string | null;
    archivos_count: number;
    externos_count: number;
    archivos_sum_size: number | null;
    usuario: { id: string; nombre: string } | null;
    created_at: string;
};

type Props = {
    carpetas: {
        data: Carpeta[];
        current_page: number;
        last_page: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
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

export default function DriveCarpetasIndex({ carpetas }: Props) {
    function handleDelete(carpeta: Carpeta) {
        if (confirm(`¿Eliminar la carpeta "${carpeta.nombre}" y todos sus archivos?`)) {
            router.delete(`/admin/drive/carpetas/${carpeta.id}`);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Drive - Carpetas" />

            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl font-semibold">Carpetas</h1>
                    <Link href="/admin/drive/carpetas/create" className="btn btn-primary">
                        <Plus className="size-4" />
                        Nueva Carpeta
                    </Link>
                </div>

                <div className="overflow-x-auto">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Creada por</th>
                                <th className="text-center">Archivos</th>
                                <th className="text-center">Externos</th>
                                <th className="text-right">Tamaño</th>
                                <th className="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {carpetas.data.map((carpeta) => (
                                <tr key={carpeta.id} className="hover">
                                    <td>
                                        <div>
                                            <span className="font-medium">{carpeta.nombre}</span>
                                            {carpeta.descripcion && (
                                                <p className="text-xs text-base-content/60 truncate max-w-xs">{carpeta.descripcion}</p>
                                            )}
                                        </div>
                                    </td>
                                    <td>{carpeta.usuario?.nombre ?? '-'}</td>
                                    <td className="text-center">{carpeta.archivos_count}</td>
                                    <td className="text-center">{carpeta.externos_count}</td>
                                    <td className="text-right">{formatSize(carpeta.archivos_sum_size)}</td>
                                    <td>
                                        <div className="flex justify-center gap-1">
                                            <Link href={`/admin/drive/carpetas/${carpeta.id}`} className="btn btn-ghost btn-xs">
                                                <Eye className="size-4" />
                                            </Link>
                                            <Link href={`/admin/drive/carpetas/${carpeta.id}/edit`} className="btn btn-ghost btn-xs">
                                                <Edit className="size-4" />
                                            </Link>
                                            <button className="btn btn-ghost btn-xs text-error" onClick={() => handleDelete(carpeta)}>
                                                <Trash2 className="size-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {carpetas.last_page > 1 && (
                    <div className="flex justify-center mt-4 join">
                        {carpetas.prev_page_url && (
                            <button className="join-item btn btn-sm" onClick={() => router.get(carpetas.prev_page_url!)}>«</button>
                        )}
                        <button className="join-item btn btn-sm btn-disabled">
                            {carpetas.current_page} / {carpetas.last_page}
                        </button>
                        {carpetas.next_page_url && (
                            <button className="join-item btn btn-sm" onClick={() => router.get(carpetas.next_page_url!)}>»</button>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
