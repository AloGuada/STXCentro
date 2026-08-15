import { CodigoBarras, esCodificable } from '@/components/alm/codigo-barras';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import {
    ACTIVOS_DEMO,
    ALMACENES_DEMO,
    ARTICULOS_DEMO,
    CLASES_ABC,
    EXISTENCIAS_DEMO,
    REGLAS_ABC,
    rutaUbicacion,
} from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { PrinterIcon, SearchIcon, TriangleAlertIcon } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Códigos de barras', href: '/admin/almacen/etiquetas' },
];

/** Qué se está etiquetando: el artículo del anaquel o cada pieza serializada. */
type Modo = 'articulo' | 'pieza';

/**
 * Tamaños de etiqueta. El grande es el rótulo del anaquel, que se lee de lejos;
 * el chico es el que se pega en la pieza o en la caja.
 */
const FORMATOS = {
    anaquel: { etiqueta: 'Anaquel (3 por fila)', columnas: 3, altura: 64 },
    caja: { etiqueta: 'Caja (4 por fila)', columnas: 4, altura: 48 },
    pieza: { etiqueta: 'Pieza (6 por fila)', columnas: 6, altura: 34 },
} as const;

type Formato = keyof typeof FORMATOS;

type Etiqueta = {
    clave: string;
    codigo: string;
    descripcion: string;
    barras: string;
    /** Línea chica de abajo: marca/modelo, o el almacén y la serie de la pieza. */
    detalle: string | null;
};

/**
 * Hoja de códigos de barras para escaneo.
 *
 * Sale de aquí y no de un PDF del servidor porque la hoja se arma a mano cada
 * vez: hoy se rotula un rack completo, mañana las tres piezas que llegaron.
 * El código se dibuja en el navegador (Code 39), así que imprimir cincuenta
 * etiquetas no son cincuenta peticiones.
 */
export default function EtiquetasIndex() {
    const [modo, setModo] = useState<Modo>('articulo');
    const [almacen, setAlmacen] = useState('');
    const [clase, setClase] = useState('');
    const [busqueda, setBusqueda] = useState('');
    const [formato, setFormato] = useState<Formato>('anaquel');
    const [copias, setCopias] = useState('1');
    const [conDescripcion, setConDescripcion] = useState(true);
    const [seleccion, setSeleccion] = useState<Record<string, boolean>>({});

    const texto = busqueda.trim().toLowerCase();

    /** Los candidatos a etiquetar, ya filtrados. */
    const candidatos = useMemo<Etiqueta[]>(() => {
        if (modo === 'pieza') {
            return ACTIVOS_DEMO.filter((p) => {
                const articulo = ARTICULOS_DEMO.find((a) => a.id === p.producto_id);

                return (
                    (!almacen || p.almacen === almacen) &&
                    (!clase || articulo?.clasificacion_abc === clase) &&
                    (!texto ||
                        p.no_serie.toLowerCase().includes(texto) ||
                        p.codigo.toLowerCase().includes(texto) ||
                        p.descripcion.toLowerCase().includes(texto))
                );
            }).map((p) => ({
                clave: `pieza-${p.id}`,
                codigo: p.no_serie,
                descripcion: p.descripcion,
                barras: p.codigo_barras ?? p.no_serie,
                detalle: `${p.almacen}${p.ubicacion ? ` · ${p.ubicacion}` : ''}`,
            }));
        }

        // Lo que no lleva kardex no se etiqueta: no vive en ningún anaquel.
        return ARTICULOS_DEMO.filter((a) => {
            if (!a.controla_inventario) {
                return false;
            }

            if (almacen && !EXISTENCIAS_DEMO.some((e) => e.almacen === almacen && e.producto === a.codigo)) {
                return false;
            }

            if (clase && a.clasificacion_abc !== clase) {
                return false;
            }

            return (
                !texto ||
                a.codigo.toLowerCase().includes(texto) ||
                a.descripcion.toLowerCase().includes(texto) ||
                (a.marca ?? '').toLowerCase().includes(texto) ||
                (a.modelo ?? '').toLowerCase().includes(texto)
            );
        }).map((a) => {
            const existencia = EXISTENCIAS_DEMO.find(
                (e) => e.producto === a.codigo && (!almacen || e.almacen === almacen),
            );
            const lugar = existencia ? rutaUbicacion(existencia.ubicacion_id) : null;

            return {
                clave: `art-${a.id}`,
                codigo: a.codigo,
                descripcion: a.descripcion,
                barras: a.codigo_barras ?? a.codigo,
                detalle: [a.marca, a.modelo].filter(Boolean).join(' · ') || lugar,
            };
        });
    }, [modo, almacen, clase, texto]);

    const elegidas = candidatos.filter((c) => seleccion[c.clave]);
    const porCopia = Math.max(1, Math.min(20, Number(copias) || 1));

    /** La hoja final: cada etiqueta repetida tantas veces como copias se pidan. */
    const hoja = elegidas.flatMap((etiqueta) =>
        Array.from({ length: porCopia }, (_, i) => ({ ...etiqueta, clave: `${etiqueta.clave}-${i}` })),
    );

    const noImprimibles = elegidas.filter((e) => !esCodificable(e.barras));

    const alternar = (clave: string) =>
        setSeleccion((prev) => ({ ...prev, [clave]: !prev[clave] }));

    const todas = candidatos.length > 0 && candidatos.every((c) => seleccion[c.clave]);

    const alternarTodas = () =>
        setSeleccion((prev) => {
            const siguiente = { ...prev };
            candidatos.forEach((c) => {
                siguiente[c.clave] = !todas;
            });

            return siguiente;
        });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Códigos de barras" />

            {/*
             * Al imprimir sólo debe salir la hoja: ni menú, ni filtros, ni el
             * aviso de maqueta. Se oculta todo y se vuelve a mostrar la hoja.
             */}
            <style>{`@media print {
                body * { visibility: hidden; }
                #hoja-etiquetas, #hoja-etiquetas * { visibility: visible; }
                #hoja-etiquetas { position: absolute; left: 0; top: 0; width: 100%; border: 0; }
            }`}</style>

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3 print:hidden">
                    <div>
                        <h1 className="text-2xl font-semibold">Códigos de barras</h1>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Arma la hoja de etiquetas y la imprime. Es lo que hace que el almacenista escanee en vez de
                            teclear el código.
                        </p>
                    </div>
                    <Button onClick={() => window.print()} disabled={hoja.length === 0} variant="primary">
                        <PrinterIcon className="size-4" />
                        Imprimir {hoja.length > 0 ? `(${hoja.length})` : ''}
                    </Button>
                </div>

                <div className="alert alert-warning mb-4 print:hidden">
                    <span>
                        Vista de maqueta: los datos son de ejemplo. La impresión sí funciona — los códigos son Code 39
                        de verdad.
                    </span>
                </div>

                <div className="mb-4 flex flex-wrap items-end gap-3 print:hidden">
                    <div className="w-48">
                        <label className="label label-text text-xs">Qué se etiqueta</label>
                        <Select
                            value={modo}
                            onValueChange={(v) => {
                                setModo(v as Modo);
                                setSeleccion({});
                                setFormato(v === 'pieza' ? 'pieza' : 'anaquel');
                            }}
                        >
                            <SelectItem value="articulo">Artículos</SelectItem>
                            <SelectItem value="pieza">Piezas con número de serie</SelectItem>
                        </Select>
                    </div>

                    <div className="w-48">
                        <label className="label label-text text-xs">Almacén</label>
                        <Select value={almacen} onValueChange={setAlmacen}>
                            <SelectItem value="">Todos</SelectItem>
                            {ALMACENES_DEMO.map((a) => (
                                <SelectItem key={a.id} value={a.clave}>
                                    {a.clave} — {a.nombre}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    <div className="w-40">
                        <label className="label label-text text-xs">Clase</label>
                        <Select value={clase} onValueChange={setClase}>
                            <SelectItem value="">Todas</SelectItem>
                            {REGLAS_ABC.map((r) => (
                                <SelectItem key={r.clasificacion} value={r.clasificacion}>
                                    {r.clasificacion} — {r.etiqueta}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    <label className="input input-bordered flex max-w-xs flex-1 items-center gap-2">
                        <SearchIcon className="text-base-content/50 size-4" />
                        <input
                            className="grow"
                            placeholder={modo === 'pieza' ? 'Buscar por serie...' : 'Buscar por código o marca...'}
                            value={busqueda}
                            onChange={(e) => setBusqueda(e.target.value)}
                        />
                    </label>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div className="rounded-box border-base-300 overflow-hidden border lg:col-span-2 print:hidden">
                        <table className="table table-sm">
                            <thead className="bg-base-200">
                                <tr>
                                    <th className="w-10">
                                        <input
                                            type="checkbox"
                                            className="checkbox checkbox-sm"
                                            checked={todas}
                                            onChange={alternarTodas}
                                            aria-label="Seleccionar todo"
                                        />
                                    </th>
                                    <th>{modo === 'pieza' ? 'No. de serie' : 'Código'}</th>
                                    <th>Descripción</th>
                                    <th>Se imprime</th>
                                </tr>
                            </thead>
                            <tbody>
                                {candidatos.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="text-base-content/50 py-6 text-center">
                                            Nada que etiquetar con esos filtros.
                                        </td>
                                    </tr>
                                ) : (
                                    candidatos.map((c) => (
                                        <tr key={c.clave} className="hover">
                                            <td>
                                                <input
                                                    type="checkbox"
                                                    className="checkbox checkbox-sm"
                                                    checked={seleccion[c.clave] ?? false}
                                                    onChange={() => alternar(c.clave)}
                                                    aria-label={`Etiquetar ${c.codigo}`}
                                                />
                                            </td>
                                            <td className="font-mono text-xs">{c.codigo}</td>
                                            <td>
                                                {c.descripcion}
                                                {c.detalle && (
                                                    <span className="text-base-content/50 block text-xs">
                                                        {c.detalle}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="text-base-content/60 font-mono text-xs">
                                                {esCodificable(c.barras) ? (
                                                    c.barras
                                                ) : (
                                                    <span className="text-error">
                                                        <TriangleAlertIcon className="mr-1 inline size-3" />
                                                        {c.barras}
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="rounded-box border-base-300 border p-4 print:hidden">
                        <h2 className="mb-4 font-medium">Formato</h2>

                        <div className="space-y-4">
                            <div>
                                <label className="label label-text text-xs">Tamaño</label>
                                <Select value={formato} onValueChange={(v) => setFormato(v as Formato)}>
                                    {Object.entries(FORMATOS).map(([valor, f]) => (
                                        <SelectItem key={valor} value={valor}>
                                            {f.etiqueta}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </div>

                            <div>
                                <label className="label label-text text-xs">Copias de cada una</label>
                                <Input
                                    type="number"
                                    min="1"
                                    max="20"
                                    value={copias}
                                    onChange={(e) => setCopias(e.target.value)}
                                />
                                <p className="text-base-content/60 mt-1 text-xs">
                                    Útil cuando el mismo artículo vive en varios anaqueles.
                                </p>
                            </div>

                            <label className="flex cursor-pointer items-center gap-2">
                                <input
                                    type="checkbox"
                                    className="checkbox checkbox-sm"
                                    checked={conDescripcion}
                                    onChange={(e) => setConDescripcion(e.target.checked)}
                                />
                                <span className="text-sm">Incluir descripción</span>
                            </label>

                            <div className="border-base-300 border-t pt-3 text-sm">
                                <p>
                                    <strong>{elegidas.length}</strong> seleccionada(s) ·{' '}
                                    <strong>{hoja.length}</strong> etiqueta(s) a imprimir
                                </p>
                                {noImprimibles.length > 0 && (
                                    <p className="text-error mt-2">
                                        {noImprimibles.length} no se puede(n) codificar: Code 39 sólo acepta mayúsculas,
                                        números y <span className="font-mono">- . $ / + %</span>.
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    id="hoja-etiquetas"
                    className="rounded-box border-base-300 mt-4 border bg-white p-4 print:mt-0 print:rounded-none print:p-0"
                >
                    {hoja.length === 0 ? (
                        <p className="py-10 text-center text-sm text-neutral-500 print:hidden">
                            Elige qué etiquetar y la hoja se arma aquí.
                        </p>
                    ) : (
                        <div
                            className="grid gap-2"
                            style={{ gridTemplateColumns: `repeat(${FORMATOS[formato].columnas}, minmax(0, 1fr))` }}
                        >
                            {hoja.map((etiqueta) => (
                                <div
                                    key={etiqueta.clave}
                                    className="flex break-inside-avoid flex-col justify-between border border-dashed border-neutral-300 p-2"
                                >
                                    {conDescripcion && (
                                        <p className="mb-1 line-clamp-2 text-[11px] leading-tight font-medium text-black">
                                            {etiqueta.descripcion}
                                        </p>
                                    )}
                                    <CodigoBarras valor={etiqueta.barras} altura={FORMATOS[formato].altura} />
                                    {etiqueta.detalle && (
                                        <p className="mt-1 truncate text-center text-[9px] text-neutral-600">
                                            {etiqueta.detalle}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <p className="text-base-content/60 mt-4 text-sm print:hidden">
                    El artículo comparte una sola etiqueta: todos los tornillos del rack se escanean igual. La{' '}
                    <strong>pieza</strong> lleva la suya, y por eso al devolver un préstamo se sabe cuál de las catorce
                    pulidoras volvió.{' '}
                    <span className={`badge badge-xs ${CLASES_ABC.A}`}>A</span> ,{' '}
                    <span className={`badge badge-xs ${CLASES_ABC.B}`}>B</span> y{' '}
                    <span className={`badge badge-xs ${CLASES_ABC.C}`}>C</span> son las clases del inventario cíclico:
                    sirven aquí para rotular primero lo que más se cuenta.
                </p>
            </div>
        </AppLayout>
    );
}
