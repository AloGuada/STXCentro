import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Edit, Plus, Trash2 } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Drive', href: '/admin/drive' },
    { title: 'Usuarios Externos', href: '/admin/drive/externos' },
];

type Externo = {
    id: number;
    nombre: string;
    email: string;
    empresa: string | null;
    activo: boolean;
    ultimo_acceso: string | null;
    carpetas_count: number;
};

type Props = {
    externos: {
        data: Externo[];
        current_page: number;
        last_page: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
};

export default function DriveExternosIndex({ externos }: Props) {
    function handleDelete(externo: Externo) {
        if (confirm(`¿Eliminar al usuario externo "${externo.nombre}"?`)) {
            router.delete(`/admin/drive/externos/${externo.id}`);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Drive - Usuarios Externos" />

            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl font-semibold">Usuarios Externos</h1>
                    <Link href="/admin/drive/externos/create" className="btn btn-primary">
                        <Plus className="size-4" />
                        Nuevo Externo
                    </Link>
                </div>

                <div className="overflow-x-auto">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Empresa</th>
                                <th className="text-center">Carpetas</th>
                                <th className="text-center">Estado</th>
                                <th>Último Acceso</th>
                                <th className="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {externos.data.map((ext) => (
                                <tr key={ext.id} className="hover">
                                    <td className="font-medium">{ext.nombre}</td>
                                    <td>{ext.email}</td>
                                    <td>{ext.empresa ?? '-'}</td>
                                    <td className="text-center">{ext.carpetas_count}</td>
                                    <td className="text-center">
                                        <span className={`badge ${ext.activo ? 'badge-success' : 'badge-error'}`}>
                                            {ext.activo ? 'Activo' : 'Inactivo'}
                                        </span>
                                    </td>
                                    <td>
                                        {ext.ultimo_acceso
                                            ? new Date(ext.ultimo_acceso).toLocaleDateString('es-MX')
                                            : 'Nunca'}
                                    </td>
                                    <td>
                                        <div className="flex justify-center gap-1">
                                            <Link href={`/admin/drive/externos/${ext.id}/edit`} className="btn btn-ghost btn-xs">
                                                <Edit className="size-4" />
                                            </Link>
                                            <button className="btn btn-ghost btn-xs text-error" onClick={() => handleDelete(ext)}>
                                                <Trash2 className="size-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {externos.last_page > 1 && (
                    <div className="flex justify-center mt-4 join">
                        {externos.prev_page_url && (
                            <button className="join-item btn btn-sm" onClick={() => router.get(externos.prev_page_url!)}>«</button>
                        )}
                        <button className="join-item btn btn-sm btn-disabled">
                            {externos.current_page} / {externos.last_page}
                        </button>
                        {externos.next_page_url && (
                            <button className="join-item btn btn-sm" onClick={() => router.get(externos.next_page_url!)}>»</button>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
