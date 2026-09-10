import { Head, Link, router, usePage } from '@inertiajs/react';
import { PlusIcon, TriangleAlertIcon } from 'lucide-react';
import { ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { etiquetaDeAlmacen } from '@/lib/alm/almacenes';
import type { BreadcrumbItem } from '@/types';
import type { AlmAlmacenOpcion, PaginatedData } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Activos', href: '/admin/almacen/activos' },
    { title: 'Préstamos', href: '/admin/almacen/prestamos' },
];

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

type PrestamoFila = {
    id: number;
    folio: string;
    almacen: string | null;
    responsable: string | null;
    destino: string;
    fecha_salida: string;
    fecha_retorno_esperada: string | null;
    dias_fuera: number;
    vencido: boolean;
    renglones: number;
    /** Unidades que siguen afuera, sumando piezas y cantidades. */
    pendiente: number;
    unidades: number;
    estatus: 'abierto' | 'cerrado';
    estatus_etiqueta: string;
};

type Props = {
    prestamos: PaginatedData<PrestamoFila>;
    filters: { almacen_id?: string; estatus?: string; responsable_id?: string; search?: string };
    resumen: { abiertos: number; vencidos: number };
    almacenes: AlmAlmacenOpcion[];
    responsables: { id: string; name: string }[];
};

/**
 * Quién tiene qué activos. Es la pregunta que un kardex por cantidad no puede
 * contestar: sabe que salieron tres pulidoras, no cuál trae cada quien.
 *
 * El resguardo no mueve el saldo: lo prestado sigue siendo del almacén, lo
 * que cambia es la custodia. Con serie va pieza por pieza; sin serie, por
 * cantidad, y puede volver por partes.
 */
export default function PrestamosIndex({ prestamos, filters, resumen, almacenes, responsables }: Props) {
    const { flash } = usePage<{ flash: { success?: string } }>().props;

    const filtrar = (cambio: Record<string, string | undefined>) =>
        router.get('/admin/almacen/prestamos', { ...filters, ...cambio, page: undefined }, { preserveState: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Préstamos" />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Préstamos</h1>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Resguardos de activos: quién los tiene, desde cuándo y hasta cuándo. Lo prestado sigue
                            siendo del almacén; lo que cambia es la custodia.
                        </p>
                    </div>
                    <ButtonLink href="/admin/almacen/prestamos/create" variant="primary">
                        <PlusIcon className="size-4" />
                        Prestar
                    </ButtonLink>
                </div>

                {flash?.success && (
                    <div className="alert alert-success mb-4">
                        <span>{flash.success}</span>
                    </div>
                )}

                {resumen.vencidos > 0 && (
                    <div className="alert alert-error mb-4">
                        <TriangleAlertIcon className="size-5" />
                        <span>
                            {resumen.vencidos} {resumen.vencidos === 1 ? 'resguardo pasó' : 'resguardos pasaron'} su
                            fecha de retorno.
                        </span>
                    </div>
                )}

                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <div className="w-44">
                        <label className="label label-text text-xs">Mostrar</label>
                        <Select value={filters.estatus ?? ''} onValueChange={(v) => filtrar({ estatus: v || undefined })}>
                            <SelectItem value="">Todos</SelectItem>
                            <SelectItem value="abierto">Afuera ({resumen.abiertos})</SelectItem>
                            <SelectItem value="vencidos">Vencidos ({resumen.vencidos})</SelectItem>
                            <SelectItem value="cerrado">Devueltos</SelectItem>
                        </Select>
                    </div>

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

                    <div className="w-56">
                        <label className="label label-text text-xs">Responsable</label>
                        <Select
                            value={filters.responsable_id ?? ''}
                            onValueChange={(v) => filtrar({ responsable_id: v || undefined })}
                        >
                            <SelectItem value="">Todos</SelectItem>
                            {responsables.map((r) => (
                                <SelectItem key={r.id} value={r.id}>
                                    {r.name}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    <div className="max-w-xs flex-1">
                        <label className="label label-text text-xs">Buscar</label>
                        <Input
                            defaultValue={filters.search ?? ''}
                            placeholder="Folio, nombre o serie..."
                            onChange={(e) => filtrar({ search: e.target.value || undefined })}
                        />
                    </div>
                </div>

                <div className="rounded-box border-base-300 overflow-x-auto border">
                    <table className="table table-sm">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Folio</th>
                                <th>Almacén</th>
                                <th>Quién responde</th>
                                <th>Dónde</th>
                                <th>Salió</th>
                                <th>Debe volver</th>
                                <th className="text-right">Días fuera</th>
                                <th className="text-right">Afuera</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            {prestamos.data.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="text-base-content/50 py-6 text-center">
                                        Ningún resguardo con esos filtros.
                                    </td>
                                </tr>
                            ) : (
                                prestamos.data.map((p) => (
                                    <tr key={p.id} className={p.vencido ? 'bg-error/5' : 'hover'}>
                                        <td>
                                            <Link
                                                href={`/admin/almacen/prestamos/${p.id}`}
                                                className="link link-hover font-mono font-medium"
                                            >
                                                {p.folio}
                                            </Link>
                                        </td>
                                        <td>
                                            <span className="badge badge-sm badge-ghost font-mono">{p.almacen}</span>
                                        </td>
                                        <td className="text-sm font-medium">{p.responsable}</td>
                                        <td className="text-sm">{p.destino}</td>
                                        <td className="font-mono text-xs">{p.fecha_salida}</td>
                                        <td className="font-mono text-xs">
                                            {p.fecha_retorno_esperada ? (
                                                <span className={p.vencido ? 'text-error font-semibold' : ''}>
                                                    {p.fecha_retorno_esperada}
                                                </span>
                                            ) : (
                                                <span className="text-base-content/40">Sin fecha</span>
                                            )}
                                        </td>
                                        <td className="text-right font-mono">
                                            <span className={p.vencido ? 'text-error font-semibold' : ''}>
                                                {p.vencido && <TriangleAlertIcon className="mr-1 inline size-3" />}
                                                {p.dias_fuera}
                                            </span>
                                        </td>
                                        <td className="text-right font-mono text-xs">
                                            {numero(p.pendiente)}/{numero(p.unidades)}
                                            <span className="text-base-content/50 block">
                                                {p.renglones} renglón(es)
                                            </span>
                                        </td>
                                        <td>
                                            <span
                                                className={`badge badge-sm ${p.estatus === 'abierto' ? 'badge-warning' : 'badge-success'}`}
                                            >
                                                {p.estatus_etiqueta}
                                            </span>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {prestamos.links.length > 3 && (
                    <div className="join mt-4 flex justify-center">
                        {prestamos.links.map((link, i) =>
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
                    Con número de serie se presta pieza por pieza y vuelve entera. Sin serie, el activo se presta por
                    cantidad contra su existencia y puede volver por partes. Un insumo no se presta: sale con una
                    salida.
                </p>
            </div>
        </AppLayout>
    );
}
