import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra, ObraEstatus, PaginatedData } from '@/types/models';
import { OBRA_ESTATUS_LABELS } from '@/types/models';
import { Head } from '@inertiajs/react';

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

const columns: Column<ObraWithSums>[] = [
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
        key: 'presupuesto_total',
        label: 'Pres. Total',
        render: (o) => <span className="font-mono text-sm">{fmt(o.presupuesto_total)}</span>,
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
        key: 'disponible',
        label: 'Disponible',
        render: (o) => {
            const disponible = (o.obra_rubros_sum_presupuestado ?? 0) - (o.obra_rubros_sum_acumulado ?? 0);
            return <span className={`font-mono text-sm ${disponible < 0 ? 'text-error' : ''}`}>{fmt(disponible)}</span>;
        },
    },
];

type Props = {
    obras: PaginatedData<ObraWithSums>;
    filters: { search?: string };
};

export default function PresupuestosIndex({ obras, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Presupuestos" />

            <div className="p-6">
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
