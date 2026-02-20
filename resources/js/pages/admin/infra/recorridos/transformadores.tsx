import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { InfraTurno, InfraTransformador } from '@/types/models';
import { Head, Link } from '@inertiajs/react';

type Props = {
    data: InfraTransformador | null;
    fecha: string;
    turno: InfraTurno | null;
};

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

function Campo({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <span className="text-sm text-base-content/60">{label}</span>
            <p className="font-medium">{children}</p>
        </div>
    );
}

export default function TransformadoresShow({ data, fecha, turno }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Infraestructura', href: '/admin/infra/recorridos' },
        { title: 'Recorridos', href: '/admin/infra/recorridos' },
        { title: `Transformadores${turno ? ` - ${turno.nombre}` : ''}`, href: '#' },
    ];
    if (!data) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Transformadores" />
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

    const lineas = [
        { nombre: 'Linea A', actual: data.linea_A, max: data.linea_A_max, fecha_max: data.date_A },
        { nombre: 'Linea B', actual: data.linea_B, max: data.linea_B_max, fecha_max: data.date_B },
        { nombre: 'Linea C', actual: data.linea_C, max: data.linea_C_max, fecha_max: data.date_C },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Transformadores" />

            <div className="p-6 space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Transformadores - {fecha}{turno ? ` (${turno.nombre})` : ''}</h1>
                    <Link href={`/admin/infra/recorridos?fecha=${fecha}`} className="btn btn-sm btn-outline">
                        Volver
                    </Link>
                </div>

                {/* Lineas */}
                <div className="card bg-base-100 shadow-sm border border-base-300">
                    <div className="card-body">
                        <h2 className="card-title text-lg">Lineas</h2>
                        <div className="overflow-x-auto">
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Linea</th>
                                        <th>kW/h Actual</th>
                                        <th>kW/h Max</th>
                                        <th>Fecha Max</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {lineas.map((l) => (
                                        <tr key={l.nombre}>
                                            <td className="font-medium">{l.nombre}</td>
                                            <td>
                                                <Valor valor={l.actual} unidad="kW/h" />
                                            </td>
                                            <td>
                                                <Valor valor={l.max} unidad="kW/h" />
                                            </td>
                                            <td>{l.fecha_max ?? <span className="text-base-content/40">No hay datos</span>}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {/* Totales */}
                <div className="card bg-base-100 shadow-sm border border-base-300">
                    <div className="card-body">
                        <h2 className="card-title text-lg">Totales</h2>
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            <Campo label="Total 1">
                                <Valor valor={data.total_1} />
                            </Campo>
                            <Campo label="Total 5">
                                <Valor valor={data.total_5} />
                            </Campo>
                            <Campo label="Solar (Lectura 5y5)">
                                <Valor valor={data.lectura_5y5} />
                            </Campo>
                        </div>
                    </div>
                </div>

                {/* Lecturas */}
                <div className="card bg-base-100 shadow-sm border border-base-300">
                    <div className="card-body">
                        <h2 className="card-title text-lg">Lecturas</h2>
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <Campo label="Lectura 301">
                                <Valor valor={data.lectura_301} />
                            </Campo>
                            <Campo label="Lectura 302">
                                <Valor valor={data.lectura_302} />
                            </Campo>
                            <Campo label="Lectura 303">
                                <Valor valor={data.lectura_303} />
                            </Campo>
                            <Campo label="Lectura 310">
                                <Valor valor={data.lectura_310} />
                            </Campo>
                        </div>
                    </div>
                </div>

                {/* Observaciones */}
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
