import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosSolicitudPago, CostosSolicitudPagoEstatus, PaginatedData } from '@/types/models';
import { SOLICITUD_PAGO_ESTATUS_COLORS, SOLICITUD_PAGO_ESTATUS_LABELS } from '@/types/models';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/solicitudes-pago' },
    { title: 'Solicitudes Pago', href: '/admin/costos/solicitudes-pago' },
];

const columns: Column<CostosSolicitudPago>[] = [
    { key: 'folio', label: 'Folio' },
    {
        key: 'departamento',
        label: 'Departamento',
        render: (row) => row.departamento?.descripcion ?? '-',
    },
    {
        key: 'proveedor',
        label: 'Proveedor',
        render: (row) => row.proveedor?.razon_social ?? '-',
    },
    { key: 'concepto', label: 'Concepto' },
    {
        key: 'monto_total',
        label: 'Total',
        render: (row) => `$${Number(row.monto_total).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`,
    },
    {
        key: 'estatus',
        label: 'Estatus',
        render: (row) => (
            <span className={`badge ${SOLICITUD_PAGO_ESTATUS_COLORS[row.estatus]}`}>
                {SOLICITUD_PAGO_ESTATUS_LABELS[row.estatus]}
            </span>
        ),
    },
    {
        key: 'created_at',
        label: 'Fecha',
        render: (row) => new Date(row.created_at).toLocaleDateString(),
    },
];

const estatusOptions: { value: string; label: string }[] = [
    { value: '', label: 'Todos' },
    { value: 'borrador', label: 'Borrador' },
    { value: 'pendiente_firma', label: 'Pendiente Firma' },
    { value: 'aprobada', label: 'Aprobada' },
    { value: 'pagada', label: 'Pagada' },
    { value: 'cancelada', label: 'Cancelada' },
];

type Props = {
    solicitudes: PaginatedData<CostosSolicitudPago>;
    filters: { search?: string; estatus?: string };
};

export default function SolicitudesPagoIndex({ solicitudes, filters }: Props) {
    const handleEstatusChange = (estatus: string) => {
        router.get('/admin/costos/solicitudes-pago', { ...filters, estatus: estatus || undefined }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Solicitudes de Pago" />

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
                    data={solicitudes}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por folio o concepto..."
                    createHref="/admin/costos/solicitudes-pago/create"
                    createLabel="Nueva Solicitud"
                    emptyMessage="No hay solicitudes de pago"
                    getRowHref={(row) => row.estatus === 'borrador'
                        ? `/admin/costos/solicitudes-pago/${row.id}/edit`
                        : `/admin/costos/solicitudes-pago/${row.id}`
                    }
                />
            </div>
        </AppLayout>
    );
}
