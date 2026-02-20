import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { InfraPtar, InfraTurno } from '@/types/models';
import { Head, Link } from '@inertiajs/react';

type Props = {
    data: InfraPtar | null;
    fecha: string;
    turno: InfraTurno | null;
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

export default function PtarShow({ data, fecha, turno }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Infraestructura', href: '/admin/infra/recorridos' },
        { title: 'Recorridos', href: '/admin/infra/recorridos' },
        { title: `PTAR${turno ? ` - ${turno.nombre}` : ''}`, href: '#' },
    ];
    if (!data) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="PTAR" />
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
            <Head title="PTAR" />

            <div className="p-6 space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">PTAR - {fecha}{turno ? ` (${turno.nombre})` : ''}</h1>
                    <Link href={`/admin/infra/recorridos?fecha=${fecha}`} className="btn btn-sm btn-outline">
                        Volver
                    </Link>
                </div>

                <div className="card bg-base-100 shadow-sm border border-base-300">
                    <div className="card-body">
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <Campo label="Soplador">
                                <StatusBadge activo={data.soplador_activa} />
                            </Campo>
                            <Campo label="Bomba">
                                <StatusBadge activo={data.bomba_activa} />
                            </Campo>
                            <Campo label="Trampa Sólidos">
                                <span className={`badge ${data.trampa_solida ? 'badge-success' : 'badge-error'}`}>{data.trampa_solida ? 'Limpio' : 'Sucio'}</span>
                            </Campo>
                            <Campo label="Nivel Cloro">
                                <Valor valor={data.nivel_cloro} unidad="%" />
                            </Campo>
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
