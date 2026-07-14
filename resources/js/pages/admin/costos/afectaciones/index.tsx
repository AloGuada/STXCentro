import { Head, router } from '@inertiajs/react';
import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosAfectacionPresupuestal, PaginatedData } from '@/types/models';
import { AFECTACION_ESTATUS_COLORS, AFECTACION_ESTATUS_LABELS } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/afectaciones' },
    { title: 'Afectaciones', href: '/admin/costos/afectaciones' },
];

const fmtFecha = (date: string) =>
    new Date(date).toLocaleDateString('es-MX', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });

const columns: Column<CostosAfectacionPresupuestal>[] = [
    {
        key: 'folio',
        label: 'Folio',
        sortable: true,
        render: (row) => (
            <div>
                <span className="font-mono text-xs font-medium">{row.folio}</span>
                <div className="mt-0.5 text-[11px] text-base-content/50">{fmtFecha(row.fecha)}</div>
            </div>
        ),
    },
    {
        key: 'descripcion',
        label: 'Razón',
        render: (row) => (
            <span className="line-clamp-1 block max-w-xs text-sm text-base-content/70">
                {row.descripcion}
            </span>
        ),
    },
    {
        key: 'tipo_origen',
        label: 'Tipo',
        sortable: true,
        render: (row) => (
            <span className="text-xs whitespace-nowrap text-base-content/60 capitalize">
                {row.tipo_origen.replace(/_/g, ' ')}
            </span>
        ),
    },
    {
        key: 'monto_total',
        label: 'Total',
        sortable: true,
        render: (row) => (
            <span className="font-medium whitespace-nowrap">
                ${Number(row.monto_total).toLocaleString('es-MX', { minimumFractionDigits: 2 })}
            </span>
        ),
    },
    {
        key: 'estatus',
        label: 'Estatus',
        sortable: true,
        render: (row) => (
            <span className={`badge badge-sm ${AFECTACION_ESTATUS_COLORS[row.estatus]}`}>
                {AFECTACION_ESTATUS_LABELS[row.estatus]}
            </span>
        ),
    },
];

const estatusOptions: { value: string; label: string }[] = [
    { value: '', label: 'Todos' },
    { value: 'borrador', label: 'Borrador' },
    { value: 'pendiente_firma', label: 'Pendiente Firma' },
    { value: 'aprobada', label: 'Aprobada' },
    { value: 'cancelada', label: 'Cancelada' },
];

type Props = {
    afectaciones: PaginatedData<CostosAfectacionPresupuestal>;
    filters: { search?: string; estatus?: string };
    sortBy?: string;
    sortDir?: 'asc' | 'desc';
};

export default function AfectacionesIndex({ afectaciones, filters, sortBy, sortDir }: Props) {
    const handleEstatusChange = (estatus: string) => {
        router.get('/admin/costos/afectaciones', { ...filters, estatus: estatus || undefined }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Afectaciones Presupuestales" />

            <div className="p-6">
                <div className="mb-4 flex items-center gap-4">
                    <select
                        className="select select-bordered select-sm"
                        value={filters.estatus ?? ''}
                        onChange={(e) => handleEstatusChange(e.target.value)}
                    >
                        {estatusOptions.map((opt) => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                    </select>
                </div>

                <DataTable
                    columns={columns}
                    data={afectaciones}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por folio o descripción..."
                    createHref="/admin/costos/afectaciones/create"
                    createLabel="Nueva Afectación"
                    emptyMessage="No hay afectaciones presupuestales"
                    getRowHref={(row) => `/admin/costos/afectaciones/${row.id}`}
                    sortBy={sortBy}
                    sortDir={sortDir}
                />
            </div>
        </AppLayout>
    );
}
