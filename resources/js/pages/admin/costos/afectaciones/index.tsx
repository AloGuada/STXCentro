import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosAfectacionEstatus, CostosAfectacionPresupuestal, PaginatedData } from '@/types/models';
import { AFECTACION_ESTATUS_COLORS, AFECTACION_ESTATUS_LABELS } from '@/types/models';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/afectaciones' },
    { title: 'Afectaciones', href: '/admin/costos/afectaciones' },
];

const columns: Column<CostosAfectacionPresupuestal>[] = [
    { key: 'folio', label: 'Folio', sortable: true },
    {
        key: 'fecha',
        label: 'Fecha',
        sortable: true,
        render: (row) => new Date(row.fecha).toLocaleDateString(),
    },
    {
        key: 'departamento',
        label: 'Departamento',
        sortable: true,
        render: (row) => row.departamento?.descripcion ?? '-',
    },
    {
        key: 'tipo_origen',
        label: 'Tipo Origen',
        sortable: true,
        render: (row) => <span className="capitalize">{row.tipo_origen.replace(/_/g, ' ')}</span>,
    },
    {
        key: 'monto_total',
        label: 'Total',
        sortable: true,
        render: (row) => `$${Number(row.monto_total).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`,
    },
    {
        key: 'estatus',
        label: 'Estatus',
        sortable: true,
        render: (row) => (
            <span className={`badge ${AFECTACION_ESTATUS_COLORS[row.estatus]}`}>
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
                    getRowHref={(row) => row.estatus === 'borrador'
                        ? `/admin/costos/afectaciones/${row.id}/edit`
                        : `/admin/costos/afectaciones/${row.id}`
                    }
                    sortBy={sortBy}
                    sortDir={sortDir}
                />
            </div>
        </AppLayout>
    );
}
