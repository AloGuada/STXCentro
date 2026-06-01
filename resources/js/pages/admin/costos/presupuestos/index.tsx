import { DataTable, type Column } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra, ObraEstatus, PaginatedData } from '@/types/models';
import { OBRA_ESTATUS_LABELS } from '@/types/models';
import { Head } from '@inertiajs/react';
import { AlertTriangleIcon, DownloadIcon, ShieldAlertIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/presupuestos' },
    { title: 'Presupuestos', href: '/admin/costos/presupuestos' },
];

const ESTATUS_COLORS: Record<ObraEstatus, string> = {
    planificacion: 'badge-info',
    en_proceso: 'badge-warning',
    activa: 'badge-success',
    suspendida: 'badge-error',
    completada: 'badge-ghost',
    cancelada: 'badge-error badge-outline',
};

const fmt = (v: number) => `$${Number(v).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

type ObraWithSums = Obra & {
    obra_rubros_sum_presupuestado: number | null;
    obra_rubros_sum_acumulado: number | null;
    obra_rubros_count: number;
};

function avanceBarColor(pct: number, umbral: number): string {
    if (pct > 100) return 'bg-error';
    if (pct >= umbral) return 'bg-warning';
    return 'bg-success';
}

function makeColumns(umbral: number): Column<ObraWithSums>[] {
    return [
        { key: 'no', label: 'No.' },
        { key: 'descripcion', label: 'Descripcion' },
        {
            key: 'estatus',
            label: 'Estatus',
            render: (o) => (
                <span className={`badge badge-sm ${ESTATUS_COLORS[o.estatus]}`}>
                    {OBRA_ESTATUS_LABELS[o.estatus]}
                </span>
            ),
        },
        {
            key: 'obra_rubros_count',
            label: 'Rubros',
            render: (o) => o.obra_rubros_count,
        },
        {
            key: 'obra_rubros_sum_presupuestado',
            label: 'Presupuestado',
            render: (o) => <span className="font-mono text-sm">{fmt(o.obra_rubros_sum_presupuestado ?? 0)}</span>,
        },
        {
            key: 'obra_rubros_sum_acumulado',
            label: 'Acumulado',
            render: (o) => <span className="font-mono text-sm">{fmt(o.obra_rubros_sum_acumulado ?? 0)}</span>,
        },
        {
            key: 'avance',
            label: 'Avance',
            render: (o) => {
                const presup = o.obra_rubros_sum_presupuestado ?? 0;
                const acum = o.obra_rubros_sum_acumulado ?? 0;
                const pct = presup > 0 ? (acum / presup) * 100 : 0;
                const color = avanceBarColor(pct, umbral);
                const widthPct = Math.min(100, pct);

                return (
                    <div className="w-32">
                        <div className="flex items-center justify-between text-[10px] text-base-content/60 mb-0.5">
                            <span>{pct.toFixed(0)}%</span>
                            {pct > 100 && (
                                <span className="text-error font-bold">+{(pct - 100).toFixed(0)}%</span>
                            )}
                        </div>
                        <div className="h-1.5 w-full overflow-hidden rounded-full bg-base-200">
                            <div className={`h-full ${color}`} style={{ width: `${widthPct}%` }} />
                        </div>
                    </div>
                );
            },
        },
        {
            key: 'disponible',
            label: 'Disponible',
            render: (o) => {
                const disponible = (o.obra_rubros_sum_presupuestado ?? 0) - (o.obra_rubros_sum_acumulado ?? 0);
                return <span className={`font-mono text-sm ${disponible < 0 ? 'text-error font-bold' : ''}`}>{fmt(disponible)}</span>;
            },
        },
    ];
}

type Stats = {
    total_rubros: number;
    sobregiros: number;
    criticos: number;
    total_presupuestado: number;
    total_acumulado: number;
    umbral_alerta: number;
    bloquear_sobregiro: boolean;
};

type Props = {
    obras: PaginatedData<ObraWithSums>;
    filters: { search?: string };
    stats: Stats;
};

export default function PresupuestosIndex({ obras, filters, stats }: Props) {
    const columns = makeColumns(stats.umbral_alerta);
    const totalDisponible = stats.total_presupuestado - stats.total_acumulado;
    const pctGlobal = stats.total_presupuestado > 0
        ? (stats.total_acumulado / stats.total_presupuestado) * 100
        : 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Presupuestos" />

            <div className="p-6">
                {/* Stats panel */}
                <div className="mb-4 grid grid-cols-2 md:grid-cols-5 gap-3">
                    <div className="rounded-lg border border-base-300 p-3">
                        <div className="text-xs text-base-content/60">Total presupuestado</div>
                        <div className="font-semibold text-sm">{fmt(stats.total_presupuestado)}</div>
                    </div>
                    <div className="rounded-lg border border-base-300 p-3">
                        <div className="text-xs text-base-content/60">Total acumulado</div>
                        <div className="font-semibold text-sm">{fmt(stats.total_acumulado)}</div>
                    </div>
                    <div className={`rounded-lg border border-base-300 p-3 ${totalDisponible < 0 ? 'bg-error/10' : ''}`}>
                        <div className="text-xs text-base-content/60">Disponible global</div>
                        <div className={`font-semibold text-sm ${totalDisponible < 0 ? 'text-error' : ''}`}>{fmt(totalDisponible)}</div>
                        <div className="text-[10px] text-base-content/50 mt-0.5">{pctGlobal.toFixed(1)}% consumido</div>
                    </div>
                    <div className={`rounded-lg border ${stats.criticos > 0 ? 'border-warning bg-warning/10' : 'border-base-300'} p-3`}>
                        <div className="flex items-center gap-1 text-xs text-base-content/60">
                            <AlertTriangleIcon className="size-3" /> Rubros críticos
                        </div>
                        <div className="font-semibold text-sm">{stats.criticos} <span className="text-xs text-base-content/50">/ {stats.total_rubros}</span></div>
                        <div className="text-[10px] text-base-content/50 mt-0.5">≥ {stats.umbral_alerta}% consumido</div>
                    </div>
                    <div className={`rounded-lg border ${stats.sobregiros > 0 ? 'border-error bg-error/10' : 'border-base-300'} p-3`}>
                        <div className="flex items-center gap-1 text-xs text-base-content/60">
                            <ShieldAlertIcon className="size-3" /> Sobregiros
                        </div>
                        <div className={`font-semibold text-sm ${stats.sobregiros > 0 ? 'text-error' : ''}`}>{stats.sobregiros}</div>
                        <div className="text-[10px] text-base-content/50 mt-0.5">
                            {stats.bloquear_sobregiro ? 'Bloqueo activo' : 'Solo alertas'}
                        </div>
                    </div>
                </div>

                <div className="mb-4 flex justify-end">
                    <Button asChild variant="outline">
                        <a href="/admin/costos/presupuestos/reporte-pdf" target="_blank" rel="noopener noreferrer">
                            <DownloadIcon className="size-4" />
                            Descargar Reporte
                        </a>
                    </Button>
                </div>
                <DataTable
                    columns={columns}
                    data={obras}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por numero o descripcion..."
                    emptyMessage="No hay obras registradas"
                    getRowHref={(o) => `/admin/costos/presupuestos/${o.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
