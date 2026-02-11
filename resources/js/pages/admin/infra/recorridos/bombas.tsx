import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { InfraBomba } from '@/types/models';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Infraestructura', href: '/admin/infra/recorridos' },
    { title: 'Recorridos', href: '/admin/infra/recorridos' },
    { title: 'Bombas', href: '#' },
];

type Props = {
    data: InfraBomba | null;
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

function Campo({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <span className="text-sm text-base-content/60">{label}</span>
            <p className="font-medium">{children}</p>
        </div>
    );
}

export default function BombasShow({ data, fecha }: Props) {
    if (!data) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Bombas" />
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bombas" />

            <div className="p-6 space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Bombas - {fecha}</h1>
                    <Link href={`/admin/infra/recorridos?fecha=${fecha}`} className="btn btn-sm btn-outline">
                        Volver
                    </Link>
                </div>

                {/* Bombas de Pozos */}
                <div className="card bg-base-100 shadow-sm border border-base-300">
                    <div className="card-body">
                        <h2 className="card-title text-lg">Bombas de Pozos</h2>
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            <Campo label="Bomba Pozos 1">
                                <StatusBadge activo={data.bomba_posos_1} />
                            </Campo>
                            <Campo label="Bomba Pozos 2">
                                <StatusBadge activo={data.bomba_posos_2} />
                            </Campo>
                        </div>
                    </div>
                </div>

                {/* Bombas de Planta */}
                <div className="card bg-base-100 shadow-sm border border-base-300">
                    <div className="card-body">
                        <h2 className="card-title text-lg">Bombas de Planta</h2>
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            <Campo label="Bomba Planta 1">
                                <StatusBadge activo={data.bomba_planta_1} />
                            </Campo>
                            <Campo label="Bomba Planta 2">
                                <StatusBadge activo={data.bomba_planta_2} />
                            </Campo>
                            <Campo label="Bomba Planta 3">
                                <StatusBadge activo={data.bomba_planta_3} />
                            </Campo>
                        </div>
                    </div>
                </div>

                {/* Niveles */}
                <div className="card bg-base-100 shadow-sm border border-base-300">
                    <div className="card-body">
                        <h2 className="card-title text-lg">Niveles</h2>
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            <Campo label="Salmuera">
                                <Valor valor={data.nivel_salmuera} />
                            </Campo>
                            <Campo label="Tinaco">
                                <Valor valor={data.nivel_tinaco} />
                            </Campo>
                            <Campo label="Cisterna">
                                <Valor valor={data.nivel_sisterna} unidad="%" />
                            </Campo>
                            <Campo label="Hipoclorito">
                                <Valor valor={data.nivel_hipoclorito} />
                            </Campo>
                            <Campo label="Anticongelante">
                                <Valor valor={data.nivel_anticongelante} unidad="%" />
                            </Campo>
                            <Campo label="Presion Tuberia">
                                <Valor valor={data.presion_tuberia} unidad="PSI" />
                            </Campo>
                        </div>
                    </div>
                </div>

                {/* Motor */}
                <div className="card bg-base-100 shadow-sm border border-base-300">
                    <div className="card-body">
                        <h2 className="card-title text-lg">Motor</h2>
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            <Campo label="Aceite del Motor">
                                <Valor valor={data.aceite_del_motor} />
                            </Campo>
                            <Campo label="Tanque Diesel">
                                <Valor valor={data.tanque_diesel} />
                            </Campo>
                            <Campo label="Voltaje Bateria">
                                <Valor valor={data.voltaje_bateria} unidad="V" />
                            </Campo>
                        </div>
                    </div>
                </div>

                {/* Sistema Contra Incendios */}
                <div className="card bg-base-100 shadow-sm border border-base-300">
                    <div className="card-body">
                        <h2 className="card-title text-lg">Sistema Contra Incendios</h2>
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            <Campo label="Bomba Jockey">
                                <StatusBadge activo={data.bomba_jockey} />
                            </Campo>
                            <Campo label="Bomba Electrica">
                                <StatusBadge activo={data.bomba_electrica} />
                            </Campo>
                            <Campo label="Bomba Diesel">
                                <StatusBadge activo={data.bomba_diesel} />
                            </Campo>
                            <Campo label="Presion Tuberia Incendio">
                                <Valor valor={data.presion_tuberia_incendio} unidad="PSI" />
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
