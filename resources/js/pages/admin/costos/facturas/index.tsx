import { formatMoney as fmtMonto } from '@/components/costos/monto';
import { DataTable, type Column } from '@/components/data-table';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosFactura, CostosFacturaEstatus, PaginatedData, Proveedor } from '@/types/models';
import { FACTURA_ESTATUS_COLORS, FACTURA_ESTATUS_LABELS } from '@/types/models';
import { useCan } from '@/hooks/use-can';
import { Head, Link, router } from '@inertiajs/react';
import { BuildingIcon, DownloadIcon, FileTextIcon, PlusIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/facturas' },
    { title: 'Facturas', href: '/admin/costos/facturas' },
];

const columns: Column<CostosFactura>[] = [
    { key: 'folio', label: 'Folio', sortable: true },
    {
        key: 'orden_compra',
        label: 'OC',
        sortable: true,
        render: (row) => row.orden_compra?.folio ?? '-',
    },
    {
        key: 'media_pdf',
        label: 'PDF',
        render: (row) =>
            row.media_pdf ? (
                <a
                    href={`/storage/${row.media_pdf.path}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    onClick={(e) => e.stopPropagation()}
                    className="btn btn-ghost btn-xs"
                    title="Ver factura PDF"
                >
                    <FileTextIcon className="size-4" />
                </a>
            ) : (
                <span className="text-base-content/30">—</span>
            ),
        className: 'w-16 text-center',
    },
    {
        key: 'proveedor',
        label: 'Proveedor',
        sortable: true,
        render: (row) => row.proveedor?.razon_social ?? '-',
    },

    {
        key: 'fecha_factura',
        label: 'Fecha',
        sortable: true,
        render: (row) => row.fecha_factura ? new Date(row.fecha_factura).toLocaleDateString() : '-',
    },
    {
        key: 'total',
        label: 'Total',
        sortable: true,
        render: (row) => fmtMonto(row.total, row.moneda),
    },
    {
        key: 'estatus',
        label: 'Estatus',
        sortable: true,
        render: (row) => (
            <span className={`badge ${FACTURA_ESTATUS_COLORS[row.estatus]}`}>
                {FACTURA_ESTATUS_LABELS[row.estatus]}
            </span>
        ),
    },
];

const estatusOptions = [
    { value: '', label: 'Todos' },
    { value: 'pendiente_aprobacion', label: 'Pendiente Aprobación' },
    { value: 'pendiente_pago', label: 'Pendiente Pago' },
    { value: 'pagada', label: 'Pagada' },
    { value: 'cancelada', label: 'Cancelada' },
];

function getWeekDateRange(year: number, week: number): { start: string; end: string } {
    // ISO week: week 1 contains the first Thursday of the year
    const jan4 = new Date(year, 0, 4);
    const dayOfWeek = jan4.getDay() || 7; // Monday = 1
    const monday = new Date(jan4);
    monday.setDate(jan4.getDate() - dayOfWeek + 1 + (week - 1) * 7);
    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);

    const fmt = (d: Date) => d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
    return { start: fmt(monday), end: fmt(sunday) };
}

function getCurrentWeekNumber(): number {
    const now = new Date();
    const jan4 = new Date(now.getFullYear(), 0, 4);
    const dayOfWeek = jan4.getDay() || 7;
    const firstMonday = new Date(jan4);
    firstMonday.setDate(jan4.getDate() - dayOfWeek + 1);
    const diff = now.getTime() - firstMonday.getTime();
    return Math.ceil(diff / (7 * 24 * 60 * 60 * 1000));
}

type Props = {
    facturas: PaginatedData<CostosFactura>;
    filters: { search?: string; estatus?: string };
    proveedores: Pick<Proveedor, 'id' | 'razon_social'>[];
    sortBy?: string;
    sortDir?: 'asc' | 'desc';
};

export default function FacturasIndex({ facturas, filters, proveedores, sortBy, sortDir }: Props) {
    const { can } = useCan();
    const currentYear = new Date().getFullYear();
    const [selectedYear, setSelectedYear] = useState(currentYear);
    const [selectedWeek, setSelectedWeek] = useState(getCurrentWeekNumber());
    const [provYear, setProvYear] = useState(currentYear);
    const [provWeek, setProvWeek] = useState(getCurrentWeekNumber());
    const [selectedProveedor, setSelectedProveedor] = useState<number | ''>('');

    const handleEstatusChange = (estatus: string) => {
        router.get('/admin/costos/facturas', { ...filters, estatus: estatus || undefined }, { preserveState: true });
    };

    const getWeekOptions = (year: number) =>
        Array.from({ length: 53 }, (_, i) => {
            const week = i + 1;
            const { start, end } = getWeekDateRange(year, week);
            return { value: week, label: `Semana ${week}: ${start} - ${end}` };
        });

    const weekOptions = getWeekOptions(selectedYear);
    const provWeekOptions = getWeekOptions(provYear);

    const downloadUrl = `/admin/costos/facturas/reporte-semanal?anio=${selectedYear}&semana=${selectedWeek}`;
    const downloadProvUrl = `/admin/costos/facturas/reporte-semanal-proveedor?anio=${provYear}&semana=${provWeek}&proveedor_id=${selectedProveedor}`;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Facturas" />

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

                    {can('costos.facturas.crear') && (
                        <Link href="/admin/costos/facturas/create" className="btn btn-primary btn-sm ml-auto">
                            <PlusIcon className="size-4" />
                            Nueva factura
                        </Link>
                    )}

                    <Dialog>
                        <DialogTrigger className={`btn btn-outline btn-sm${can('costos.facturas.crear') ? '' : ' ml-auto'}`}>
                            <DownloadIcon className="size-4" />
                            Reporte Semanal
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Reporte Semanal de Facturas</DialogTitle>
                            </DialogHeader>

                            <div className="space-y-4">
                                <div className="form-control">
                                    <label className="label">
                                        <span className="label-text">Año</span>
                                    </label>
                                    <select
                                        className="select select-bordered w-full"
                                        value={selectedYear}
                                        onChange={(e) => setSelectedYear(Number(e.target.value))}
                                    >
                                        <option value={currentYear}>{currentYear}</option>
                                        <option value={currentYear - 1}>{currentYear - 1}</option>
                                    </select>
                                </div>

                                <div className="form-control">
                                    <label className="label">
                                        <span className="label-text">Semana</span>
                                    </label>
                                    <select
                                        className="select select-bordered w-full"
                                        value={selectedWeek}
                                        onChange={(e) => setSelectedWeek(Number(e.target.value))}
                                    >
                                        {weekOptions.map((opt) => (
                                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <DialogFooter>
                                <DialogClose>Cancelar</DialogClose>
                                <a
                                    href={downloadUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="btn btn-primary"
                                >
                                    <DownloadIcon className="size-4" />
                                    Descargar
                                </a>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    <Dialog>
                        <DialogTrigger className="btn btn-outline btn-sm">
                            <BuildingIcon className="size-4" />
                            Reporte por Proveedor
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Reporte Semanal por Proveedor</DialogTitle>
                            </DialogHeader>

                            <div className="space-y-4">
                                <div className="form-control">
                                    <label className="label">
                                        <span className="label-text">Proveedor</span>
                                    </label>
                                    <select
                                        className="select select-bordered w-full"
                                        value={selectedProveedor}
                                        onChange={(e) => setSelectedProveedor(e.target.value ? Number(e.target.value) : '')}
                                    >
                                        <option value="">Seleccionar proveedor...</option>
                                        {proveedores.map((p) => (
                                            <option key={p.id} value={p.id}>{p.razon_social}</option>
                                        ))}
                                    </select>
                                </div>

                                <div className="form-control">
                                    <label className="label">
                                        <span className="label-text">Año</span>
                                    </label>
                                    <select
                                        className="select select-bordered w-full"
                                        value={provYear}
                                        onChange={(e) => setProvYear(Number(e.target.value))}
                                    >
                                        <option value={currentYear}>{currentYear}</option>
                                        <option value={currentYear - 1}>{currentYear - 1}</option>
                                    </select>
                                </div>

                                <div className="form-control">
                                    <label className="label">
                                        <span className="label-text">Semana</span>
                                    </label>
                                    <select
                                        className="select select-bordered w-full"
                                        value={provWeek}
                                        onChange={(e) => setProvWeek(Number(e.target.value))}
                                    >
                                        {provWeekOptions.map((opt) => (
                                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <DialogFooter>
                                <DialogClose>Cancelar</DialogClose>
                                <a
                                    href={downloadProvUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className={`btn btn-primary ${!selectedProveedor ? 'btn-disabled pointer-events-none' : ''}`}
                                >
                                    <DownloadIcon className="size-4" />
                                    Descargar
                                </a>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>

                <DataTable
                    columns={columns}
                    data={facturas}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por folio o proveedor..."
                    emptyMessage="No hay facturas"
                    getRowHref={(row) => `/admin/costos/facturas/${row.id}`}
                    sortBy={sortBy}
                    sortDir={sortDir}
                />
            </div>
        </AppLayout>
    );
}
