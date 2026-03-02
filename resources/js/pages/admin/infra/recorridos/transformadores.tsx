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
        {
            nombre: 'Linea A',
            energia: data.linea_A,
            registro: data.registro_a,
            potencia: data.linea_A_max,
            fecha_hora: data.date_A,
            voltaje: data.voltaje_a,
            codEnergia: '11',
            codRegistro: '212',
            codPotencia: '41',
        },
        {
            nombre: 'Linea B',
            energia: data.linea_B,
            registro: data.registro_b,
            potencia: data.linea_B_max,
            fecha_hora: data.date_B,
            voltaje: data.voltaje_b,
            codEnergia: '12',
            codRegistro: '234',
            codPotencia: '42',
        },
        {
            nombre: 'Linea C',
            energia: data.linea_C,
            registro: data.registro_c,
            potencia: data.linea_C_max,
            fecha_hora: data.date_C,
            voltaje: data.voltaje_c,
            codEnergia: '13',
            codRegistro: '256',
            codPotencia: '43',
        },
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

                {/* Voltajes */}
                <div className="card bg-base-100 shadow-sm border border-base-300">
                    <div className="card-body">
                        <h2 className="card-title text-lg">Voltajes</h2>
                        <div className="grid grid-cols-3 gap-4">
                            {lineas.map((l) => {
                                const voltajeOk = l.voltaje !== null && l.voltaje >= 120 && l.voltaje <= 130;
                                return (
                                    <Campo key={l.nombre} label={`Voltaje ${l.nombre} (V)`}>
                                        {l.voltaje !== null ? (
                                            <span className={voltajeOk ? 'text-success' : 'text-error font-semibold'}>
                                                {l.voltaje} V
                                            </span>
                                        ) : (
                                            <span className="text-base-content/40">No hay datos</span>
                                        )}
                                    </Campo>
                                );
                            })}
                        </div>
                    </div>
                </div>

                {/* Lineas */}
                <div className="card bg-base-100 shadow-sm border border-base-300">
                    <div className="card-body">
                        <h2 className="card-title text-lg">Indicador CFE</h2>
                        <div className="overflow-x-auto">
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Linea</th>
                                        <th>Energía Consumida kWh</th>
                                        <th>Energía Registro kWh</th>
                                        <th>Potencia Instantánea kW</th>
                                        <th>Fecha/Hora</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {lineas.map((l) => (
                                        <tr key={l.nombre}>
                                            <td className="font-medium">{l.nombre}</td>
                                            <td>
                                                <Valor valor={l.energia} unidad={`kWh (${l.codEnergia})`} />
                                            </td>
                                            <td>
                                                <Valor valor={l.registro} unidad={`kWh (${l.codRegistro})`} />
                                            </td>
                                            <td>
                                                <Valor valor={l.potencia} unidad={`kW (${l.codPotencia})`} />
                                            </td>
                                            <td>{l.fecha_hora ?? <span className="text-base-content/40">No hay datos</span>}</td>
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
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <Campo label="Consumo Total kWh (4)">
                                <Valor valor={data.total_1} unidad="kWh" />
                            </Campo>
                            <Campo label="Consumo Red kWh (5)">
                                <Valor valor={data.total_5} unidad="kWh" />
                            </Campo>
                            <Campo label="Energía Generada kWh (190)">
                                <Valor valor={data.lectura_5y5} unidad="kWh" />
                            </Campo>
                            <Campo label="Tarifa (8)">
                                <Valor valor={data.tarifa} />
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
