import { DataTable, type Column } from '@/components/data-table';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra, ObraEstatus, PaginatedData } from '@/types/models';
import { OBRA_ESTATUS_LABELS } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangleIcon, DownloadIcon, FactoryIcon, Loader2Icon, ShieldAlertIcon } from 'lucide-react';
import { useState, type FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/presupuestos' },
    { title: 'Presupuestos', href: '/admin/costos/presupuestos' },
];

const ESTATUS_COLORS: Record<ObraEstatus, string> = {
    abierta: 'badge-success',
    cerrada: 'badge-ghost',
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
        { key: 'no', label: 'No.', sortable: true },
        { key: 'descripcion', label: 'Descripcion', sortable: true },
        {
            key: 'estatus',
            label: 'Estatus',
            sortable: true,
            render: (o) => (
                <span className={`badge badge-sm ${ESTATUS_COLORS[o.estatus]}`}>
                    {OBRA_ESTATUS_LABELS[o.estatus]}
                </span>
            ),
        },
        {
            key: 'obra_rubros_count',
            label: 'Centros de Costos',
            sortable: true,
            render: (o) => o.obra_rubros_count,
        },
        {
            key: 'obra_rubros_sum_presupuestado',
            label: 'Presupuestado',
            sortable: true,
            render: (o) => <span className="font-mono text-sm">{fmt(o.obra_rubros_sum_presupuestado ?? 0)}</span>,
        },
        {
            key: 'obra_rubros_sum_acumulado',
            label: 'Acumulado',
            sortable: true,
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

type StatsPlanta = {
    total_rubros: number;
    sobregiros: number;
    criticos: number;
    total_presupuestado: number;
    total_acumulado: number;
};

type Props = {
    obras: PaginatedData<ObraWithSums>;
    planta: ObraWithSums | null;
    statsPlanta: StatsPlanta | null;
    filters: { search?: string };
    stats: Stats;
    sortBy?: string;
    sortDir?: 'asc' | 'desc';
};

function DefinirPlantaDialog({ onClose }: { onClose: () => void }) {
    const { data, setData, post, processing, errors } = useForm({
        descripcion: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/costos/presupuestos/planta');
    };

    return (
        <dialog className="modal modal-open">
            <div className="modal-box max-w-md">
                <h3 className="mb-4 text-lg font-medium">Definir proyecto de planta</h3>
                <p className="mb-4 text-sm text-base-content/60">
                    El proyecto de planta lleva el presupuesto del gasto operativo de la planta, separado de las obras.
                    Solo los centros de costos con ámbito Planta se presupuestan aquí.
                </p>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormField label="Nombre" htmlFor="planta_descripcion" error={errors.descripcion} required>
                        <Input
                            id="planta_descripcion"
                            value={data.descripcion}
                            onChange={(e) => setData('descripcion', e.target.value)}
                            placeholder="Ej: Gasto Operativo Planta"
                        />
                    </FormField>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Loader2Icon className="size-4 animate-spin" />}
                            Crear
                        </Button>
                    </div>
                </form>
            </div>
            <div className="modal-backdrop" onClick={onClose} />
        </dialog>
    );
}

function PlantaSection({ planta, statsPlanta }: { planta: ObraWithSums | null; statsPlanta: StatsPlanta | null }) {
    const [showDialog, setShowDialog] = useState(false);

    if (!planta) {
        return (
            <div className="mb-4 flex items-center justify-between rounded-lg border border-dashed border-base-300 p-4">
                <div className="flex items-center gap-3">
                    <FactoryIcon className="size-5 text-base-content/40" />
                    <div>
                        <div className="font-medium">Proyecto de planta</div>
                        <div className="text-xs text-base-content/60">
                            Aún no está definido. Crea el proyecto para presupuestar los centros de costos de planta.
                        </div>
                    </div>
                </div>
                <Button variant="outline" onClick={() => setShowDialog(true)}>
                    Definir proyecto de planta
                </Button>
                {showDialog && <DefinirPlantaDialog onClose={() => setShowDialog(false)} />}
            </div>
        );
    }

    const presup = statsPlanta?.total_presupuestado ?? 0;
    const acum = statsPlanta?.total_acumulado ?? 0;
    const disponible = presup - acum;

    return (
        <Link
            href={`/admin/costos/presupuestos/${planta.id}/edit`}
            className="mb-4 flex items-center justify-between rounded-lg border border-base-300 p-4 transition-colors hover:bg-base-200/50"
        >
            <div className="flex items-center gap-3">
                <FactoryIcon className="size-5 text-info" />
                <div>
                    <div className="flex items-center gap-2 font-medium">
                        {planta.descripcion}
                        <span className="badge badge-info badge-sm">Planta</span>
                    </div>
                    <div className="text-xs text-base-content/60">
                        {planta.obra_rubros_count} centros de costos
                        {(statsPlanta?.sobregiros ?? 0) > 0 && (
                            <span className="text-error font-medium"> · {statsPlanta?.sobregiros} en sobregiro</span>
                        )}
                        {(statsPlanta?.criticos ?? 0) > 0 && (
                            <span className="text-warning font-medium"> · {statsPlanta?.criticos} críticos</span>
                        )}
                    </div>
                </div>
            </div>
            <div className="flex gap-6 text-right">
                <div>
                    <div className="text-xs text-base-content/60">Presupuestado</div>
                    <div className="font-mono text-sm font-semibold">{fmt(presup)}</div>
                </div>
                <div>
                    <div className="text-xs text-base-content/60">Acumulado</div>
                    <div className="font-mono text-sm font-semibold">{fmt(acum)}</div>
                </div>
                <div>
                    <div className="text-xs text-base-content/60">Disponible</div>
                    <div className={`font-mono text-sm font-semibold ${disponible < 0 ? 'text-error' : ''}`}>{fmt(disponible)}</div>
                </div>
            </div>
        </Link>
    );
}

export default function PresupuestosIndex({ obras, planta, statsPlanta, filters, stats, sortBy, sortDir }: Props) {
    const columns = makeColumns(stats.umbral_alerta);
    const totalDisponible = stats.total_presupuestado - stats.total_acumulado;
    const pctGlobal = stats.total_presupuestado > 0
        ? (stats.total_acumulado / stats.total_presupuestado) * 100
        : 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Presupuestos" />

            <div className="p-6">
                {/* Proyecto de planta */}
                <PlantaSection planta={planta} statsPlanta={statsPlanta} />

                {/* Stats panel (solo obras) */}
                <div className="mb-4 grid grid-cols-2 md:grid-cols-5 gap-3">
                    <div className="rounded-lg border border-base-300 p-3">
                        <div className="text-xs text-base-content/60">Total presupuestado (obras)</div>
                        <div className="font-semibold text-sm">{fmt(stats.total_presupuestado)}</div>
                    </div>
                    <div className="rounded-lg border border-base-300 p-3">
                        <div className="text-xs text-base-content/60">Total acumulado (obras)</div>
                        <div className="font-semibold text-sm">{fmt(stats.total_acumulado)}</div>
                    </div>
                    <div className={`rounded-lg border border-base-300 p-3 ${totalDisponible < 0 ? 'bg-error/10' : ''}`}>
                        <div className="text-xs text-base-content/60">Disponible global</div>
                        <div className={`font-semibold text-sm ${totalDisponible < 0 ? 'text-error' : ''}`}>{fmt(totalDisponible)}</div>
                        <div className="text-[10px] text-base-content/50 mt-0.5">{pctGlobal.toFixed(1)}% consumido</div>
                    </div>
                    <div className={`rounded-lg border ${stats.criticos > 0 ? 'border-warning bg-warning/10' : 'border-base-300'} p-3`}>
                        <div className="flex items-center gap-1 text-xs text-base-content/60">
                            <AlertTriangleIcon className="size-3" /> Centros de costos críticos
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
                    sortBy={sortBy}
                    sortDir={sortDir}
                />
            </div>
        </AppLayout>
    );
}
