import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { InfraBomba, InfraCompresor, InfraPtar, InfraTanque, InfraTransformador } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, CheckCircle2, Circle } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Infraestructura', href: '/admin/infra/recorridos' },
    { title: 'Recorridos', href: '/admin/infra/recorridos' },
];

type Props = {
    fecha: string;
    compresores: InfraCompresor | null;
    bombas: InfraBomba | null;
    transformador: InfraTransformador | null;
    tanques: InfraTanque | null;
    ptar: InfraPtar | null;
};

const sistemas = [
    { key: 'compresores', label: 'Compresores' },
    { key: 'bombas', label: 'Bombas' },
    { key: 'transformadores', label: 'Transformadores' },
    { key: 'tanques', label: 'Tanques de Gas' },
    { key: 'ptar', label: 'PTAR' },
] as const;

export default function RecorridosIndex({ fecha, compresores, bombas, transformador, tanques, ptar }: Props) {
    const dataMap: Record<string, unknown> = {
        compresores,
        bombas,
        transformadores: transformador,
        tanques,
        ptar,
    };

    const completados = sistemas.filter((s) => dataMap[s.key] !== null).length;

    const cambiarFecha = (dias: number) => {
        const d = new Date(fecha + 'T12:00:00');
        d.setDate(d.getDate() + dias);
        router.get('/admin/infra/recorridos', { fecha: d.toISOString().split('T')[0] });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Recorridos" />

            <div className="p-6">
                <h1 className="mb-6 text-2xl font-semibold">Recorridos de Infraestructura</h1>

                {/* Date navigator */}
                <div className="mb-6 flex items-center gap-4">
                    <button className="btn btn-sm btn-ghost" onClick={() => cambiarFecha(-1)}>
                        <ChevronLeft className="size-4" />
                    </button>
                    <input
                        type="date"
                        className="input input-bordered input-sm"
                        value={fecha}
                        onChange={(e) => router.get('/admin/infra/recorridos', { fecha: e.target.value })}
                    />
                    <button className="btn btn-sm btn-ghost" onClick={() => cambiarFecha(1)}>
                        <ChevronRight className="size-4" />
                    </button>
                    <span className="badge badge-info">
                        {completados}/{sistemas.length} completados
                    </span>
                </div>

                {/* Progress bar */}
                <div className="mb-6">
                    <progress className="progress progress-success w-full" value={completados} max={sistemas.length} />
                </div>

                {/* System cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {sistemas.map((sistema) => {
                        const tiene = dataMap[sistema.key] !== null;
                        return (
                            <div key={sistema.key} className={`card bg-base-100 shadow-sm border ${tiene ? 'border-success/30' : 'border-base-300'}`}>
                                <div className="card-body">
                                    <div className="flex items-center justify-between">
                                        <h2 className="card-title text-lg">{sistema.label}</h2>
                                        {tiene ? (
                                            <CheckCircle2 className="size-5 text-success" />
                                        ) : (
                                            <Circle className="size-5 text-base-content/30" />
                                        )}
                                    </div>
                                    <div className="card-actions mt-4 justify-end">
                                        {tiene ? (
                                            <Link
                                                href={`/admin/infra/recorridos/show?sistema=${sistema.key}&fecha=${fecha}`}
                                                className="btn btn-sm btn-outline"
                                            >
                                                Ver
                                            </Link>
                                        ) : (
                                            <Link
                                                href={`/admin/infra/recorridos/create?sistema=${sistema.key}&fecha=${fecha}`}
                                                className="btn btn-sm btn-primary"
                                            >
                                                Registrar
                                            </Link>
                                        )}
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </AppLayout>
    );
}
