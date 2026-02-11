import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { InfraCompresor } from '@/types/models';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Infraestructura', href: '/admin/infra/recorridos' },
    { title: 'Recorridos', href: '/admin/infra/recorridos' },
    { title: 'Compresores', href: '#' },
];

type Props = {
    data: InfraCompresor | null;
    fecha: string;
};

function StatusBadge({ activo }: { activo: boolean }) {
    return <span className={`badge ${activo ? 'badge-success' : 'badge-error'}`}>{activo ? 'Activa' : 'Inactiva'}</span>;
}

function Valor({ valor, unidad }: { valor: number | null; unidad?: string }) {
    if (valor === null) {
        return <span className="text-base-content/40">No hay datos</span>;
    }
    return (
        <span>
            {valor} {unidad && <span className="text-base-content/60 text-sm">{unidad}</span>}
        </span>
    );
}

export default function CompresoresShow({ data, fecha }: Props) {
    if (!data) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Compresores" />
                <div className="p-6">
                    <div className="card bg-base-100 shadow-sm border border-base-300">
                        <div className="card-body items-center text-center">
                            <p className="text-base-content/60">No hay datos registrados para esta fecha.</p>
                            <div className="card-actions mt-4">
                                <Link href={`/admin/infra/recorridos?fecha=${fecha}`} className="btn btn-sm btn-outline">
                                    Volver
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </AppLayout>
        );
    }

    const compresores = [
        { nombre: 'Compresor 1', status: data.compresor_1_status, presion: data.compresor_1_presion_aire, kwhr: data.compresor_1_kwhr, trabajo: data.compresor_1_tiempo_trabajo, marcha: data.compresor_1_tiempo_marcha },
        { nombre: 'Compresor 2', status: data.compresor_2_status, presion: data.compresor_2_presion_aire, kwhr: data.compresor_2_kwhr, trabajo: data.compresor_2_tiempo_trabajo, marcha: data.compresor_2_tiempo_marcha },
        { nombre: 'Compresor 3', status: data.compresor_3_status, presion: data.compresor_3_presion_aire, kwhr: data.compresor_3_kwhr, trabajo: data.compresor_3_tiempo_trabajo, marcha: data.compresor_3_tiempo_marcha },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Compresores" />

            <div className="p-6 space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Compresores - {fecha}</h1>
                    <Link href={`/admin/infra/recorridos?fecha=${fecha}`} className="btn btn-sm btn-outline">
                        Volver
                    </Link>
                </div>

                <div className="card bg-base-100 shadow-sm border border-base-300">
                    <div className="card-body">
                        <div className="overflow-x-auto">
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Compresor</th>
                                        <th>Status</th>
                                        <th>Presion Aire</th>
                                        <th>kW/h</th>
                                        <th>Tiempo Trabajo</th>
                                        <th>Tiempo Marcha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {compresores.map((c) => (
                                        <tr key={c.nombre}>
                                            <td className="font-medium">{c.nombre}</td>
                                            <td>
                                                <StatusBadge activo={c.status} />
                                            </td>
                                            <td>
                                                <Valor valor={c.presion} unidad="PSI" />
                                            </td>
                                            <td>
                                                <Valor valor={c.kwhr} unidad="kW/h" />
                                            </td>
                                            <td>
                                                <Valor valor={c.trabajo} unidad="hrs" />
                                            </td>
                                            <td>
                                                <Valor valor={c.marcha} unidad="hrs" />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {data.observaciones && (
                    <div className="card bg-base-100 shadow-sm border border-base-300">
                        <div className="card-body">
                            <h2 className="card-title text-lg">Observaciones</h2>
                            <p className="whitespace-pre-wrap">{data.observaciones}</p>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
