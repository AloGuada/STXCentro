import { DataTable, type Column } from '@/components/data-table';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { etiquetaDeAlmacen } from '@/lib/alm/almacenes';
import type { BreadcrumbItem } from '@/types';
import type { AlmAlmacenOpcion, AlmOpcion, AlmPedidoEstatus, PaginatedData } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import { NetworkIcon, PackageIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Pedidos', href: '/admin/almacen/pedidos' },
];

const CLASE_ESTATUS: Record<AlmPedidoEstatus, string> = {
    borrador: 'badge-ghost',
    pendiente: 'badge-warning',
    aprobado: 'badge-info',
    surtido: 'badge-success',
    cancelado: 'badge-ghost',
    rechazado: 'badge-error',
};

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

type PedidoFila = {
    id: number;
    folio: string | null;
    fecha: string | null;
    fecha_requerida: string | null;
    almacen: string | null;
    departamento: string | null;
    obra: string | null;
    recibe: string | null;
    solicitante: string | null;
    estatus: AlmPedidoEstatus;
    estatus_etiqueta: string;
    renglones: number;
    /** Con obra va por transferencia; sin obra, por salida. */
    se_surte_con: 'salida' | 'transferencia';
    avance: { solicitado: number; surtido: number; renglones_pendientes: number };
};

type Props = {
    pedidos: PaginatedData<PedidoFila>;
    filters: {
        almacen_id?: string;
        obra_id?: string;
        departamento_id?: string;
        estatus?: string;
        destino?: string;
        search?: string;
    };
    estatuses: AlmOpcion[];
    almacenes: AlmAlmacenOpcion[];
    departamentos: { id: number; descripcion: string }[];
    obras: { id: number; no: string; descripcion: string }[];
};

const columns: Column<PedidoFila>[] = [
    { key: 'folio', label: 'Folio', render: (p) => <span className="font-mono font-medium">{p.folio}</span> },
    { key: 'fecha', label: 'Fecha', className: 'font-mono text-xs' },
    {
        key: 'almacen',
        label: 'Le pide a',
        render: (p) => <span className="badge badge-sm badge-ghost font-mono">{p.almacen}</span>,
    },
    { key: 'departamento', label: 'Quién pide' },
    {
        key: 'obra',
        label: 'Para dónde',
        render: (p) =>
            p.obra ?? (
                <span className="text-base-content/60" title="Consumo interno: el material se queda en la planta">
                    Planta
                </span>
            ),
    },
    {
        // De aquí depende con qué documento se surte, y por eso se dice desde el
        // listado: quien va a despachar necesita saberlo antes de abrirlo.
        key: 'se_surte_con',
        label: 'Se surte con',
        render: (p) =>
            p.se_surte_con === 'transferencia' ? (
                <span className="text-base-content/70 inline-flex items-center gap-1 text-sm">
                    <NetworkIcon className="size-3.5" />
                    Transferencia
                </span>
            ) : (
                <span className="text-base-content/70 inline-flex items-center gap-1 text-sm">
                    <PackageIcon className="size-3.5" />
                    Salida
                </span>
            ),
    },
    {
        key: 'avance',
        label: 'Avance',
        className: 'text-right',
        render: (p) => (
            <span className="font-mono text-sm">
                {numero(p.avance.surtido)} / {numero(p.avance.solicitado)}
                {p.avance.renglones_pendientes > 0 && (
                    <span className="text-base-content/50 block text-xs">
                        {p.avance.renglones_pendientes} renglón(es) pendiente(s)
                    </span>
                )}
            </span>
        ),
    },
    { key: 'fecha_requerida', label: 'Se necesita', className: 'font-mono text-xs' },
    {
        key: 'estatus',
        label: 'Estado',
        render: (p) => <span className={`badge badge-sm ${CLASE_ESTATUS[p.estatus]}`}>{p.estatus_etiqueta}</span>,
    },
];

export default function PedidosIndex({ pedidos, filters, estatuses, almacenes, departamentos, obras }: Props) {
    const filtrar = (cambio: Record<string, string | undefined>) =>
        router.get('/admin/almacen/pedidos', { ...filters, ...cambio, page: undefined }, { preserveState: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pedidos" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Pedidos</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Lo que un área le pide a un almacén de lo que ya está en existencia. No es la requisición de
                        compra: ésa le pide material a un proveedor.
                    </p>
                </div>

                <DataTable
                    columns={columns}
                    data={pedidos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por folio..."
                    createHref="/admin/almacen/pedidos/create"
                    createLabel="Nuevo pedido"
                    emptyMessage="No hay pedidos con esos filtros."
                    getRowHref={(p) => `/admin/almacen/pedidos/${p.id}`}
                >
                    <div className="w-44">
                        <Select
                            value={filters.almacen_id ?? ''}
                            onValueChange={(v) => filtrar({ almacen_id: v || undefined })}
                            placeholder="Todos los almacenes"
                        >
                            {almacenes.map((a) => (
                                <SelectItem key={a.id} value={String(a.id)}>
                                    {etiquetaDeAlmacen(a)}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    <div className="w-44">
                        <Select
                            value={filters.destino ?? ''}
                            onValueChange={(v) => filtrar({ destino: v || undefined })}
                            placeholder="Cualquier destino"
                        >
                            <SelectItem value="planta">Consumo de planta</SelectItem>
                            <SelectItem value="obra">Para una obra</SelectItem>
                        </Select>
                    </div>

                    <div className="w-44">
                        <Select
                            value={filters.departamento_id ?? ''}
                            onValueChange={(v) => filtrar({ departamento_id: v || undefined })}
                            placeholder="Todas las áreas"
                        >
                            {departamentos.map((d) => (
                                <SelectItem key={d.id} value={String(d.id)}>
                                    {d.descripcion}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    <div className="w-40">
                        <Select
                            value={filters.estatus ?? ''}
                            onValueChange={(v) => filtrar({ estatus: v || undefined })}
                            placeholder="Todos los estados"
                        >
                            {estatuses.map((e) => (
                                <SelectItem key={e.value} value={e.value}>
                                    {e.label}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>
                </DataTable>
            </div>
        </AppLayout>
    );
}
