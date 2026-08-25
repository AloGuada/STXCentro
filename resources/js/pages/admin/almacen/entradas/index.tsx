import { DataTable, type Column } from '@/components/data-table';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { etiquetaDeAlmacen } from '@/lib/alm/almacenes';
import type { BreadcrumbItem } from '@/types';
import type { AlmAlmacenOpcion, PaginatedData } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { PackageCheckIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Entradas', href: '/admin/almacen/entradas' },
];

const moneda = (n: number) => n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });

type EntradaFila = {
    id: number;
    folio: string | null;
    fecha: string | null;
    almacen: string | null;
    orden_compra_id: number | null;
    orden_folio: string | null;
    proveedor: string | null;
    renglones: number;
    importe: number;
    recibio: string | null;
    cancelada: boolean;
};

type Props = {
    entradas: PaginatedData<EntradaFila>;
    filters: { almacen_id?: string; search?: string; ver_canceladas?: boolean };
    almacenes: AlmAlmacenOpcion[];
    /** Lo que el almacén todavía debe recibir contra una orden. */
    ordenesAbiertas: { id: number; folio: string | null; proveedor: string | null; fecha: string | null }[];
};

const columns: Column<EntradaFila>[] = [
    {
        key: 'folio',
        label: 'Folio',
        render: (e) => (
            <span className={`font-mono font-medium ${e.cancelada ? 'line-through opacity-60' : ''}`}>{e.folio}</span>
        ),
    },
    { key: 'fecha', label: 'Fecha', className: 'font-mono text-xs' },
    {
        key: 'almacen',
        label: 'Almacén',
        render: (e) => <span className="badge badge-sm badge-ghost font-mono">{e.almacen}</span>,
    },
    {
        key: 'orden_folio',
        label: 'Orden',
        render: (e) =>
            e.orden_folio ? (
                <Link
                    href={`/admin/costos/ordenes-compra/${e.orden_compra_id}`}
                    className="link link-hover font-mono text-xs"
                    onClick={(ev) => ev.stopPropagation()}
                >
                    {e.orden_folio}
                </Link>
            ) : (
                <span className="text-base-content/50 text-sm" title="Material que llegó sin compra de por medio">
                    Sin orden
                </span>
            ),
    },
    { key: 'proveedor', label: 'Proveedor' },
    { key: 'renglones', label: 'Renglones', className: 'text-right font-mono' },
    {
        key: 'importe',
        label: 'Importe',
        className: 'text-right',
        render: (e) => <span className="font-mono">{moneda(e.importe)}</span>,
    },
    { key: 'recibio', label: 'Recibió' },
    {
        key: 'cancelada',
        label: '',
        render: (e) => (e.cancelada ? <span className="badge badge-sm badge-ghost">Cancelada</span> : null),
    },
];

/**
 * La recepción vista desde Almacén.
 *
 * Escribe en `costos_entregas`, la misma tabla que destraba la factura: no es un
 * documento paralelo. Toda entrada que sube el valor del inventario se captura
 * aquí, venga de una orden de compra o sin ella.
 */
export default function EntradasIndex({ entradas, filters, almacenes, ordenesAbiertas }: Props) {
    const filtrar = (cambio: Record<string, string | undefined>) =>
        router.get('/admin/almacen/entradas', { ...filters, ...cambio, page: undefined }, { preserveState: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Entradas" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Entradas</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Material que llega al almacén. Es la misma recepción que ve Compras —la que destraba la
                        factura—, capturada desde acá.
                    </p>
                </div>

                {ordenesAbiertas.length > 0 && (
                    <div className="alert alert-info mb-4">
                        <PackageCheckIcon className="size-4" />
                        <span>
                            {ordenesAbiertas.length} orden(es) de compra siguen esperando material.
                        </span>
                        <Link href="/admin/almacen/entradas/create" className="btn btn-sm">
                            Recibir contra una orden
                        </Link>
                    </div>
                )}

                <DataTable
                    columns={columns}
                    data={entradas}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por folio..."
                    createHref="/admin/almacen/entradas/create"
                    createLabel="Nueva entrada"
                    emptyMessage="No hay entradas con esos filtros."
                    getRowHref={(e) => `/admin/almacen/entradas/${e.id}`}
                >
                    <div className="w-52">
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
