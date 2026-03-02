import InfraChart from '@/components/infra/infra-chart';
import StatusIndicator from '@/components/infra/status-indicator';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    InfraChartBomba,
    InfraChartCompresor,
    InfraChartTanque,
    InfraChartTransformador,
    InfraEstados,
    InfraTurnoData,
} from '@/types/models';
import { Deferred, Link, router } from '@inertiajs/react';
import { Head } from '@inertiajs/react';
import { Check, ChevronLeft, ChevronRight, Eye, Lock, Plus } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Infraestructura', href: '/admin/infra/recorridos' },
    { title: 'Recorridos', href: '/admin/infra/recorridos' },
];

type ActiveSystem = 'compresores' | 'bombas' | 'transformadores' | 'tanques' | 'ptar';

type Props = {
    fecha: string;
    year: number;
    turnoData: InfraTurnoData[];
    estados: InfraEstados;
    chartCompresores?: InfraChartCompresor[];
    chartBombas?: InfraChartBomba[];
    chartTransformadores?: InfraChartTransformador[];
    chartTanques?: InfraChartTanque[];
};

const sistemas: { key: ActiveSystem; label: string; estadosKey: keyof InfraEstados }[] = [
    { key: 'compresores', label: 'Compresores', estadosKey: 'compresores' },
    { key: 'bombas', label: 'Bombas', estadosKey: 'bombas' },
    { key: 'transformadores', label: 'Transformadores', estadosKey: 'transformadores' },
    { key: 'tanques', label: 'Tanques de Gas', estadosKey: 'tanques' },
    { key: 'ptar', label: 'PTAR', estadosKey: 'ptar' },
];

const dataKeyMap: Record<ActiveSystem, keyof Omit<InfraTurnoData, 'turno'>> = {
    compresores: 'compresores',
    bombas: 'bombas',
    transformadores: 'transformador',
    tanques: 'tanques',
    ptar: 'ptar',
};

function ChartSkeleton() {
    return (
        <div className="flex h-[300px] items-center justify-center">
            <div className="flex flex-col items-center gap-3">
                <span className="loading loading-spinner loading-lg" />
                <span className="text-sm text-base-content/50">Cargando gráfica...</span>
            </div>
        </div>
    );
}

export default function RecorridosIndex({
    fecha,
    year,
    turnoData,
    estados,
    chartCompresores,
    chartBombas,
    chartTransformadores,
    chartTanques,
}: Props) {
    const [activeSystem, setActiveSystem] = useState<ActiveSystem>('compresores');

    // Progress: count turno×sistema slots completed vs total
    const totalSlots = turnoData.length * sistemas.length;
    const completados = turnoData.reduce((acc, td) => {
        return acc + sistemas.filter((s) => td[dataKeyMap[s.key]] !== null).length;
    }, 0);

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
                    <button
                        className="btn btn-sm btn-outline btn-primary"
                        onClick={() => router.get('/admin/infra/recorridos', { fecha: new Date().toISOString().split('T')[0] })}
                    >
                        Hoy
                    </button>
                    <span className="badge badge-info">
                        {completados}/{totalSlots} completados
                    </span>
                </div>

                {/* Progress bar */}
                <div className="mb-6">
                    <progress className="progress progress-success w-full" value={completados} max={totalSlots} />
                </div>

                {/* System cards with status indicators */}
                <div className="mb-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    {sistemas.map((sistema) => {
                        const indicadores = estados[sistema.estadosKey];
                        const isActive = activeSystem === sistema.key;
                        const dataKey = dataKeyMap[sistema.key];

                        return (
                            <div
                                key={sistema.key}
                                className={`card bg-base-100 shadow-sm border transition-all cursor-pointer ${
                                    isActive ? 'border-primary ring-2 ring-primary/20' : 'border-base-300'
                                }`}
                                onMouseEnter={() => setActiveSystem(sistema.key)}
                            >
                                <div className="card-body p-4">
                                    <h2 className="card-title text-base">{sistema.label}</h2>

                                    {/* Status indicators */}
                                    <div className="mt-2 flex flex-wrap gap-x-3 gap-y-1">
                                        {indicadores.map((ind) => (
                                            <StatusIndicator key={ind.label} indicador={ind} />
                                        ))}
                                    </div>

                                    {/* Turno buttons */}
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {turnoData.map((td, i) => {
                                            const tiene = td[dataKey] !== null;
                                            const turnoId = td.turno.id;

                                            // Disabled if previous turno for this system isn't filled
                                            const prevTiene = i === 0 || turnoData[i - 1][dataKey] !== null;
                                            const disabled = !tiene && !prevTiene;

                                            const href = tiene
                                                ? `/admin/infra/recorridos/show?sistema=${sistema.key}&fecha=${fecha}${turnoId ? `&turno_id=${turnoId}` : ''}`
                                                : `/admin/infra/recorridos/create?sistema=${sistema.key}&fecha=${fecha}${turnoId ? `&turno_id=${turnoId}` : ''}`;

                                            if (disabled) {
                                                return (
                                                    <span
                                                        key={i}
                                                        className="btn btn-xs btn-disabled gap-1 opacity-40"
                                                    >
                                                        <Lock className="size-3" />
                                                        {td.turno.nombre}
                                                    </span>
                                                );
                                            }

                                            return (
                                                <Link
                                                    key={i}
                                                    href={href}
                                                    className={`btn btn-xs gap-1 ${
                                                        tiene
                                                            ? 'btn-success text-success-content'
                                                            : 'btn-primary btn-outline'
                                                    }`}
                                                >
                                                    {tiene ? <Check className="size-3" /> : <Plus className="size-3" />}
                                                    {td.turno.nombre}
                                                </Link>
                                            );
                                        })}
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Chart section */}
                <div className="card bg-base-100 shadow-sm border border-base-300">
                    <div className="card-body">
                        <div className="flex items-center justify-between mb-2">
                            <h2 className="card-title text-lg">Estadisticas Mensuales</h2>
                            <div className="flex items-center gap-2">
                                <button
                                    className="btn btn-sm btn-ghost"
                                    onClick={() => router.get('/admin/infra/recorridos', { fecha, year: year - 1 }, { preserveState: true, only: ['year', 'chartCompresores', 'chartBombas', 'chartTransformadores', 'chartTanques'] })}
                                >
                                    <ChevronLeft className="size-4" />
                                </button>
                                <span className="font-semibold tabular-nums">{year}</span>
                                <button
                                    className="btn btn-sm btn-ghost"
                                    onClick={() => router.get('/admin/infra/recorridos', { fecha, year: year + 1 }, { preserveState: true, only: ['year', 'chartCompresores', 'chartBombas', 'chartTransformadores', 'chartTanques'] })}
                                    disabled={year >= new Date().getFullYear()}
                                >
                                    <ChevronRight className="size-4" />
                                </button>
                            </div>
                        </div>
                        <Deferred data={['chartCompresores', 'chartBombas', 'chartTransformadores', 'chartTanques']} fallback={<ChartSkeleton />}>
                            <InfraChart
                                activeSystem={activeSystem}
                                chartCompresores={chartCompresores}
                                chartBombas={chartBombas}
                                chartTransformadores={chartTransformadores}
                                chartTanques={chartTanques}
                            />
                        </Deferred>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
