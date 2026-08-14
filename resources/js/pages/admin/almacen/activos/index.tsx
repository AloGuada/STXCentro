import { ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { ACTIVOS_DEMO, ALMACENES_DEMO, ESTATUS_ACTIVO, PRESTAMOS_DEMO } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import type { AlmActivoEstatus } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { PlusIcon, SearchIcon } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
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
    const [query, setQuery] = useState('');
    const [almacen, setAlmacen] = useState('');
    const [estatus, setEstatus] = useState('');

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
                    a.descripcion.toLowerCase().includes(s)),
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
                                placeholder="Serie, código o descripción"
                                className="pl-9"
                            />
                        </div>
                    </div>

                    <div className="w-56">
                        <label className="label" htmlFor="almacen">
                            <span className="label-text">Pañol</span>
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
                                <th>Pañol</th>
                                <th>Estado</th>
                                <th>Quién la trae</th>
                                <th>Condición</th>
                            </tr>
                        </thead>
                        <tbody>
                            {visibles.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="text-base-content/50 py-6 text-center">
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
                                            <td className="text-sm">{a.descripcion}</td>
                                            <td>
                                                <span className="badge badge-sm badge-ghost font-mono">
                                                    {a.almacen}
                                                </span>
                                            </td>
                                            <td>
                                                <span className={`badge badge-sm ${estado.clase}`}>
                                                    {estado.etiqueta}
                                                </span>
                                            </td>
                                            <td className="text-sm">
                                                {responsablePorActivo[a.id] ?? (
                                                    <span className="text-base-content/40">En el pañol</span>
                                                )}
                                            </td>
                                            <td className="text-base-content/70 text-sm">{a.condicion}</td>
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
                    mismas que cuenta el kardex — no es un inventario aparte. Prestarla no la saca del pañol, sólo deja
                    de estar disponible; eso se maneja desde{' '}
                    <Link href="/admin/almacen/prestamos" className="link">
                        Préstamos
                    </Link>
                    .
                </p>
            </div>
        </AppLayout>
    );
}
