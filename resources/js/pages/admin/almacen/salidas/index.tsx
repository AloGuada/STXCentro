import { BotonFormato } from '@/components/alm/boton-formato';
import { DataTable, type Column } from '@/components/data-table';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { etiquetaDeAlmacen } from '@/lib/alm/almacenes';
import type { BreadcrumbItem } from '@/types';
import type { AlmAlmacenOpcion, PaginatedData } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Salidas', href: '/admin/almacen/salidas' },
];

type SalidaFila = {
    id: number;
    folio: string | null;
    fecha: string | null;
    almacen: string | null;
    obra_destino: string | null;
    pedido_folio: string | null;
    recibe: string | null;
    entrego: string | null;
    renglones: number;
    cancelada: boolean;
};

type Props = {
    salidas: PaginatedData<SalidaFila>;
    filters: {
        almacen_id?: string;
        obra_id?: string;
        desde?: string;
        hasta?: string;
        search?: string;
        ver_canceladas?: boolean;
    };
    almacenes: AlmAlmacenOpcion[];
    obras: { id: number; no: string; descripcion: string }[];
};

const columns: Column<SalidaFila>[] = [
    {
        key: 'folio',
        label: 'Folio',
        render: (s) => (
            <span className={`font-mono font-medium ${s.cancelada ? 'line-through opacity-60' : ''}`}>{s.folio}</span>
        ),
    },
    { key: 'fecha', label: 'Fecha', className: 'font-mono text-xs' },
    {
        key: 'almacen',
        label: 'Almacén',
        render: (s) => <span className="badge badge-sm badge-ghost font-mono">{s.almacen}</span>,
    },
    {
        key: 'pedido_folio',
        label: 'Surte',
        render: (s) =>
            s.pedido_folio ? (
                <span className="font-mono text-xs">{s.pedido_folio}</span>
            ) : (
                <span className="text-base-content/50 text-sm" title="Salida directa, sin pedido previo">
                    Directa
                </span>
            ),
    },
    { key: 'recibe', label: 'Recibe' },
    { key: 'renglones', label: 'Renglones', className: 'text-right font-mono' },
    { key: 'entrego', label: 'Entregó' },
    {
        key: 'cancelada',
        label: '',
        render: (s) => (s.cancelada ? <span className="badge badge-sm badge-ghost">Cancelada</span> : null),
    },
    {
        key: 'vale',
        label: '',
        className: 'w-10',
        render: (s) => <BotonFormato href={`/admin/almacen/salidas/${s.id}/pdf`} soloIcono />,
    },
];

export default function SalidasIndex({ salidas, filters, almacenes, obras }: Props) {
    const filtrar = (cambio: Record<string, string | undefined>) =>
        router.get('/admin/almacen/salidas', { ...filters, ...cambio, page: undefined }, { preserveState: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Salidas" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Salidas</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Material que sale del almacén y se queda en el mismo domicilio. El «vale» es el impreso que
                        firma quien se lo lleva; el documento se llama salida.
                    </p>
                </div>

                <DataTable
                    columns={columns}
                    data={salidas}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por folio o quién recibe..."
                    createHref="/admin/almacen/salidas/create"
                    createLabel="Nueva salida"
                    emptyMessage="No hay salidas con esos filtros."
                    getRowHref={(s) => `/admin/almacen/salidas/${s.id}`}
                >
                    <div className="w-48">
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

                    <div className="w-48">
                        <Select
                            value={filters.obra_id ?? ''}
                            onValueChange={(v) => filtrar({ obra_id: v || undefined })}
                            placeholder="Todas las obras"
                        >
                            {obras.map((o) => (
                                <SelectItem key={o.id} value={String(o.id)}>
                                    {o.no} — {o.descripcion}
                                </SelectItem>
                            ))}
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
