import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { etiquetaDeAlmacen } from '@/lib/alm/almacenes';
import type { BreadcrumbItem } from '@/types';
import type { AlmAlmacenOpcion, PaginatedData } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeftRightIcon, HistoryIcon, MapPinOffIcon, TriangleAlertIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Existencias', href: '/admin/almacen/existencias' },
];

const moneda = (n: number) => n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
const cantidad = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

type ExistenciaFila = {
    id: number;
    almacen_id: number;
    almacen: string | null;
    obra: string | null;
    producto_id: number;
    codigo: string | null;
    descripcion: string | null;
    unidad: string | null;
    clasificacion_abc: string | null;
    se_controla_por_pieza: boolean;
    stock_minimo: number | null;
    cantidad: number;
    costo_promedio: number;
    valor: number;
    ubicacion_id: number | null;
    ubicacion: string | null;
    ultimo_movimiento_at: string | null;
    /**
     * Sólo en los renglones por pieza. Suma exactamente la existencia: lo que
     * cambia es que aquí sí se sabe en qué anda cada una, y eso decide lo que el
     * almacenista puede prometer.
     */
    piezas: { disponibles: number; prestadas: number; en_reparacion: number } | null;
    /**
     * Lo que viene en camino hacia este almacén. No entra al saldo ni al valor:
     * salió de otra bodega y todavía no es de ésta.
     */
    en_transito: number;
    /**
     * Lo que cualquiera puede llevarse sin pedirle permiso a nadie. No se
     * guarda: es la existencia menos lo repartido.
     */
    libre: number;
    /** De quién es el resto. Vacío = todo el renglón está libre. */
    asignaciones: { obra_id: number; obra: string | null; cantidad: number }[];
};

type ObraOpcion = { id: number; no: string };

type Props = {
    existencias: PaginatedData<ExistenciaFila>;
    filters: {
        almacen_id?: string;
        ubicacion_id?: string;
        obra_id?: string;
        search?: string;
        sin_acomodar?: boolean;
        solo_con_saldo?: boolean;
    };
    /** Sobre el almacén elegido, no sobre la página. */
    resumen: { renglones: number; valor: number; con_saldo: number; sin_acomodar: number; en_negativo: number };
    almacenes: AlmAlmacenOpcion[];
    ubicaciones: { id: number; ruta: string }[];
    obras: ObraOpcion[];
    puedeReasignar: boolean;
};

/**
 * La pantalla de diario: qué hay y cuánto en cada almacén. Sin filtro de almacén
 * se ve el consolidado de la empresa; con filtro, el inventario de esa bodega.
 */
export default function ExistenciasIndex({
    existencias,
    filters,
    resumen,
    almacenes,
    ubicaciones,
    obras,
    puedeReasignar,
}: Props) {
    const [reasignando, setReasignando] = useState<ExistenciaFila | null>(null);

    const filtrar = (cambio: Record<string, string | undefined>) =>
        router.get('/admin/almacen/existencias', { ...filters, ...cambio, page: undefined }, { preserveState: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Existencias" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Existencias</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Lo que hay hoy en cada almacén, valuado a costo promedio. Es sólo lectura: el saldo se mueve con
                        documentos, nunca a mano.
                    </p>
                </div>

                <div className="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div className="rounded-box border-base-300 border p-3">
                        <p className="text-base-content/60 text-xs">Valor del inventario</p>
                        <p className="font-mono text-xl">{moneda(resumen.valor)}</p>
                    </div>
                    <div className="rounded-box border-base-300 border p-3">
                        <p className="text-base-content/60 text-xs">Artículos con saldo</p>
                        <p className="font-mono text-xl">{resumen.con_saldo}</p>
                        <p className="text-base-content/50 text-xs">de {resumen.renglones} con historia</p>
                    </div>
                    <button
                        type="button"
                        className="rounded-box border-base-300 hover:bg-base-200 border p-3 text-left"
                        onClick={() => filtrar({ sin_acomodar: filters.sin_acomodar ? undefined : '1' })}
                    >
                        <p className="text-base-content/60 flex items-center gap-1 text-xs">
                            <MapPinOffIcon className="size-3" />
                            Sin acomodar
                        </p>
                        <p className={`font-mono text-xl ${resumen.sin_acomodar > 0 ? 'text-warning' : ''}`}>
                            {resumen.sin_acomodar}
                        </p>
                    </button>
                    {/* No debería pasar nunca; justo por eso hay que poder verlo. */}
                    <div className="rounded-box border-base-300 border p-3">
                        <p className="text-base-content/60 flex items-center gap-1 text-xs">
                            <TriangleAlertIcon className="size-3" />
                            En negativo
                        </p>
                        <p className={`font-mono text-xl ${resumen.en_negativo > 0 ? 'text-error' : ''}`}>
                            {resumen.en_negativo}
                        </p>
                    </div>
                </div>

                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <div className="w-52">
                        <label className="label label-text text-xs">Almacén</label>
                        <Select
                            value={filters.almacen_id ?? ''}
                            onValueChange={(v) => filtrar({ almacen_id: v || undefined, ubicacion_id: undefined })}
                            placeholder="Todos"
                        >
                            {almacenes.map((a) => (
                                <SelectItem key={a.id} value={String(a.id)}>
                                    {etiquetaDeAlmacen(a)} — {a.nombre}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    {/* Filtrar por lugar sólo tiene sentido dentro de un almacén:
                        el «Rack A-1» de AG no es el de FAK. */}
                    {ubicaciones.length > 0 && (
                        <div className="w-64">
                            <label className="label label-text text-xs">Ubicación</label>
                            <Select
                                value={filters.ubicacion_id ?? ''}
                                onValueChange={(v) => filtrar({ ubicacion_id: v || undefined })}
                                placeholder="Todas"
                            >
                                {ubicaciones.map((u) => (
                                    <SelectItem key={u.id} value={String(u.id)}>
                                        {u.ruta}
                                    </SelectItem>
                                ))}
                            </Select>
                        </div>
                    )}

                    {/* «Qué material puedo repartir» es la pregunta con la que se
                        abre esta pantalla cuando hay que asignar lo que ya estaba
                        en bodega, así que lo libre es una opción más. */}
                    <div className="w-52">
                        <label className="label label-text text-xs">Obra</label>
                        <Select
                            value={filters.obra_id ?? ''}
                            onValueChange={(v) => filtrar({ obra_id: v || undefined })}
                            placeholder="Todas"
                        >
                            <SelectItem value="libre">Sin asignar</SelectItem>
                            {obras.map((o) => (
                                <SelectItem key={o.id} value={String(o.id)}>
                                    {o.no}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    <div className="max-w-sm flex-1">
                        <label className="label label-text text-xs">Buscar</label>
                        <Input
                            defaultValue={filters.search ?? ''}
                            placeholder="Código, descripción o código de barras..."
                            onChange={(e) => filtrar({ search: e.target.value || undefined })}
                        />
                    </div>

                    <label className="mb-2 flex cursor-pointer items-center gap-2">
                        <input
                            type="checkbox"
                            className="checkbox checkbox-sm"
                            checked={Boolean(filters.solo_con_saldo)}
                            onChange={(e) => filtrar({ solo_con_saldo: e.target.checked ? '1' : undefined })}
                        />
                        <span className="text-sm">Sólo con saldo</span>
                    </label>
                </div>

                <div className="rounded-box border-base-300 overflow-x-auto border">
                    <table className="table table-sm">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Almacén</th>
                                <th>Artículo</th>
                                <th>Unidad</th>
                                <th>Ubicación</th>
                                <th className="text-right">Existencia</th>
                                <th className="text-right">En camino</th>
                                <th className="text-right">Costo promedio</th>
                                <th className="text-right">Valor</th>
                                <th className="w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {existencias.data.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="text-base-content/50 py-6 text-center">
                                        No hay existencias con esos filtros.
                                    </td>
                                </tr>
                            ) : (
                                existencias.data.map((e) => {
                                    const bajoMinimo = e.stock_minimo !== null && e.cantidad < e.stock_minimo;

                                    return (
                                        <tr key={e.id} className="hover">
                                            <td>
                                                <span className="badge badge-sm badge-ghost font-mono">
                                                    {e.almacen}
                                                </span>
                                                {e.obra && (
                                                    <span className="text-base-content/60 ml-1 text-xs">{e.obra}</span>
                                                )}
                                            </td>
                                            <td>
                                                <Link
                                                    href={`/admin/almacen/articulos/${e.producto_id}`}
                                                    className="link link-hover font-mono text-xs"
                                                >
                                                    {e.codigo}
                                                </Link>
                                                <span className="block">{e.descripcion}</span>
                                                {e.piezas && (
                                                    <Link
                                                        href={`/admin/almacen/activos?producto_id=${e.producto_id}&almacen_id=${e.almacen_id}`}
                                                        className="badge badge-xs badge-info"
                                                        title="Ver las piezas de este renglón"
                                                    >
                                                        {e.piezas.disponibles} de {cantidad(e.cantidad)} disponible(s)
                                                    </Link>
                                                )}
                                            </td>
                                            <td className="text-base-content/60 font-mono text-xs">{e.unidad}</td>
                                            <td className="text-base-content/60 text-sm">
                                                {e.ubicacion ?? (
                                                    <span className="text-warning inline-flex items-center gap-1">
                                                        <MapPinOffIcon className="size-3" />
                                                        Sin acomodar
                                                    </span>
                                                )}
                                            </td>
                                            <td className="text-right font-mono">
                                                {/* Cinco pulidoras con tres prestadas no son
                                                    cinco pulidoras que entregar. */}
                                                {e.piezas &&
                                                    e.piezas.prestadas + e.piezas.en_reparacion > 0 && (
                                                        <span
                                                            className="text-base-content/50 block text-xs"
                                                            title="Siguen siendo del almacén, pero no se pueden entregar"
                                                        >
                                                            {[
                                                                e.piezas.prestadas > 0
                                                                    ? `${e.piezas.prestadas} afuera`
                                                                    : null,
                                                                e.piezas.en_reparacion > 0
                                                                    ? `${e.piezas.en_reparacion} en reparación`
                                                                    : null,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(' · ')}
                                                        </span>
                                                    )}
                                                <span
                                                    className={
                                                        e.cantidad < 0
                                                            ? 'text-error font-semibold'
                                                            : bajoMinimo
                                                              ? 'text-warning font-medium'
                                                              : ''
                                                    }
                                                >
                                                    {cantidad(e.cantidad)}
                                                </span>
                                                {bajoMinimo && e.cantidad >= 0 && (
                                                    <TriangleAlertIcon
                                                        className="text-warning ml-1 inline size-3"
                                                        aria-label={`Por debajo del mínimo (${cantidad(e.stock_minimo ?? 0)})`}
                                                    />
                                                )}
                                                {/* De quién es. Un renglón sin
                                                    asignaciones está todo libre y
                                                    no necesita explicarse. */}
                                                {e.asignaciones.length > 0 && (
                                                    <span
                                                        className="text-base-content/60 block text-xs"
                                                        title="Comprometido con una obra: la salida de otra necesita permiso"
                                                    >
                                                        {[
                                                            e.libre > 0 ? `${cantidad(e.libre)} libre` : null,
                                                            ...e.asignaciones.map(
                                                                (a) => `${cantidad(a.cantidad)} ${a.obra ?? '—'}`,
                                                            ),
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ')}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="text-right font-mono">
                                                {e.en_transito > 0 ? (
                                                    <span
                                                        className="text-warning"
                                                        title="Salió de otro almacén y todavía no llega: no cuenta como existencia"
                                                    >
                                                        {cantidad(e.en_transito)}
                                                    </span>
                                                ) : (
                                                    <span className="text-base-content/30">—</span>
                                                )}
                                            </td>
                                            <td className="text-base-content/60 text-right font-mono">
                                                {moneda(e.costo_promedio)}
                                            </td>
                                            <td className="text-right font-mono">{moneda(e.valor)}</td>
                                            <td className="whitespace-nowrap">
                                                {puedeReasignar && e.cantidad > 0 && (
                                                    <button
                                                        type="button"
                                                        className="btn btn-ghost btn-xs"
                                                        title="Repartir entre obras"
                                                        onClick={() => setReasignando(e)}
                                                    >
                                                        <ArrowLeftRightIcon className="size-3.5" />
                                                    </button>
                                                )}
                                                <Link
                                                    href={`/admin/almacen/kardex?almacen_id=${e.almacen_id}&producto_id=${e.producto_id}`}
                                                    className="btn btn-ghost btn-xs"
                                                    title="Ver su kardex"
                                                >
                                                    <HistoryIcon className="size-3.5" />
                                                </Link>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                {existencias.links.length > 3 && (
                    <div className="join mt-4 flex justify-center">
                        {existencias.links.map((link, i) =>
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

                {reasignando !== null && (
                    <ReasignarModal existencia={reasignando} obras={obras} onCerrar={() => setReasignando(null)} />
                )}
            </div>
        </AppLayout>
    );
}

/**
 * Cambiar de dueño, no de bodega.
 *
 * Lo libre es un origen y un destino de primera clase, no el caso borde:
 * mientras el inventario que ya estaba en bodega no tenga asignación, repartir
 * desde lo libre es la vía normal por la que la gana.
 */
function ReasignarModal({
    existencia,
    obras,
    onCerrar,
}: {
    existencia: ExistenciaFila;
    obras: ObraOpcion[];
    onCerrar: () => void;
}) {
    const form = useForm({
        existencia_id: existencia.id,
        de_obra_id: '',
        a_obra_id: '',
        cantidad: '',
        motivo: '',
    });

    // Lo que hay de verdad en el origen elegido: es el tope de lo que se puede
    // mover, y verlo evita teclear una cantidad que el servidor va a rebotar.
    const enOrigen =
        form.data.de_obra_id === ''
            ? existencia.libre
            : (existencia.asignaciones.find((a) => String(a.obra_id) === form.data.de_obra_id)?.cantidad ?? 0);

    return (
        <dialog className="modal modal-open">
            <div className="modal-box max-w-lg">
                <h3 className="text-lg font-semibold">Reasignar material</h3>
                <p className="text-base-content/60 mb-4 text-sm">
                    {existencia.codigo} — {existencia.descripcion}. Cambia de obra, no de bodega: el saldo del almacén
                    queda igual y el gasto no se mueve.
                </p>

                <form
                    onSubmit={(ev) => {
                        ev.preventDefault();
                        form.transform((datos) => ({
                            ...datos,
                            de_obra_id: datos.de_obra_id === '' ? null : datos.de_obra_id,
                            a_obra_id: datos.a_obra_id === '' ? null : datos.a_obra_id,
                        }));
                        form.post('/admin/almacen/asignaciones/reasignar', {
                            preserveScroll: true,
                            onSuccess: onCerrar,
                        });
                    }}
                    className="space-y-3"
                >
                    <label className="form-control">
                        <span className="label label-text">De</span>
                        <Select
                            value={form.data.de_obra_id}
                            onValueChange={(v) => form.setData('de_obra_id', v)}
                            placeholder="Sin asignar (libre)"
                        >
                            {existencia.asignaciones.map((a) => (
                                <SelectItem key={a.obra_id} value={String(a.obra_id)}>
                                    {a.obra ?? '—'} ({cantidad(a.cantidad)})
                                </SelectItem>
                            ))}
                        </Select>
                        <span className="text-base-content/50 text-xs">Disponible ahí: {cantidad(enOrigen)}</span>
                    </label>

                    <label className="form-control">
                        <span className="label label-text">A</span>
                        <Select
                            value={form.data.a_obra_id}
                            onValueChange={(v) => form.setData('a_obra_id', v)}
                            placeholder="Soltar a libre"
                        >
                            {obras.map((o) => (
                                <SelectItem key={o.id} value={String(o.id)}>
                                    {o.no}
                                </SelectItem>
                            ))}
                        </Select>
                        {form.errors.a_obra_id && <span className="text-error text-xs">{form.errors.a_obra_id}</span>}
                    </label>

                    <label className="form-control">
                        <span className="label label-text">Cantidad</span>
                        <Input
                            type="number"
                            step="0.0001"
                            min="0"
                            max={enOrigen}
                            value={form.data.cantidad}
                            onChange={(ev) => form.setData('cantidad', ev.target.value)}
                            className="font-mono"
                        />
                        {form.errors.cantidad && <span className="text-error text-xs">{form.errors.cantidad}</span>}
                    </label>

                    <label className="form-control">
                        <span className="label label-text">Motivo</span>
                        <Input
                            value={form.data.motivo}
                            onChange={(ev) => form.setData('motivo', ev.target.value)}
                            placeholder="Por qué cambia de obra"
                        />
                        {form.errors.motivo && <span className="text-error text-xs">{form.errors.motivo}</span>}
                    </label>

                    <div className="modal-action">
                        <button type="button" className="btn btn-ghost" onClick={onCerrar}>
                            Cancelar
                        </button>
                        <button type="submit" className="btn btn-primary" disabled={form.processing}>
                            Reasignar
                        </button>
                    </div>
                </form>
            </div>
            <form method="dialog" className="modal-backdrop">
                <button onClick={onCerrar}>cerrar</button>
            </form>
        </dialog>
    );
}
