/**
 * El dosier de una obra — el editor simple.
 *
 * A la izquierda el árbol de secciones con cuántos PDF lleva cada una; a la
 * derecha la sección elegida con sus PDF: subir varios a la vez, verlos,
 * acomodar su orden (el de la descarga) y quitarlos. Arriba, el estatus
 * —borrador, en revisión, entregado con fecha— y «Descargar dosier».
 *
 * El árbol es propio de este dosier: «Editar secciones» añade o quita sin
 * tocar la plantilla ni los demás. Quitar una sección con PDF los borra.
 */

import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowDownIcon, ArrowUpIcon, DownloadIcon, ExternalLinkIcon, FileWarningIcon, Trash2Icon, UploadIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ArbolDeLectura, EditorDeArbol, editableDe, paraGuardar } from '@/components/qal/dosier/arbol';
import { ESTATUS_DOSIER } from '@/components/qal/dosier/lista';
import type { ArchivoDeSeccion, EstatusDosier, NodoArbol, NodoEditable } from '@/components/qal/dosier/tipos';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import { formatBytes } from '@/lib/uploads';
import type { BreadcrumbItem } from '@/types';

type Props = {
    dosier: {
        id: number;
        obra: string;
        plantilla: string;
        estatus: EstatusDosier;
        entregado_at: string | null;
        notas: string | null;
        creo: string | null;
        creado: string;
    };
    arbol: NodoArbol[];
    archivos: ArchivoDeSeccion[];
    estatus: { valor: EstatusDosier; etiqueta: string }[];
    limites: { archivoMb: number; porEnvio: number };
};

/** Las secciones en orden de lectura, para encontrar la elegida. */
function aplanar(nodos: NodoArbol[]): NodoArbol[] {
    return nodos.flatMap((n) => [n, ...aplanar(n.hijos)]);
}

function idsDe(nodos: NodoEditable[]): number[] {
    return nodos.flatMap((n) => [...(n.id ? [n.id] : []), ...idsDe(n.hijos)]);
}

export default function DosierDeObra({ dosier, arbol, archivos, estatus, limites }: Props) {
    const { can } = useCan();
    const puedeEditar = can('qal.dossier.editar');
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const base = `/admin/calidad/dosier/${dosier.id}`;

    const secciones = useMemo(() => aplanar(arbol), [arbol]);
    const porSeccion = useMemo(() => {
        const mapa = new Map<number, ArchivoDeSeccion[]>();
        archivos.forEach((a) => mapa.set(a.seccion_id, [...(mapa.get(a.seccion_id) ?? []), a]));
        mapa.forEach((lista) => lista.sort((x, y) => x.orden - y.orden));

        return mapa;
    }, [archivos]);

    const [elegida, setElegida] = useState<number | null>(secciones[0]?.id ?? null);
    const seccion = secciones.find((s) => s.id === elegida) ?? null;
    const propios = seccion ? (porSeccion.get(seccion.id) ?? []) : [];
    const fuera = archivos.filter((a) => !a.compatible);

    const [editando, setEditando] = useState(false);
    const [borrador, setBorrador] = useState<NodoEditable[]>(() => editableDe(arbol));

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Dosier', href: '/admin/calidad/dosier' },
        { title: dosier.obra, href: base },
    ];

    const notas = useForm({ estatus: dosier.estatus, notas: dosier.notas ?? '' });
    const cambiarEstatus = (valor: EstatusDosier) => router.put(base, { estatus: valor, notas: notas.data.notas }, { preserveScroll: true });

    const subida = useForm<{ archivos: File[] }>({ archivos: [] });
    const subir = () => {
        if (!seccion) {
            return;
        }

        subida.post(`${base}/secciones/${seccion.id}/archivos`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => subida.reset(),
        });
    };
    const demasiadoGrandes = subida.data.archivos.filter((f) => f.size > limites.archivoMb * 1024 * 1024);

    const mover = (i: number, paso: -1 | 1) => {
        if (!seccion) {
            return;
        }

        const ids = propios.map((a) => a.id);
        [ids[i], ids[i + paso]] = [ids[i + paso], ids[i]];
        router.put(`${base}/secciones/${seccion.id}/archivos/orden`, { ids }, { preserveScroll: true });
    };

    const quitar = (archivo: ArchivoDeSeccion) => {
        if (confirm(`¿Quitar «${archivo.nombre}» del dosier? Se borra el archivo.`)) {
            router.delete(`${base}/archivos/${archivo.id}`, { preserveScroll: true });
        }
    };

    const guardarSecciones = () => {
        const quedan = new Set(idsDe(borrador));
        const perdidos = archivos.filter((a) => !quedan.has(a.seccion_id));

        if (perdidos.length && !confirm(`Las secciones que quitaste tienen ${perdidos.length} PDF. ¿Borrarlos junto con las secciones?`)) {
            return;
        }

        router.put(
            `${base}/secciones`,
            { arbol: paraGuardar(borrador) },
            { preserveScroll: true, onSuccess: () => setEditando(false) },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Dosier · ${dosier.obra}`} />

            <div className="space-y-4 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">{dosier.obra}</h1>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Nació de «{dosier.plantilla}» el {dosier.creado}
                            {dosier.creo && ` · lo creó ${dosier.creo}`}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link href="/admin/calidad/dosier" className="btn btn-sm btn-ghost">
                            Volver
                        </Link>
                        <a href={`${base}/descargar`} className="btn btn-sm btn-primary">
                            <DownloadIcon className="size-4" />
                            Descargar dosier
                        </a>
                    </div>
                </div>

                <div className="rounded-box border-base-300 flex flex-wrap items-center gap-3 border p-3">
                    <span className="text-sm font-medium">Estatus</span>
                    <div className="join">
                        {estatus.map((e) => (
                            <button
                                key={e.valor}
                                type="button"
                                disabled={!puedeEditar}
                                onClick={() => cambiarEstatus(e.valor)}
                                className={`btn btn-sm join-item ${dosier.estatus === e.valor ? 'btn-active btn-neutral' : ''}`}
                            >
                                {e.etiqueta}
                            </button>
                        ))}
                    </div>
                    <span className={`badge ${ESTATUS_DOSIER[dosier.estatus].clase}`}>
                        {ESTATUS_DOSIER[dosier.estatus].texto}
                        {dosier.entregado_at && ` · ${dosier.entregado_at}`}
                    </span>
                    {puedeEditar && (
                        <form
                            className="flex min-w-64 flex-1 gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                // El estatus viaja con la nota: se manda el vigente, no el del formulario al abrir.
                                notas.transform((d) => ({ ...d, estatus: dosier.estatus }));
                                notas.put(base, { preserveScroll: true });
                            }}
                        >
                            <input
                                className="input input-sm input-bordered min-w-0 flex-1"
                                placeholder="Notas internas del dosier"
                                maxLength={2000}
                                value={notas.data.notas}
                                onChange={(e) => notas.setData('notas', e.target.value)}
                            />
                            <Button type="submit" size="sm" variant="outline" disabled={!notas.isDirty || notas.processing}>
                                Guardar nota
                            </Button>
                        </form>
                    )}
                </div>

                {errors.dosier && <div className="alert alert-error py-2 text-sm">{errors.dosier}</div>}

                {fuera.length > 0 && (
                    <div className="alert alert-warning py-2 text-sm">
                        <FileWarningIcon className="size-4" />
                        <span>
                            {fuera.length} PDF no se pueden unir con el motor actual y quedarán fuera de la descarga (el índice los
                            nombra): {fuera.map((a) => a.nombre).join(' · ')}.
                        </span>
                    </div>
                )}

                <div className="grid gap-4 lg:grid-cols-[minmax(0,24rem)_minmax(0,1fr)]">
                    <div className="rounded-box border-base-300 border p-3">
                        <div className="mb-2 flex items-center justify-between">
                            <span className="text-sm font-semibold">Secciones</span>
                            {puedeEditar && !editando && (
                                <button type="button" className="btn btn-xs btn-ghost" onClick={() => setEditando(true)}>
                                    Editar secciones
                                </button>
                            )}
                        </div>
                        {editando ? (
                            <div className="space-y-3">
                                <EditorDeArbol valor={borrador} onChange={setBorrador} />
                                {errors.arbol && <div className="alert alert-error py-2 text-sm">{errors.arbol}</div>}
                                <div className="flex gap-2">
                                    <Button type="button" size="sm" onClick={guardarSecciones}>
                                        Guardar secciones
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => {
                                            setBorrador(editableDe(arbol));
                                            setEditando(false);
                                        }}
                                    >
                                        Cancelar
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <ArbolDeLectura
                                arbol={arbol}
                                elegida={elegida}
                                onElegir={(n) => setElegida(n.id)}
                                extra={(n) => {
                                    const cuantos = porSeccion.get(n.id)?.length ?? 0;

                                    return cuantos ? (
                                        <span className="badge badge-sm badge-primary shrink-0">{cuantos}</span>
                                    ) : (
                                        <span className="bg-base-300 mt-1.5 size-2 shrink-0 rounded-full" title="Sin PDF" />
                                    );
                                }}
                            />
                        )}
                    </div>

                    <div className="rounded-box border-base-300 border p-4">
                        {!seccion ? (
                            <div className="text-base-content/60 p-10 text-center text-sm">El dosier no tiene secciones.</div>
                        ) : (
                            <div className="space-y-4">
                                <div>
                                    <div className="text-lg font-semibold">
                                        <span className="text-base-content/60 mr-2 font-mono">{seccion.numero}</span>
                                        {seccion.titulo}
                                    </div>
                                    {seccion.nota && <p className="text-base-content/60 mt-1 text-sm">{seccion.nota}</p>}
                                </div>

                                {propios.length === 0 ? (
                                    <div className="text-base-content/50 rounded-md border border-dashed p-6 text-center text-sm">
                                        Esta sección todavía no tiene PDF.
                                    </div>
                                ) : (
                                    <ul className="divide-base-300 rounded-box border-base-300 divide-y border">
                                        {propios.map((a, i) => (
                                            <li key={a.id} className="flex flex-wrap items-center gap-2 px-3 py-2">
                                                <div className="min-w-0 flex-1">
                                                    <div className="truncate text-sm font-medium">{a.nombre}</div>
                                                    <div className="text-base-content/60 text-xs">
                                                        {formatBytes(a.size)}
                                                        {a.paginas !== null && ` · ${a.paginas} hoja(s)`} · {a.subio ?? '—'} · {a.fecha}
                                                    </div>
                                                </div>
                                                {!a.compatible && (
                                                    <span className="badge badge-sm badge-warning" title="El motor de unión actual no lo abre">
                                                        no se une
                                                    </span>
                                                )}
                                                <a href={a.url} target="_blank" rel="noreferrer" className="btn btn-ghost btn-xs" title="Ver">
                                                    <ExternalLinkIcon className="size-3.5" />
                                                </a>
                                                {puedeEditar && (
                                                    <>
                                                        <button type="button" className="btn btn-ghost btn-xs" disabled={i === 0} onClick={() => mover(i, -1)} title="Subir">
                                                            <ArrowUpIcon className="size-3.5" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            className="btn btn-ghost btn-xs"
                                                            disabled={i === propios.length - 1}
                                                            onClick={() => mover(i, 1)}
                                                            title="Bajar"
                                                        >
                                                            <ArrowDownIcon className="size-3.5" />
                                                        </button>
                                                        <button type="button" className="btn btn-ghost btn-xs text-error" onClick={() => quitar(a)} title="Quitar">
                                                            <Trash2Icon className="size-3.5" />
                                                        </button>
                                                    </>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                )}

                                {puedeEditar && (
                                    <div className="rounded-box border-base-300 space-y-2 border border-dashed p-3">
                                        <div className="text-sm font-medium">Subir PDF a esta sección</div>
                                        <input
                                            type="file"
                                            accept="application/pdf,.pdf"
                                            multiple
                                            className="file-input file-input-sm file-input-bordered w-full"
                                            onChange={(e) => subida.setData('archivos', Array.from(e.target.files ?? []).slice(0, limites.porEnvio))}
                                        />
                                        <p className="text-base-content/50 text-xs">
                                            Hasta {limites.porEnvio} PDF por envío, {limites.archivoMb} MB cada uno. Salen en la descarga en el
                                            orden de la lista.
                                        </p>
                                        {demasiadoGrandes.length > 0 && (
                                            <p className="text-error text-xs">
                                                Pesan más de {limites.archivoMb} MB: {demasiadoGrandes.map((f) => f.name).join(', ')}.
                                            </p>
                                        )}
                                        {Object.entries(subida.errors).map(([campo, mensaje]) => (
                                            <p key={campo} className="text-error text-xs">
                                                {mensaje}
                                            </p>
                                        ))}
                                        {subida.progress && <progress className="progress progress-primary w-full" value={subida.progress.percentage} max={100} />}
                                        <Button
                                            type="button"
                                            size="sm"
                                            onClick={subir}
                                            disabled={subida.processing || subida.data.archivos.length === 0 || demasiadoGrandes.length > 0}
                                        >
                                            <UploadIcon className="size-4" />
                                            Subir {subida.data.archivos.length || ''} PDF
                                        </Button>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
