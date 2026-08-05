import { ButtonLink } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { ProdProceso } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/destajos' },
    { title: 'Procesos', href: '/admin/prod/procesos' },
];

type Props = {
    procesos: ProdProceso[];
};

export default function ProcesosIndex({ procesos }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Procesos" />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Procesos</h1>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Trabajo que se paga como destajo. Cada pieza se paga una vez por proceso, con la tarifa que
                            le fije su grupo de precios.
                        </p>
                    </div>
                    <ButtonLink href="/admin/prod/procesos/create" variant="primary">
                        <PlusIcon className="size-4" />
                        Nuevo proceso
                    </ButtonLink>
                </div>

                <div className="rounded-box border-base-300 overflow-hidden border">
                    <table className="table">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Proceso</th>
                                <th>Eventos del export de planta</th>
                                <th className="text-right">Producción capturada</th>
                                <th className="text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            {procesos.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="text-base-content/50 py-6 text-center">
                                        No hay procesos registrados
                                    </td>
                                </tr>
                            ) : (
                                procesos.map((p) => (
                                    <tr
                                        key={p.id}
                                        className="hover cursor-pointer"
                                        onClick={() => router.visit(`/admin/prod/procesos/${p.id}/edit`)}
                                    >
                                        <td className="font-medium">{p.nombre}</td>
                                        <td>
                                            {(p.eventos ?? []).length === 0 ? (
                                                <span className="text-base-content/50 text-sm">
                                                    Sin eventos — sólo captura manual
                                                </span>
                                            ) : (
                                                <div className="flex flex-wrap gap-1">
                                                    {p.eventos?.map((e) => (
                                                        <span
                                                            key={e.id}
                                                            className="badge badge-sm badge-ghost font-mono"
                                                            title={e.descripcion ?? undefined}
                                                        >
                                                            {e.evento}
                                                        </span>
                                                    ))}
                                                </div>
                                            )}
                                        </td>
                                        <td className="text-right font-mono">{p.registros_count ?? 0}</td>
                                        <td className="text-center">
                                            <span className={`badge badge-sm ${p.activo ? 'badge-success' : 'badge-ghost'}`}>
                                                {p.activo ? 'Activo' : 'Inactivo'}
                                            </span>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <p className="text-base-content/60 mt-4 text-sm">
                    Un número de evento sólo puede pertenecer a un proceso: si el mismo número pagara dos, un mismo
                    movimiento del export se cargaría a los dos y la pieza se pagaría dos veces.
                </p>
            </div>
        </AppLayout>
    );
}
