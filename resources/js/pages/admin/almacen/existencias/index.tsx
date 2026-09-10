import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeftRightIcon,
    ChevronDownIcon,
    DownloadIcon,
    HistoryIcon,
    MapPinOffIcon,
    SearchIcon,
    TriangleAlertIcon,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { etiquetaDeAlmacen } from '@/lib/alm/almacenes';
import type { BreadcrumbItem } from '@/types';
import type { AlmAlmacenOpcion, PaginatedData } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Existencias', href: '/admin/almacen/existencias' },
];

/** Lo que se espera a que la mano se detenga antes de ir al servidor. */
const BUSQUEDA_MS = 400;

const moneda = (n: number) => n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
const cantidad = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

type ExistenciaFila = {
    id: number;
    almacen_id: number;
    almacen: string | null;
    obra: string | null;
    articulo_id: number;
    codigo: string | null;
    descripcion: string | null;
    unidad: string | null;
    clasificacion_abc: string | null;
    /** La familia del insumo. Null es legítimo: hay artículos sin clasificar. */
    area: string | null;
    se_controla_por_pieza: boolean;
    stock_minimo: number | null;
    cantidad: number;
    /** Lo que anda afuera en resguardo. Sólo suma en los activos por cantidad. */
    prestado: number;
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

type AreaOpcion = { id: number; descripcion: string };

type Props = {
    existencias: PaginatedData<ExistenciaFila>;
    filters: {
        almacen_id?: string;
        ubicacion_id?: string;
        obra_id?: string;
        area_id?: string;
        search?: string;
        sin_acomodar?: boolean;
        /** '' | 'con_saldo' | 'cero'. */
        saldo?: string;
    };
    /**
     * El pie de la tabla: suma lo filtrado entero, no la página. Viaja en `null`
     * mientras no se haya filtrado: la pantalla no está vacía, está sin
     * consultar.
     */
    totales: { renglones: number; valor: number } | null;
    almacenes: AlmAlmacenOpcion[];
    ubicaciones: { id: number; ruta: string }[];
    obras: ObraOpcion[];
    areas: AreaOpcion[];
    puedeReasignar: boolean;
};

/**
 * La pantalla de diario: qué hay y cuánto en cada almacén.
 *
 * Se abre en blanco a propósito: el consolidado de la empresa son decenas de
 * miles de renglones que nadie lee. Primero se pregunta —un almacén, una obra,
 * o un artículo en el buscador— y hasta entonces hay tabla y totales.
 */
export default function ExistenciasIndex({
    existencias,
    filters,
    totales,
    almacenes,
    ubicaciones,
    obras,
    areas,
    puedeReasignar,
}: Props) {
    const [reasignando, setReasignando] = useState<ExistenciaFila | null>(null);
    const [busqueda, setBusqueda] = useState(filters.search ?? '');
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    /** El servidor sólo consulta cuando ya hay una pregunta que contestar. */
    const consultado = totales !== null;

    /** El Excel es de lo filtrado entero: lleva los mismos filtros que la pantalla. */
    const exportar = () => {
        const params = new URLSearchParams();

        Object.entries(filters).forEach(([clave, valor]) => {
            if (valor !== undefined && valor !== '' && valor !== false) {
                params.set(clave, String(valor));
            }
        });

        window.location.href = `/admin/almacen/existencias/exportar?${params.toString()}`;
    };

    const filtrar = (cambio: Record<string, string | undefined>) =>
        router.get('/admin/almacen/existencias', { ...filters, ...cambio, page: undefined }, { preserveState: true });

    /** Teclear no es preguntar: la consulta sale cuando la mano se detiene. */
    const teclear = (valor: string) => {
        setBusqueda(valor);

        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        debounceRef.current = setTimeout(() => filtrar({ search: valor.trim() || undefined }), BUSQUEDA_MS);
    };

    const buscarYa = () => {
        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
            debounceRef.current = null;
        }

        filtrar({ search: busqueda.trim() || undefined });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Existencias" />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Existencias</h1>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Lo que hay hoy en cada almacén, valuado a costo promedio. Es sólo lectura: el saldo se
                            mueve con documentos, nunca a mano.
                        </p>
                    </div>
                    <button
                        type="button"
                        className="btn btn-outline btn-sm"
                        onClick={exportar}
                        disabled={!consultado}
                        title={consultado ? 'Descarga lo filtrado, completo' : 'Filtra primero: el reporte es de lo que está en pantalla'}
                    >
                        <DownloadIcon className="size-4" /> Exportar Excel
                    </button>
                </div>

                <form
                    className="mb-3 flex max-w-xl gap-2"
                    onSubmit={(ev) => {
                        ev.preventDefault();
                        buscarYa();
                    }}
                >
                    <label className="input input-bordered flex flex-1 items-center gap-2">
                        <SearchIcon className="text-base-content/40 size-4" />
                        <input
                            type="search"
                            className="grow"
                            value={busqueda}
                            placeholder="Buscar por código, descripción o código de barras..."
                            autoFocus
                            onChange={(e) => teclear(e.target.value)}
                        />
                    </label>
                    <button type="submit" className="btn btn-neutral">
                        Buscar
                    </button>
                </form>

                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <div className="w-52">
                        <label className="label label-text text-xs">Almacén</label>
                        <Select
                            value={filters.almacen_id ?? ''}
                            onValueChange={(v) => filtrar({ almacen_id: v || undefined, ubicacion_id: undefined })}
                        >
                            <SelectItem value="">Todos</SelectItem>
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
                            >
                                <SelectItem value="">Todas</SelectItem>
                                {ubicaciones.map((u) => (
                                    <SelectItem key={u.id} value={String(u.id)}>
                                        {u.ruta}
                                    </SelectItem>
                                ))}
                            </Select>
                        </div>
                    )}

                    {/* Filtrar por familia es la forma de mirar el inventario sin
                        elegir bodega: «qué tenemos de tornillería» no es una
                        pregunta sobre un almacén. */}
                    <div className="w-52">
                        <label className="label label-text text-xs">Área</label>
                        <Select
                            value={filters.area_id ?? ''}
                            onValueChange={(v) => filtrar({ area_id: v || undefined })}
                        >
                            <SelectItem value="">Todas</SelectItem>
                            {areas.map((a) => (
                                <SelectItem key={a.id} value={String(a.id)}>
                                    {a.descripcion}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    {/* «Qué material puedo repartir» es la pregunta con la que se
                        abre esta pantalla cuando hay que asignar lo que ya estaba
                        en bodega, así que lo libre es una opción más. */}
                    <div className="w-52">
                        <label className="label label-text text-xs">Obra</label>
                        <Select
                            value={filters.obra_id ?? ''}
                            onValueChange={(v) => filtrar({ obra_id: v || undefined })}
                        >
                            <SelectItem value="">Todas</SelectItem>
                            <SelectItem value="libre">Sin asignar</SelectItem>
                            {obras.map((o) => (
                                <SelectItem key={o.id} value={String(o.id)}>
                                    {o.no}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    {/* «Sin existencia» es la pregunta de compras —qué se
                        acabó—, y es la contraria de «con existencia», así que
                        las dos comparten control en vez de ser dos casillas que
                        se pueden marcar juntas y no devolver nada. */}
                    <div className="w-52">
                        <label className="label label-text text-xs">Existencia</label>
                        <Select
                            value={filters.saldo ?? ''}
                            onValueChange={(v) => filtrar({ saldo: v || undefined })}
                        >
                            <SelectItem value="">Todas</SelectItem>
                            <SelectItem value="con_saldo">Con existencia</SelectItem>
                            <SelectItem value="cero">Sin existencia</SelectItem>
                        </Select>
                    </div>

                    {/* Material que nadie acomodó: es la lista de trabajo del
                        almacenista, y por eso sigue siendo un filtro a la vista. */}
                    <label className="mb-2 flex cursor-pointer items-center gap-2">
                        <input
                            type="checkbox"
                            className="checkbox checkbox-sm"
                            checked={Boolean(filters.sin_acomodar)}
                            onChange={(e) => filtrar({ sin_acomodar: e.target.checked ? '1' : undefined })}
                        />
                        <span className="flex items-center gap-1 text-sm">
                            <MapPinOffIcon className="size-3.5" />
                            Sin acomodar
                        </span>
                    </label>
                </div>

                {consultado ? (
                    <div className="rounded-box border-base-300 overflow-x-auto border">
                        <table className="table table-sm">
                            <thead className="bg-base-200">
                                <tr>
                                    <th>Almacén</th>
                                    <th>Artículo</th>
                                    <th>Área</th>
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
                                        <td colSpan={10} className="text-base-content/50 py-6 text-center">
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
                                                        href={`/admin/almacen/articulos/${e.articulo_id}`}
                                                        className="link link-hover font-mono text-xs"
                                                    >
                                                        {e.codigo}
                                                    </Link>
                                                    <span className="block">{e.descripcion}</span>
                                                    {e.piezas && (
                                                        <Link
                                                            href={`/admin/almacen/activos?articulo_id=${e.articulo_id}&almacen_id=${e.almacen_id}`}
                                                            className="badge badge-xs badge-info"
                                                            title="Ver las piezas de este renglón"
                                                        >
                                                            {e.piezas.disponibles} de {cantidad(e.cantidad)} disponible(s)
                                                        </Link>
                                                    )}
                                                </td>
                                                <td>
                                                    {e.area ?? (
                                                        <span
                                                            className="text-base-content/30"
                                                            title="Sin clasificar todavía"
                                                        >
                                                            —
                                                        </span>
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
                                                    {e.prestado > 0 && (
                                                        <span
                                                            className="text-base-content/50 block text-xs"
                                                            title="En resguardo: siguen siendo del almacén, pero no están en el anaquel"
                                                        >
                                                            {cantidad(e.prestado)} en resguardo
                                                        </span>
                                                    )}
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
                                                    {/* De quién es. */}
                                                    <DesgloseAsignaciones existencia={e} />
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
                                                        href={`/admin/almacen/kardex?almacen_id=${e.almacen_id}&articulo_id=${e.articulo_id}`}
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

                            {/* Lo filtrado entero, no la página. Las cantidades no
                                se suman: cada renglón trae su unidad y sumar kilos
                                con piezas no daría un número, daría un error. */}
                            <tfoot className="bg-base-200 text-base-content">
                                <tr>
                                    <td colSpan={7} className="text-right font-medium">
                                        Total de {totales.renglones}{' '}
                                        {totales.renglones === 1 ? 'renglón' : 'renglones'}
                                        {existencias.total > existencias.data.length && (
                                            <span className="text-base-content/50 ml-1 font-normal">
                                                (no sólo esta página)
                                            </span>
                                        )}
                                    </td>
                                    <td className="text-right font-mono font-semibold">{moneda(totales.valor)}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                ) : (
                    /* Nada que enseñar todavía. No es un inventario vacío: es un
                       inventario que nadie ha preguntado, y decirlo evita que el
                       almacenista crea que se le perdió el material. */
                    <div className="rounded-box border-base-300 bg-base-100 border border-dashed p-10 text-center">
                        <SearchIcon className="text-base-content/30 mx-auto size-8" />
                        <p className="mt-3 font-medium">Elige qué quieres ver</p>
                        <p className="text-base-content/60 mx-auto mt-1 max-w-md text-sm">
                            El inventario completo son demasiados renglones para leerlos de corrido. Busca un artículo,
                            o filtra por almacén, ubicación u obra.
                        </p>
                        <div className="mt-4 flex flex-wrap justify-center gap-2">
                            {almacenes.map((a) => (
                                <button
                                    key={a.id}
                                    type="button"
                                    className="btn btn-sm btn-outline"
                                    onClick={() => filtrar({ almacen_id: String(a.id) })}
                                >
                                    {etiquetaDeAlmacen(a)}
                                </button>
                            ))}
                        </div>
                        {/* La lista de trabajo del almacenista sigue estando a un
                            clic, aunque los totales ya no se calculen de entrada. */}
                        <button
                            type="button"
                            className="btn btn-ghost btn-sm mt-2"
                            onClick={() => filtrar({ sin_acomodar: '1' })}
                        >
                            <MapPinOffIcon className="size-3.5" />
                            Ver lo que está sin acomodar
                        </button>
                    </div>
                )}

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
 * De quién es el renglón, renglón por renglón.
 *
 * Con dos obras la línea corrida se leía; con seis no. El desglose se abre
 * dentro de la celda en vez de flotar encima: la tabla vive en un contenedor
 * con scroll horizontal y ahí cualquier capa absoluta se corta a la mitad.
 */
function DesgloseAsignaciones({ existencia }: { existencia: ExistenciaFila }) {
    const { libre, asignaciones } = existencia;

    // Un renglón sin repartir está todo libre y no necesita explicarse.
    if (asignaciones.length === 0) {
        return null;
    }

    return (
        <details className="group mt-0.5">
            <summary
                className="text-base-content/60 hover:text-base-content flex cursor-pointer list-none items-center justify-end gap-1 text-xs [&::-webkit-details-marker]:hidden"
                title="Comprometido con una obra: la salida de otra necesita permiso"
            >
                <ChevronDownIcon className="size-3 transition-transform group-open:rotate-180" />
                <span className="font-sans">
                    {asignaciones.length === 1 ? '1 obra' : `${asignaciones.length} obras`}
                </span>
                {libre > 0 && <span className="text-base-content/40">· {cantidad(libre)} libre</span>}
            </summary>

            <ul className="rounded-box border-base-300 bg-base-200/60 mt-1 min-w-40 border p-1 text-xs font-sans">
                {asignaciones.map((a) => (
                    <li key={a.obra_id} className="flex items-baseline justify-between gap-3 px-1.5 py-0.5">
                        <span className="truncate">{a.obra ?? '—'}</span>
                        <span className="font-mono">{cantidad(a.cantidad)}</span>
                    </li>
                ))}
                {/* Lo que sobra de repartir. No se guarda: es la existencia
                    menos lo comprometido, y es lo único que cualquiera puede
                    llevarse sin pedir permiso. */}
                <li className="border-base-300 text-base-content/60 flex items-baseline justify-between gap-3 border-t px-1.5 py-0.5">
                    <span>Libre</span>
                    <span className="font-mono">{cantidad(libre)}</span>
                </li>
            </ul>
        </details>
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
