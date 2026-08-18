import { FormField } from '@/components/form';
import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import {
    ACTIVOS_DEMO,
    ALMACENES_DEMO,
    ESTATUS_ACTIVO,
    PRESTAMOS_DEMO,
    rutaUbicacion,
    ubicacionesDe,
} from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import type { AlmActivoDemo, AlmActivoEstatus } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { PencilIcon, PlusIcon, SearchIcon } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Activos', href: '/admin/almacen/activos' },
];

/**
 * Las piezas identificadas: una fila por número de serie.
 *
 * El kardex sigue contando por cantidad —14 pulidoras en HER— y esta pantalla
 * es la que dice *cuáles* son esas 14 y en qué anda cada una. Sólo aparecen los
 * artículos marcados "por pieza" en el catálogo.
 */
export default function ActivosIndex() {
    // Existencias manda aquí con el artículo ya elegido: el renglón dice cuántas
    // hay y esta pantalla, cuáles son. Llegar sin el filtro puesto obligaría a
    // teclear otra vez lo que ya se había señalado.
    const params = new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search);

    const [query, setQuery] = useState(params.get('codigo') ?? '');
    const [almacen, setAlmacen] = useState(params.get('almacen') ?? '');
    const [estatus, setEstatus] = useState('');
    // Los datos de la pieza —serie, marca, modelo, id de mantenimiento— se
    // corrigen aquí y no en el catálogo: son de esta pulidora, no de todas.
    const [editando, setEditando] = useState<AlmActivoDemo | null>(null);

    /** Quién trae cada pieza prestada, para no tener que ir a Préstamos. */
    const responsablePorActivo = useMemo(() => {
        const mapa: Record<number, string> = {};

        PRESTAMOS_DEMO.filter((p) => p.estatus === 'abierto').forEach((p) => {
            mapa[p.activo_id] = p.responsable;
        });

        return mapa;
    }, []);

    const visibles = useMemo(() => {
        const s = query.trim().toLowerCase();

        return ACTIVOS_DEMO.filter(
            (a) =>
                (!almacen || a.almacen === almacen) &&
                (!estatus || a.estatus === estatus) &&
                (!s ||
                    a.no_serie.toLowerCase().includes(s) ||
                    a.codigo.toLowerCase().includes(s) ||
                    a.descripcion.toLowerCase().includes(s) ||
                    (a.marca ?? '').toLowerCase().includes(s) ||
                    (a.modelo ?? '').toLowerCase().includes(s) ||
                    (a.id_mantenimiento ?? '').toLowerCase().includes(s)),
        );
    }, [query, almacen, estatus]);

    const conteo = (e: AlmActivoEstatus) => ACTIVOS_DEMO.filter((a) => a.estatus === e).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Activos" />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Activos</h1>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Una fila por número de serie. El kardex cuenta cuántas hay; aquí se ve cuáles son y en qué
                            anda cada una.
                        </p>
                    </div>
                    <ButtonLink href="/admin/almacen/activos/create" variant="primary">
                        <PlusIcon className="size-4" />
                        Dar de alta piezas
                    </ButtonLink>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: los datos son de ejemplo, todavía no hay backend.</span>
                </div>

                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <div className="min-w-64 flex-1">
                        <label className="label" htmlFor="q">
                            <span className="label-text">Buscar</span>
                        </label>
                        <div className="relative">
                            <SearchIcon className="text-base-content/40 pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                id="q"
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Serie, código, marca o id de mto."
                                className="pl-9"
                            />
                        </div>
                    </div>

                    <div className="w-56">
                        <label className="label" htmlFor="almacen">
                            <span className="label-text">Almacén</span>
                        </label>
                        <Select id="almacen" value={almacen} onValueChange={setAlmacen} placeholder="Todos">
                            {ALMACENES_DEMO.map((a) => (
                                <SelectItem key={a.id} value={a.clave}>
                                    {a.clave} — {a.nombre}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    <div className="w-56">
                        <label className="label" htmlFor="estatus">
                            <span className="label-text">Estado</span>
                        </label>
                        <Select id="estatus" value={estatus} onValueChange={setEstatus} placeholder="Todos">
                            {Object.entries(ESTATUS_ACTIVO).map(([valor, { etiqueta }]) => (
                                <SelectItem key={valor} value={valor}>
                                    {etiqueta} ({conteo(valor as AlmActivoEstatus)})
                                </SelectItem>
                            ))}
                        </Select>
                    </div>
                </div>

                <div className="rounded-box border-base-300 overflow-x-auto border">
                    <table className="table">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Serie</th>
                                <th>Código</th>
                                <th>Artículo</th>
                                <th>Id de mto.</th>
                                <th>Almacén</th>
                                <th>Ubicación</th>
                                <th>Estado</th>
                                <th>Quién la trae</th>
                                <th>Condición</th>
                                <th className="w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {visibles.length === 0 ? (
                                <tr>
                                    <td colSpan={10} className="text-base-content/50 py-6 text-center">
                                        Ninguna pieza coincide con el filtro.
                                    </td>
                                </tr>
                            ) : (
                                visibles.map((a) => {
                                    const estado = ESTATUS_ACTIVO[a.estatus];

                                    return (
                                        <tr key={a.id} className="hover">
                                            <td className="font-mono font-medium">{a.no_serie}</td>
                                            <td className="font-mono text-sm">{a.codigo}</td>
                                            <td className="text-sm">
                                                {a.descripcion}
                                                {/* Marca y modelo son de la pieza: dos altas del
                                                    mismo artículo pueden traer marcas distintas. */}
                                                {(a.marca || a.modelo) && (
                                                    <span className="text-base-content/50 block text-xs">
                                                        {[a.marca, a.modelo].filter(Boolean).join(' · ')}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="text-base-content/60 font-mono text-xs">
                                                {a.id_mantenimiento ?? (
                                                    <span
                                                        className="text-base-content/30"
                                                        title="Nadie le ha anotado su número en mantenimiento"
                                                    >
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td>
                                                <span className="badge badge-sm badge-ghost font-mono">
                                                    {a.almacen}
                                                </span>
                                            </td>
                                            <td className="text-base-content/60 text-sm">
                                                {rutaUbicacion(a.ubicacion_id) ?? (
                                                    <span className="text-base-content/40">Sin acomodar</span>
                                                )}
                                            </td>
                                            <td>
                                                <span className={`badge badge-sm ${estado.clase}`}>
                                                    {estado.etiqueta}
                                                </span>
                                            </td>
                                            <td className="text-sm">
                                                {responsablePorActivo[a.id] ?? (
                                                    <span className="text-base-content/40">En el almacén</span>
                                                )}
                                            </td>
                                            <td className="text-base-content/70 text-sm">{a.condicion}</td>
                                            <td>
                                                <button
                                                    type="button"
                                                    className="btn btn-ghost btn-xs"
                                                    onClick={() => setEditando(a)}
                                                    title="Corregir los datos de esta pieza"
                                                    aria-label={`Editar la pieza ${a.no_serie}`}
                                                >
                                                    <PencilIcon className="size-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                <p className="text-base-content/60 mt-4 text-sm">
                    Cada pieza suma 1 a la existencia de su artículo, así que estas{' '}
                    <strong>{ACTIVOS_DEMO.filter((a) => a.codigo === 'PUL-4120').length} pulidoras</strong> son las
                    mismas que cuenta el kardex — no es un inventario aparte. Prestarla no la saca del almacén, sólo deja
                    de estar disponible; eso se maneja desde{' '}
                    <Link href="/admin/almacen/prestamos" className="link">
                        Préstamos
                    </Link>
                    .
                </p>

                {editando && <ModalPieza pieza={editando} onCerrar={() => setEditando(null)} />}
            </div>
        </AppLayout>
    );
}

/**
 * Corregir una pieza ya dada de alta.
 *
 * Lo que se toca aquí es de la pieza y de nadie más: la serie con que se
 * etiquetó, la marca y el modelo con que se cumplió el artículo, el número con
 * que la conoce mantenimiento, y en qué anda hoy. Cambiar el artículo sería
 * otra cosa —eso es un error de alta— y por eso no está.
 */
function ModalPieza({ pieza, onCerrar }: { pieza: AlmActivoDemo; onCerrar: () => void }) {
    const [serie, setSerie] = useState(pieza.no_serie);
    const [marca, setMarca] = useState(pieza.marca ?? '');
    const [modelo, setModelo] = useState(pieza.modelo ?? '');
    const [idMantenimiento, setIdMantenimiento] = useState(pieza.id_mantenimiento ?? '');
    const [condicion, setCondicion] = useState(pieza.condicion);
    const [ubicacion, setUbicacion] = useState(pieza.ubicacion_id === null ? '' : String(pieza.ubicacion_id));

    return (
        <dialog className="modal modal-open">
            <div className="modal-box max-w-2xl">
                <h3 className="text-lg font-bold">
                    Pieza <span className="font-mono">{pieza.no_serie}</span>
                </h3>
                <p className="text-base-content/60 mt-1 text-sm">
                    {pieza.codigo} — {pieza.descripcion}
                </p>

                <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <FormField label="No. de serie" htmlFor="serie" required>
                        <Input
                            id="serie"
                            value={serie}
                            onChange={(e) => setSerie(e.target.value)}
                            className="font-mono"
                        />
                    </FormField>

                    <FormField
                        label="Id de mantenimiento"
                        htmlFor="id_mantenimiento"
                        description="Con qué número la conoce mantenimiento. Sólo se anota."
                    >
                        <Input
                            id="id_mantenimiento"
                            value={idMantenimiento}
                            onChange={(e) => setIdMantenimiento(e.target.value)}
                            className="font-mono"
                            placeholder="MTO-0071"
                        />
                    </FormField>

                    <FormField label="Marca" htmlFor="marca">
                        <Input
                            id="marca"
                            value={marca}
                            onChange={(e) => setMarca(e.target.value)}
                            placeholder="DeWalt"
                        />
                    </FormField>

                    <FormField
                        label="Modelo"
                        htmlFor="modelo"
                        description="Es lo que se pide al reponerla o al comprarle refacción."
                    >
                        <Input
                            id="modelo"
                            value={modelo}
                            onChange={(e) => setModelo(e.target.value)}
                            placeholder="DWE4120"
                        />
                    </FormField>

                    <FormField label="Condición" htmlFor="condicion">
                        <Input
                            id="condicion"
                            value={condicion}
                            onChange={(e) => setCondicion(e.target.value)}
                            placeholder="Buena, usada, sin guarda..."
                        />
                    </FormField>

                    <FormField
                        label="Ubicación"
                        htmlFor="ubicacion"
                        description={`Dónde vive dentro de ${pieza.almacen} cuando está en el almacén.`}
                    >
                        <Select
                            id="ubicacion"
                            value={ubicacion}
                            onValueChange={setUbicacion}
                            placeholder="Sin acomodar"
                        >
                            {ubicacionesDe(pieza.almacen)
                                .filter((u) => u.activa)
                                .map((u) => (
                                    <SelectItem key={u.id} value={String(u.id)}>
                                        {rutaUbicacion(u.id)}
                                    </SelectItem>
                                ))}
                        </Select>
                    </FormField>
                </div>

                <div className="alert alert-warning mt-4">
                    <span>La maqueta todavía no guarda: al cerrar, la pieza queda como estaba.</span>
                </div>

                <div className="modal-action">
                    <Button type="button" variant="outline" onClick={onCerrar}>
                        Cerrar
                    </Button>
                    <Button type="button" disabled title="La maqueta todavía no guarda">
                        Guardar
                    </Button>
                </div>
            </div>
            <div className="modal-backdrop" onClick={onCerrar}></div>
        </dialog>
    );
}
