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

const columns: Column<PresupuestoRow>[] = [
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
        render: (p) => <div className="font-medium">{p.nombre}</div>,
    },
    {
        key: 'op',
        label: 'OP',
        render: (p) => <span className="font-mono text-sm">{p.op ?? '—'}</span>,
    },
    {
        key: 'descripcion',
        label: 'Descripción',
        sortable: true,
        render: (p) => (
            <span className="text-sm text-base-content/70">{p.descripcion ?? '—'}</span>
        ),
    },
];

type Props = {
    presupuestos: PaginatedData<PresupuestoRow>;
    estatus: CostosPresupuestoEstatus;
    conteos: { activo: number; cerrado: number };
    filters: { search?: string; estatus?: string };
    sortBy?: string;
    sortDir?: 'asc' | 'desc';
};

export default function ObrasActivasIndex({ presupuestos, estatus, conteos, filters, sortBy, sortDir }: Props) {
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
