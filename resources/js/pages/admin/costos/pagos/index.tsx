import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosPago, CostosPagoEstatus, PaginatedData } from '@/types/models';
import { PAGO_ESTATUS_COLORS, PAGO_ESTATUS_LABELS, PAGO_TIPO_PAGO_LABELS, TIPO_MONEDA_LABELS } from '@/types/models';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/pagos' },
    { title: 'Pagos', href: '/admin/costos/pagos' },
];

const columns: Column<CostosPago>[] = [
    { key: 'folio', label: 'Folio' },
    {
        key: 'pagable',
        label: 'Origen',
        render: (row) => {
            if (row.pagable && 'folio' in row.pagable) {
                return row.pagable.folio;
            }
            return '-';
        },
    },
    {
        key: 'monto_pago',
        label: 'Monto',
        render: (row) => `$${Number(row.monto_pago).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`,
    },
    {
        key: 'moneda',
        label: 'Moneda',
        render: (row) => TIPO_MONEDA_LABELS[row.moneda as keyof typeof TIPO_MONEDA_LABELS] ?? row.moneda.toUpperCase(),
    },
    {
        key: 'tipo_pago',
        label: 'Tipo',
        render: (row) => PAGO_TIPO_PAGO_LABELS[row.tipo_pago],
    },
    {
        key: 'estatus',
        label: 'Estatus',
        render: (row) => (
            <span className={`badge ${PAGO_ESTATUS_COLORS[row.estatus]}`}>
                {PAGO_ESTATUS_LABELS[row.estatus]}
            </span>
        ),
    },
    {
        key: 'fecha_pago_programada',
        label: 'F. Programada',
        render: (row) => row.fecha_pago_programada ? new Date(row.fecha_pago_programada).toLocaleDateString() : '-',
    },
    {
        key: 'fecha_pago_realizada',
        label: 'F. Realizada',
        render: (row) => row.fecha_pago_realizada ? new Date(row.fecha_pago_realizada).toLocaleDateString() : '-',
    },
];

const estatusOptions = [
    { value: '', label: 'Todos' },
    { value: 'pendiente', label: 'Pendiente' },
    { value: 'parcial', label: 'Parcial' },
    { value: 'pagado', label: 'Pagado' },
];

const tipoPagoOptions = [
    { value: '', label: 'Todos' },
    { value: 'contado', label: 'Contado' },
    { value: 'credito', label: 'Crédito' },
];

type Props = {
    pagos: PaginatedData<CostosPago>;
    filters: { search?: string; estatus?: string; tipo_pago?: string };
};

export default function PagosIndex({ pagos, filters }: Props) {
    const handleFilterChange = (key: string, value: string) => {
        router.get('/admin/costos/pagos', { ...filters, [key]: value || undefined }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pagos" />

            <div className="p-6">
                <div className="mb-4 flex items-center gap-4">
                    <select
                        className="select select-bordered select-sm"
                        value={filters.estatus ?? ''}
                        onChange={(e) => handleFilterChange('estatus', e.target.value)}
                    >
                        {estatusOptions.map((opt) => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                    </select>
                    <select
                        className="select select-bordered select-sm"
                        value={filters.tipo_pago ?? ''}
                        onChange={(e) => handleFilterChange('tipo_pago', e.target.value)}
                    >
                        {tipoPagoOptions.map((opt) => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                    </select>
                </div>

                <DataTable
                    columns={columns}
                    data={pagos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por folio..."
                    emptyMessage="No hay pagos registrados"
                    getRowHref={(row) => `/admin/costos/pagos/${row.id}`}
                />
            </div>
        </AppLayout>
    );
}
