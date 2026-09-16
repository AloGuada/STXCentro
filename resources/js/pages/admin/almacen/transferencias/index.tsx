import { DataTable, type Column } from '@/components/data-table';
import { SearchSelect } from '@/components/ui/search-select';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { etiquetaDeAlmacen } from '@/lib/alm/almacenes';
import type { BreadcrumbItem } from '@/types';
import type { AlmAlmacenOpcion, AlmTransferenciaEstatus, PaginatedData } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import { ArrowRightIcon, TruckIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Transferencias', href: '/admin/almacen/transferencias' },
];

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

type TransferenciaFila = {
    id: number;
    folio: string | null;
    fecha_envio: string | null;
    fecha_recepcion: string | null;
    origen: string | null;
    destino: string | null;
    estatus: AlmTransferenciaEstatus;
    estatus_etiqueta: string;
    pedido_folio: string | null;
    envio: string | null;
    recibio: string | null;
    faltante_responsable: string | null;
    renglones: number;
    cancelada: boolean;
    resumen: { enviado: number; recibido: number; faltante: number; renglones_con_faltante: number };
};

type Props = {
    transferencias: PaginatedData<TransferenciaFila>;
    filters: {
        almacen_id?: string;
        estatus?: string;
        desde?: string;
        hasta?: string;
        search?: string;
        ver_canceladas?: boolean;
    };
    almacenes: AlmAlmacenOpcion[];
};

const columns: Column<TransferenciaFila>[] = [
    {
        key: 'folio',
        label: 'Folio',
        render: (t) => (
            <span className={`font-mono font-medium ${t.cancelada ? 'line-through opacity-60' : ''}`}>{t.folio}</span>
        ),
    },
    {
        key: 'origen',
        label: 'Ruta',
        render: (t) => (
            <span className="inline-flex items-center gap-1 font-mono text-sm">
                {t.origen}
                <ArrowRightIcon className="text-base-content/40 size-3" />
                {t.destino}
            </span>
        ),
    },
    { key: 'fecha_envio', label: 'Salió', className: 'font-mono text-xs' },
    {
        key: 'fecha_recepcion',
        label: 'Llegó',
        className: 'font-mono text-xs',
        render: (t) =>
            t.fecha_recepcion ?? (
                <span className="text-warning inline-flex items-center gap-1 text-xs">
                    <TruckIcon className="size-3" />
                    Va en camino
                </span>
            ),
    },
    {
        key: 'resumen',
        label: 'Enviado / recibido',
        className: 'text-right',
        render: (t) => (
            <span className="font-mono text-sm">
                {numero(t.resumen.enviado)}
                {t.estatus === 'recibida' && ` / ${numero(t.resumen.recibido)}`}
                {t.resumen.faltante > 0 && (
                    <span className="text-error block text-xs">
                        faltaron {numero(t.resumen.faltante)} en {t.resumen.renglones_con_faltante} renglón(es)
                    </span>
                )}
            </span>
        ),
    },
    {
        key: 'pedido_folio',
        label: 'Surte',
        render: (t) =>
            t.pedido_folio ? <span className="font-mono text-xs">{t.pedido_folio}</span> : <span className="text-base-content/40">—</span>,
    },
    {
        key: 'estatus',
        label: 'Estado',
        render: (t) => {
            if (t.cancelada) {
                return <span className="badge badge-sm badge-ghost">Cancelada</span>;
            }

            // «Recibida con faltante» no es un estatus aparte: es una recepción
            // cerrada en la que alguien tiene algo que explicar.
            if (t.estatus === 'recibida' && t.resumen.faltante > 0) {
                return (
                    <span className="badge badge-sm badge-error" title={`Responde ${t.faltante_responsable}`}>
                        Con faltante
                    </span>
                );
            }

            return (
                <span className={`badge badge-sm ${t.estatus === 'recibida' ? 'badge-success' : 'badge-warning'}`}>
                    {t.estatus_etiqueta}
                </span>
            );
        },
    },
];

export default function TransferenciasIndex({ transferencias, filters, almacenes }: Props) {
    const filtrar = (cambio: Record<string, string | undefined>) =>
        router.get(
            '/admin/almacen/transferencias',
            { ...filters, ...cambio, page: undefined },
            { preserveState: true },
        );

    const enCamino = transferencias.data.filter((t) => t.estatus === 'en_transito' && !t.cancelada).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Transferencias" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Transferencias</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Material que se mueve entre almacenes. Un folio con dos firmas: el origen despacha y el destino
                        confirma qué bajó del camión.
                    </p>
                </div>

                {enCamino > 0 && (
                    <div className="alert alert-warning mb-4">
                        <TruckIcon className="size-4" />
                        <span>
                            {enCamino} transferencia(s) van en el camino. Ese material salió de su almacén y todavía no
                            es existencia del destino.
                        </span>
                    </div>
                )}

                <DataTable
                    columns={columns}
                    data={transferencias}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por folio..."
                    createHref="/admin/almacen/transferencias/create"
                    createLabel="Nuevo envío"
                    emptyMessage="No hay transferencias con esos filtros."
                    getRowHref={(t) => `/admin/almacen/transferencias/${t.id}`}
                >
                    <div className="w-52">
                        <SearchSelect
                            value={filters.almacen_id ?? ''}
                            onValueChange={(v) => filtrar({ almacen_id: v || undefined })}
                            placeholder="Cualquier almacén"
                            options={[
                                { value: '', label: 'Cualquier almacén' },
                                ...almacenes.map((a) => ({ value: String(a.id), label: etiquetaDeAlmacen(a) })),
                            ]}
                        />
                    </div>

                    <div className="w-40">
                        <Select
                            value={filters.estatus ?? ''}
                            onValueChange={(v) => filtrar({ estatus: v || undefined })}
                            placeholder="Todos los estados"
                        >
                            <SelectItem value="en_transito">En tránsito</SelectItem>
                            <SelectItem value="recibida">Recibida</SelectItem>
                        </Select>
                    </div>

                    <label className="flex cursor-pointer items-center gap-2">
                        <input
                            type="checkbox"
                            className="checkbox checkbox-sm"
                            checked={Boolean(filters.ver_canceladas)}
                            onChange={(e) => filtrar({ ver_canceladas: e.target.checked ? '1' : undefined })}
                        />
                        <span className="text-sm">Ver canceladas</span>
                    </label>
                </DataTable>
            </div>
        </AppLayout>
    );
}
