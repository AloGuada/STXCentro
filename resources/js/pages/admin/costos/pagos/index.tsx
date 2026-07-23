import { formatMoney as fmtMonto } from '@/components/costos/monto';
import { DataTable, type Column } from '@/components/data-table';
import { formatDate } from '@/components/ui/formatted-date';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosPago, CostosPagoEstatus, PaginatedData } from '@/types/models';
import { PAGO_ESTATUS_COLORS, PAGO_ESTATUS_LABELS, PAGO_TIPO_PAGO_LABELS, TIPO_MONEDA_LABELS } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import { FileTextIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/pagos' },
    { title: 'Pagos', href: '/admin/costos/pagos' },
];

const columns: Column<CostosPago>[] = [
    { key: 'folio', label: 'Folio', sortable: true },
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
        key: 'proveedor',
        label: 'Proveedor',
        render: (row) => row.pagable?.proveedor?.nombre_comercial ?? row.pagable?.proveedor?.razon_social ?? '-',
    },
    {
        key: 'monto_pago',
        label: 'Monto',
        sortable: true,
        render: (row) => fmtMonto(row.monto_pago, row.moneda),
    },
    {
        key: 'moneda',
        label: 'Moneda',
        sortable: true,
        render: (row) => TIPO_MONEDA_LABELS[row.moneda as keyof typeof TIPO_MONEDA_LABELS] ?? row.moneda.toUpperCase(),
    },
    {
        key: 'tipo_pago',
        label: 'Tipo',
        sortable: true,
        render: (row) => PAGO_TIPO_PAGO_LABELS[row.tipo_pago],
    },
    {
        key: 'estatus',
        label: 'Estatus',
        sortable: true,
        render: (row) => (
            <span className={`badge ${PAGO_ESTATUS_COLORS[row.estatus]}`}>
                {PAGO_ESTATUS_LABELS[row.estatus]}
            </span>
        ),
    },
    {
        key: 'fecha_pago_programada',
        label: 'F. Programada',
        sortable: true,
        render: (row) => formatDate(row.fecha_pago_programada) ?? '-',
    },
    {
        key: 'fecha_pago_realizada',
        label: 'F. Realizada',
        sortable: true,
        render: (row) => formatDate(row.fecha_pago_realizada) ?? '-',
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
    sortBy?: string;
    sortDir?: 'asc' | 'desc';
};

export default function PagosIndex({ pagos, filters, sortBy, sortDir }: Props) {
    const today = new Date();
    const firstOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    const toDateInput = (d: Date) => d.toISOString().split('T')[0];

    const [fechaInicio, setFechaInicio] = useState(toDateInput(firstOfMonth));
    const [fechaFin, setFechaFin] = useState(toDateInput(today));

    const handleFilterChange = (key: string, value: string) => {
        router.get('/admin/costos/pagos', { ...filters, [key]: value || undefined }, { preserveState: true });
    };

    const handleGenerarReporte = () => {
        if (!fechaInicio || !fechaFin) return;
        const url = `/admin/costos/pagos/reporte?fecha_inicio=${fechaInicio}&fecha_fin=${fechaFin}`;
        window.open(url, '_blank');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pagos" />

            <div className="p-6">
                <div className="mb-4 flex flex-wrap items-center gap-4">
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

                    <div className="ml-auto flex items-end gap-2">
                        <div className="flex flex-col">
                            <label className="text-[10px] text-base-content/60">Desde</label>
                            <input
                                type="date"
                                className="input input-bordered input-sm"
                                value={fechaInicio}
                                onChange={(e) => setFechaInicio(e.target.value)}
                            />
                        </div>
                        <div className="flex flex-col">
                            <label className="text-[10px] text-base-content/60">Hasta</label>
                            <input
                                type="date"
                                className="input input-bordered input-sm"
                                value={fechaFin}
                                onChange={(e) => setFechaFin(e.target.value)}
                            />
                        </div>
                        <button
                            type="button"
                            className="btn btn-sm btn-primary"
                            onClick={handleGenerarReporte}
                            disabled={!fechaInicio || !fechaFin || fechaInicio > fechaFin}
                        >
                            <FileTextIcon className="size-4" />
                            Generar Reporte
                        </button>
                    </div>
                </div>

                <DataTable
                    columns={columns}
                    data={pagos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por folio..."
                    emptyMessage="No hay pagos registrados"
                    getRowHref={(row) => `/admin/costos/pagos/${row.id}`}
                    sortBy={sortBy}
                    sortDir={sortDir}
                />
            </div>
        </AppLayout>
    );
}
