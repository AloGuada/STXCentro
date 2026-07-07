import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosPresupuestoEstatus, PaginatedData, PresupuestableTipo, PresupuestoRow } from '@/types/models';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/obras-activas' },
    { title: 'Obras activas', href: '/admin/costos/obras-activas' },
];

const TIPO_LABELS: Record<PresupuestableTipo, string> = {
    proyecto: 'Proyecto',
    obra: 'Obra',
    partida: 'Partida',
};

const TIPO_COLORS: Record<PresupuestableTipo, string> = {
    proyecto: 'badge-primary',
    obra: 'badge-neutral',
    partida: 'badge-accent',
};

const fmt = (v: number) => `$${Number(v).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

function avanceBarColor(pct: number, umbral: number): string {
    if (pct > 100) return 'bg-error';
    if (pct >= umbral) return 'bg-warning';
    return 'bg-success';
}

function makeColumns(umbral: number): Column<PresupuestoRow>[] {
    return [
        {
            key: 'tipo',
            label: 'Tipo',
            render: (p) => (
                <span className={`badge badge-sm ${p.es_planta ? 'badge-info' : TIPO_COLORS[p.tipo]}`}>
                    {p.es_planta ? 'Planta' : TIPO_LABELS[p.tipo]}
                </span>
            ),
        },
        {
            key: 'nombre_interno',
            label: 'Nombre',
            sortable: true,
            render: (p) => (
                <div>
                    <div className="font-medium">{p.nombre}</div>
                    {p.nombre_interno && p.no && (
                        <div className="text-[10px] text-base-content/50">{p.no}</div>
                    )}
                </div>
            ),
        },
        {
            key: 'descripcion',
            label: 'Descripción',
            sortable: true,
            render: (p) => (
                <span className="text-sm text-base-content/70">{p.descripcion ?? '—'}</span>
            ),
        },
        {
            key: 'rubros_count',
            label: 'Centros de Costos',
            sortable: true,
            render: (p) => p.rubros_count,
        },
        {
            key: 'rubros_sum_presupuestado',
            label: 'Presupuestado',
            sortable: true,
            render: (p) => <span className="font-mono text-sm">{fmt(p.sum_presupuestado)}</span>,
        },
        {
            key: 'rubros_sum_acumulado',
            label: 'Acumulado',
            sortable: true,
            render: (p) => <span className="font-mono text-sm">{fmt(p.sum_acumulado)}</span>,
        },
        {
            key: 'avance',
            label: 'Avance',
            render: (p) => {
                const pct = p.sum_presupuestado > 0 ? (p.sum_acumulado / p.sum_presupuestado) * 100 : 0;
                const color = avanceBarColor(pct, umbral);
                const widthPct = Math.min(100, pct);

                return (
                    <div className="w-32">
                        <div className="mb-0.5 flex items-center justify-between text-[10px] text-base-content/60">
                            <span>{pct.toFixed(0)}%</span>
                            {pct > 100 && <span className="font-bold text-error">+{(pct - 100).toFixed(0)}%</span>}
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
            render: (p) => {
                const disponible = p.sum_presupuestado - p.sum_acumulado;
                return <span className={`font-mono text-sm ${disponible < 0 ? 'font-bold text-error' : ''}`}>{fmt(disponible)}</span>;
            },
        },
    ];
}

type Props = {
    presupuestos: PaginatedData<PresupuestoRow>;
    estatus: CostosPresupuestoEstatus;
    conteos: { activo: number; cerrado: number };
    umbral: number;
    filters: { search?: string; estatus?: string };
    sortBy?: string;
    sortDir?: 'asc' | 'desc';
};

export default function ObrasActivasIndex({ presupuestos, estatus, conteos, umbral, filters, sortBy, sortDir }: Props) {
    const columns = makeColumns(umbral);

    const cambiarTab = (nuevo: CostosPresupuestoEstatus) => {
        if (nuevo === estatus) return;
        router.get('/admin/costos/obras-activas', { search: filters.search || undefined, estatus: nuevo }, { preserveState: true, preserveScroll: true });
    };

    const tabClass = (activo: boolean) => `tab ${activo ? 'tab-active font-medium' : ''}`;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Obras activas" />

            <div className="p-6">
                <h1 className="mb-4 text-2xl font-semibold">Obras activas</h1>

                <div role="tablist" className="tabs tabs-bordered mb-4">
                    <button type="button" role="tab" className={tabClass(estatus === 'activo')} onClick={() => cambiarTab('activo')}>
                        Activas ({conteos.activo})
                    </button>
                    <button type="button" role="tab" className={tabClass(estatus === 'cerrado')} onClick={() => cambiarTab('cerrado')}>
                        Cerradas ({conteos.cerrado})
                    </button>
                </div>

                <DataTable
                    columns={columns}
                    data={presupuestos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por nombre, numero o descripcion..."
                    emptyMessage={estatus === 'activo' ? 'No hay obras activas' : 'No hay obras cerradas'}
                    getRowHref={(p) => `/admin/costos/presupuestos/${p.id}/edit`}
                    sortBy={sortBy}
                    sortDir={sortDir}
                />
            </div>
        </AppLayout>
    );
}
