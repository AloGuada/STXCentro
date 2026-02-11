import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { InfraTanque } from '@/types/models';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Infraestructura', href: '/admin/infra/recorridos' },
    { title: 'Recorridos', href: '/admin/infra/recorridos' },
    { title: 'Tanques de Gas', href: '#' },
];

type Props = {
    data: InfraTanque | null;
    fecha: string;
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

export default function TanquesShow({ data, fecha }: Props) {
    if (!data) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Tanques de Gas" />
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

    const tanques = [
        {
            nombre: 'Oxigeno',
            pa_sistema: data.pa_sistema_oxigeno,
            presion_sistema: data.presion_sistema_oxigeno,
            presion_tanque: data.presion_tanque_oxigeno,
            litros: data.lt_tanque_oxigeno,
            kilogramos: data.kg_tanque_oxigeno,
            numero_tanque: null as number | null,
        },
        {
            nombre: 'Argon',
            pa_sistema: data.pa_sistema_argon,
            presion_sistema: data.presion_sistema_argon,
            presion_tanque: data.presion_tanque_argon,
            litros: data.lt_tanque_argon,
            kilogramos: data.kg_tanque_argon,
            numero_tanque: null as number | null,
        },
        {
            nombre: 'CO2',
            pa_sistema: data.pa_sistema_co2,
            presion_sistema: data.presion_sistema_co2,
            presion_tanque: data.presion_tanque_co2,
            litros: data.lt_tanque_co2,
            kilogramos: data.kg_tanque_co2,
            numero_tanque: null as number | null,
        },
        {
            nombre: 'Gas LP',
            pa_sistema: data.pa_sistema_lp,
            presion_sistema: data.presion_sistema_lp,
            presion_tanque: data.presion_tanque_lp,
            litros: data.lt_tanque_lp,
            kilogramos: data.kg_tanque_lp,
            numero_tanque: data.numero_tanque_lp,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tanques de Gas" />

            <div className="p-6 space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Tanques de Gas - {fecha}</h1>
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
                                        <th>Tanque</th>
                                        <th>PA Sistema</th>
                                        <th>Presion Sistema</th>
                                        <th>Presion Tanque</th>
                                        <th>Litros</th>
                                        <th>Kilogramos</th>
                                        <th>No. Tanque</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {tanques.map((t) => (
                                        <tr key={t.nombre}>
                                            <td className="font-medium">{t.nombre}</td>
                                            <td>
                                                <Valor valor={t.pa_sistema} />
                                            </td>
                                            <td>
                                                <Valor valor={t.presion_sistema} />
                                            </td>
                                            <td>
                                                <Valor valor={t.presion_tanque} unidad="PSI" />
                                            </td>
                                            <td>
                                                <Valor valor={t.litros} unidad="L" />
                                            </td>
                                            <td>
                                                <Valor valor={t.kilogramos} unidad="kg" />
                                            </td>
                                            <td>
                                                {t.numero_tanque !== null ? (
                                                    t.numero_tanque
                                                ) : (
                                                    <span className="text-base-content/40">-</span>
                                                )}
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
