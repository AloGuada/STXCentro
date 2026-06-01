import { DataTable, type Column } from '@/components/data-table';
import PortalLayout from '@/layouts/portal/portal-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosPago, PaginatedData } from '@/types/models';
import { PAGO_ESTATUS_COLORS, PAGO_ESTATUS_LABELS } from '@/types/models';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/portal' },
    { title: 'Pagos', href: '/portal/pagos' },
];

const columns: Column<CostosPago>[] = [
    { key: 'folio', label: 'Folio' },
    {
        key: 'monto_pago',
        label: 'Monto',
        render: (row) => `$${Number(row.monto_pago).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`,
    },
    {
        key: 'tipo_pago',
        label: 'Tipo',
        render: (row) => (row.tipo_pago === 'contado' ? 'Contado' : 'Credito'),
    },
    {
        key: 'fecha_pago_realizada',
        label: 'Fecha Pago',
        render: (row) => {
            if (row.fecha_pago_realizada) return new Date(row.fecha_pago_realizada).toLocaleDateString();
            if (row.fecha_pago_programada) return <span className="text-base-content/60">{new Date(row.fecha_pago_programada).toLocaleDateString()} (programado)</span>;
            return '-';
        },
    },
    {
        key: 'estatus',
        label: 'Estatus',
        render: (row) => (
            <span className={`badge ${PAGO_ESTATUS_COLORS[row.estatus]}`}>{PAGO_ESTATUS_LABELS[row.estatus]}</span>
        ),
    },
];

const estatusOptions = [
    { value: '', label: 'Todos' },
    { value: 'pendiente', label: 'Pendiente' },
    { value: 'parcial', label: 'Parcial' },
    { value: 'pagado', label: 'Pagado' },
];

type Props = {
    pagos: PaginatedData<CostosPago>;
    filters: { search?: string; estatus?: string };
};

export default function PortalPagosIndex({ pagos, filters }: Props) {
    const handleEstatusChange = (estatus: string) => {
        router.get('/portal/pagos', { ...filters, estatus: estatus || undefined }, { preserveState: true });
    };

    return (
        <PortalLayout breadcrumbs={breadcrumbs}>
            <Head title="Mis Pagos" />

            <div className="p-6">
                <h1 className="text-2xl font-semibold mb-4">Mis Pagos</h1>

                <div className="mb-4 flex items-center gap-4">
                    <select
                        className="select select-bordered select-sm"
                        value={filters.estatus ?? ''}
                        onChange={(e) => handleEstatusChange(e.target.value)}
                    >
                        {estatusOptions.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                </div>

                <DataTable
                    columns={columns}
                    data={pagos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por folio..."
                    emptyMessage="No hay pagos"
                    getRowHref={(row) => `/portal/pagos/${row.id}`}
                />
            </div>
        </PortalLayout>
    );
}
