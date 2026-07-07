import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosSolicitudPago, PaginatedData } from '@/types/models';
import { SOLICITUD_PAGO_ESTATUS_LABELS } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import { EyeIcon, FileDown, FileCheckIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/solicitudes-pago' },
    { title: 'Solicitudes Pago', href: '/admin/costos/solicitudes-pago' },
];

const ESTATUS_BADGE: Record<string, string> = {
    borrador: 'bg-base-200 text-base-content',
    pendiente_firma: 'bg-amber-100 text-amber-800',
    aprobada: 'bg-emerald-100 text-emerald-800',
    pagada: 'bg-sky-100 text-sky-800',
    cancelada: 'bg-red-100 text-red-800',
};

const fmtDate = (date: string | null) =>
    date ? new Date(date).toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '-';

const columns: Column<CostosSolicitudPago>[] = [
    {
        key: 'folio',
        label: 'Folio',
        sortable: true,
        render: (row) => (
            <div>
                <span className="font-mono text-xs font-medium">{row.folio}</span>
                <div className="mt-0.5 text-[11px] text-base-content/50">{fmtDate(row.created_at)}</div>
            </div>
        ),
    },
    {
        key: 'solicitante',
        label: 'Solicitante',
        sortable: true,
        render: (row) => (
            <div>
                <div className="text-sm">{row.solicitante?.name ?? '-'}</div>
                <div className="mt-0.5 text-[11px] text-base-content/50">{row.departamento?.descripcion ?? ''}</div>
            </div>
        ),
    },
    {
        key: 'proveedor',
        label: 'Proveedor',
        sortable: true,
        render: (row) =>
            row.proveedor ? (
                <div>
                    <div className="text-sm">{row.proveedor.razon_social}</div>
                    <div className="mt-0.5 text-[11px] text-base-content/50">RFC: {row.proveedor.rfc}</div>
                </div>
            ) : (
                <span className="text-base-content/40">-</span>
            ),
    },
    {
        key: 'concepto',
        label: 'Concepto',
        sortable: true,
        render: (row) => <span className="text-xs text-base-content/60">{row.concepto}</span>,
    },
    {
        key: 'monto_total',
        label: 'Total',
        sortable: true,
        render: (row) => (
            <span className="font-medium">${Number(row.monto_total).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</span>
        ),
    },
    {
        key: 'estatus',
        label: 'Estatus',
        sortable: true,
        render: (row) => (
            <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-medium ${ESTATUS_BADGE[row.estatus] ?? ''}`}>
                {SOLICITUD_PAGO_ESTATUS_LABELS[row.estatus]}
            </span>
        ),
    },
    {
        key: 'fecha_pago_solicitada',
        label: 'Fecha Pago',
        sortable: true,
        render: (row) => <span className="text-xs text-base-content/60">{fmtDate(row.fecha_pago_solicitada)}</span>,
    },
    {
        key: 'pdf',
        label: 'PDF',
        render: (row) => (
            <div className="flex gap-1.5">
                {row.estatus !== 'borrador' && (
                    <a
                        href={`/admin/costos/solicitudes-pago/${row.id}/pdf`}
                        onClick={(e) => e.stopPropagation()}
                        className="flex size-7 items-center justify-center rounded-lg border border-base-300 text-base-content/60 transition-colors hover:bg-base-200"
                        title="Descargar PDF generado"
                    >
                        <FileDown className="size-3.5" />
                    </a>
                )}
                {row.media && (
                    <a
                        href={`/storage/${row.media.path}`}
                        onClick={(e) => e.stopPropagation()}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex size-7 items-center justify-center rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-600 transition-colors hover:bg-emerald-100"
                        title="Ver PDF firmado"
                    >
                        <FileCheckIcon className="size-3.5" />
                    </a>
                )}
            </div>
        ),
    },
    {
        key: 'acciones',
        label: '',
        render: (row) => (
            <a
                href={row.estatus === 'borrador' ? `/admin/costos/solicitudes-pago/${row.id}/edit` : `/admin/costos/solicitudes-pago/${row.id}`}
                onClick={(e) => e.stopPropagation()}
                className="flex size-7 items-center justify-center rounded-lg border border-base-300 text-base-content/60 transition-colors hover:bg-base-200"
                title="Ver detalle"
            >
                <EyeIcon className="size-3.5" />
            </a>
        ),
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
    sortBy?: string;
    sortDir?: 'asc' | 'desc';
};

export default function SolicitudesPagoIndex({ solicitudes, filters, sortBy, sortDir }: Props) {
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

                    <a
                        href={`/admin/costos/solicitudes-pago/reporte-pdf?${new URLSearchParams(
                            Object.fromEntries(
                                Object.entries(filters).filter(([, v]) => v),
                            ),
                        ).toString()}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="btn btn-sm btn-outline ml-auto"
                    >
                        <FileDown className="size-4" />
                        Reporte PDF
                    </a>
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
                    sortBy={sortBy}
                    sortDir={sortDir}
                />
            </div>
        </AppLayout>
    );
}
