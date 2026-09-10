import { Head, Link, router, usePage } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';
import { ButtonLink } from '@/components/ui/button';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { etiquetaDeAlmacen } from '@/lib/alm/almacenes';
import type { BreadcrumbItem } from '@/types';
import type { AlmAlmacenOpcion, PaginatedData } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Activos', href: '/admin/almacen/activos' },
    { title: 'Devoluciones', href: '/admin/almacen/devoluciones' },
];

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

type DevolucionFila = {
    id: number;
    fecha: string | null;
    prestamo_id: number;
    folio: string | null;
    almacen: string | null;
    devolvio: string | null;
    recibio: string | null;
    codigo: string | null;
    descripcion: string | null;
    unidad: string | null;
    no_serie: string | null;
    cantidad_devuelta: number;
    cantidad: number;
    condicion_salida: string | null;
    condicion_retorno: string | null;
};

type Props = {
    devoluciones: PaginatedData<DevolucionFila>;
    /** Renglones de resguardo que siguen afuera. */
    pendientes: number;
    filters: { almacen_id?: string };
    almacenes: AlmAlmacenOpcion[];
};

/**
 * Lo que ha vuelto al almacén. No es un documento propio: cada fila es un
 * renglón de resguardo que cerró, y por eso se llega a su vale con un clic.
 */
export default function DevolucionesIndex({ devoluciones, pendientes, filters, almacenes }: Props) {
    const { flash } = usePage<{ flash: { success?: string } }>().props;

    const filtrar = (cambio: Record<string, string | undefined>) =>
        router.get('/admin/almacen/devoluciones', { ...filters, ...cambio, page: undefined }, { preserveState: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Devoluciones" />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Devoluciones</h1>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Activos que volvieron al almacén y dejaron de estar a nombre de alguien. No mueven
                            existencia: lo prestado siempre fue del almacén.
                        </p>
                    </div>
                    <ButtonLink href="/admin/almacen/devoluciones/create" variant="primary">
                        <PlusIcon className="size-4" />
                        Recibir devolución
                    </ButtonLink>
                </div>

                {flash?.success && (
                    <div className="alert alert-success mb-4">
                        <span>{flash.success}</span>
                    </div>
                )}

                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <div className="w-48">
                        <label className="label label-text text-xs">Almacén</label>
                        <Select
                            value={filters.almacen_id ?? ''}
                            onValueChange={(v) => filtrar({ almacen_id: v || undefined })}
                        >
                            <SelectItem value="">Todos</SelectItem>
                            {almacenes.map((a) => (
                                <SelectItem key={a.id} value={String(a.id)}>
                                    {etiquetaDeAlmacen(a)}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>
                    <p className="text-base-content/60 pb-3 text-sm">
                        {pendientes > 0
                            ? `${pendientes} renglón(es) siguen afuera en algún resguardo.`
                            : 'No hay nada afuera.'}
                    </p>
                </div>

                <div className="rounded-box border-base-300 overflow-x-auto border">
                    <table className="table table-sm">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Fecha</th>
                                <th>Resguardo</th>
                                <th>Artículo</th>
                                <th>Serie</th>
                                <th className="text-right">Volvió</th>
                                <th>Devolvió</th>
                                <th>Recibió</th>
                                <th>Cómo salió → cómo volvió</th>
                            </tr>
                        </thead>
                        <tbody>
                            {devoluciones.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="text-base-content/50 py-6 text-center">
                                        Todavía no ha vuelto nada.
                                    </td>
                                </tr>
                            ) : (
                                devoluciones.data.map((d) => (
                                    <tr key={d.id} className="hover">
                                        <td className="font-mono text-xs">{d.fecha}</td>
                                        <td>
                                            <Link
                                                href={`/admin/almacen/prestamos/${d.prestamo_id}`}
                                                className="link link-hover font-mono text-xs font-medium"
                                            >
                                                {d.folio}
                                            </Link>
                                            <span className="badge badge-xs badge-ghost ml-1 font-mono">{d.almacen}</span>
                                        </td>
                                        <td>
                                            <span className="font-mono text-xs">{d.codigo}</span>
                                            <span className="block text-sm">{d.descripcion}</span>
                                        </td>
                                        <td className="font-mono text-xs">
                                            {d.no_serie ?? <span className="text-base-content/40">por cantidad</span>}
                                        </td>
                                        <td className="text-right font-mono">
                                            {numero(d.cantidad_devuelta)}
                                            {d.cantidad_devuelta < d.cantidad && (
                                                <span className="text-base-content/50"> de {numero(d.cantidad)}</span>
                                            )}{' '}
                                            <span className="text-base-content/40 text-xs">{d.unidad}</span>
                                        </td>
                                        <td className="text-sm">{d.devolvio}</td>
                                        <td className="text-sm">{d.recibio ?? '—'}</td>
                                        <td className="text-base-content/70 text-xs">
                                            {d.condicion_retorno
                                                ? `${d.condicion_salida ?? '—'} → ${d.condicion_retorno}`
                                                : (d.condicion_salida ?? '—')}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {devoluciones.links.length > 3 && (
                    <div className="join mt-4 flex justify-center">
                        {devoluciones.links.map((link, i) =>
                            link.url === null ? (
                                <button key={i} className="join-item btn btn-sm btn-disabled">
                                    <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                </button>
                            ) : (
                                <Link
                                    key={i}
                                    href={link.url}
                                    className={`join-item btn btn-sm ${link.active ? 'btn-active' : ''}`}
                                    preserveState
                                >
                                    <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                </Link>
                            ),
                        )}
                    </div>
                )}

                <p className="text-base-content/60 mt-4 text-sm">
                    Aquí vuelven los activos prestados, con o sin serie. El material por cantidad que sobró en una obra
                    regresa con una transferencia, que es como se mueve el saldo entre almacenes.
                </p>
            </div>
        </AppLayout>
    );
}
