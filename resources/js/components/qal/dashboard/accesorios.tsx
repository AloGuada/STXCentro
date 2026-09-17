/**
 * Accesorios — la pestaña del tablero con el avance de cada lote.
 *
 * Es el panel de lotes que la captura anterior tenía dentro de Registros:
 * cuánto de cada marca llegó, cuánto se liberó y qué está detenido. Aquí se
 * consulta y se decide; cada entrega se captura en Formularios, en modo «lote
 * de accesorios», y los botones de esta pestaña abren esa captura ya llenada.
 *
 * Tres reglas que la pestaña hace visibles porque son criterio:
 *
 *  - Cada sublote cuenta una vez, con su última inspección. Uno rechazado y
 *    después aceptado es material liberado, no una entrega doble.
 *  - Lo recibido que no se liberó está detenido: no cuenta como avance hasta
 *    reinspeccionarse o liberarse bajo concesión.
 *  - Un sublote rechazado sin disposición se señala en rojo: es material que
 *    nadie decidió qué hacer con él, y en una lista en blanco no se ve.
 */

import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDownIcon, ChevronRightIcon, PlusIcon } from 'lucide-react';
import { useState } from 'react';
import { Select, SelectItem } from '@/components/ui/select';
import { useCan } from '@/hooks/use-can';
import type { SharedData } from '@/types';

type InspeccionSublote = {
    id: number;
    numero_inspeccion: number;
    fecha: string;
    unidades: number;
    nivel: string;
    muestra: number;
    conformes: number;
    rechazadas: number;
    veredicto: 'aceptado' | 'rechazado' | null;
    disposicion: string | null;
    liberado: boolean;
    sin_disposicion: boolean;
    inspector: string | null;
    modulo: string | null;
    linea: string | null;
    defectos: string;
};

type Lote = {
    id: number;
    marca: string;
    descripcion: string | null;
    obra: string | null;
    total_unidades: number;
    kg_unitario: string | null;
    elementos_unitarios: number | null;
    avance: {
        recibidas: number;
        liberadas: number;
        detenidas: number;
        sublotes: number;
        sin_disposicion: number;
        inspeccionadas: number;
        rechazadas: number;
    };
    /** Cada sublote físico con sus inspecciones, de la primera a la última. */
    grupos: InspeccionSublote[][];
};

export type DatosAccesorios = {
    obras: { id: number; no: string | null; descripcion: string | null }[];
    obraId: number | null;
    lotes: Lote[];
};

const numero = (valor: number) => valor.toLocaleString('es-MX');

export function TabAccesorios({ datos, onObra }: { datos: DatosAccesorios; onObra: (obra: string) => void }) {
    const { props } = usePage<SharedData & { flash?: { success?: string | null } }>();
    const error = (props.errors as Record<string, string> | undefined)?.sublote;
    const exito = props.flash?.success;
    const { obras, obraId, lotes } = datos;

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <p className="text-base-content/60 text-sm">
                    Cuánto de cada marca llegó, cuánto se liberó y qué está detenido. Cada entrega se captura en
                    Formularios.
                </p>

                <label className="flex flex-col gap-1">
                    <span className="text-base-content/60 text-xs font-medium">Obra</span>
                    <Select value={obraId ? String(obraId) : ''} onValueChange={onObra} className="select-sm w-64">
                        <SelectItem value="">Todas</SelectItem>
                        {obras.map((obra) => (
                            <SelectItem key={obra.id} value={String(obra.id)}>
                                {[obra.no, obra.descripcion].filter(Boolean).join(' — ')}
                            </SelectItem>
                        ))}
                    </Select>
                </label>
            </div>

            {exito && <div className="alert alert-success text-sm">{exito}</div>}
            {error && <div className="alert alert-error text-sm">{error}</div>}

            {lotes.length === 0 ? (
                <div className="border-base-300 bg-base-100 text-base-content/60 rounded-xl border p-10 text-center">
                    Todavía no hay lotes de accesorios{obraId ? ' en esta obra' : ''}. El lote nace con su primera
                    entrega, desde Formularios.
                </div>
            ) : (
                lotes.map((lote) => <TarjetaLote key={lote.id} lote={lote} />)
            )}
        </div>
    );
}

function TarjetaLote({ lote }: { lote: Lote }) {
    const { can } = useCan();
    const [abierto, setAbierto] = useState(false);
    const { avance } = lote;

    const total = Math.max(lote.total_unidades, 1);
    const liberado = Math.min(100, (avance.liberadas / total) * 100);
    const detenido = Math.min(100 - liberado, (avance.detenidas / total) * 100);
    const faltan = Math.max(0, lote.total_unidades - avance.recibidas);

    // Las unidades de cada sublote, contadas en el orden en que llegaron: así
    // se habla de «las unidades 301 a 600» y no de un número de sublote suelto.
    const rangos = lote.grupos.reduce<[number, number][]>((acumulado, grupo) => {
        const inicio = acumulado.length ? acumulado[acumulado.length - 1][1] + 1 : 1;
        return [...acumulado, [inicio, inicio + grupo[grupo.length - 1].unidades - 1]];
    }, []);

    return (
        <section className="border-base-300 bg-base-100 rounded-xl border">
            <div className="flex flex-wrap items-start justify-between gap-3 p-4">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-lg font-bold">{lote.marca}</h2>
                        {lote.obra && <span className="badge badge-ghost badge-sm">{lote.obra}</span>}
                        {avance.sin_disposicion > 0 && (
                            <span className="badge badge-error badge-sm">{avance.sin_disposicion} sin disposición</span>
                        )}
                    </div>
                    <p className="text-base-content/60 text-sm">
                        {[
                            lote.descripcion,
                            lote.kg_unitario && `${Number(lote.kg_unitario)} kg por unidad`,
                            lote.elementos_unitarios && `${lote.elementos_unitarios} elementos por unidad`,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                </div>
                {can('qal.accesorios.crear') && (
                    <Link href={`/admin/calidad/accesorios/lotes/${lote.id}/sublote`} className="btn btn-sm btn-primary">
                        <PlusIcon className="size-4" />
                        Nueva entrega
                    </Link>
                )}
            </div>

            <div className="px-4">
                <div className="bg-base-200 flex h-3 overflow-hidden rounded-full">
                    <div className="bg-success h-full" style={{ width: `${liberado}%` }} />
                    <div className="bg-error h-full" style={{ width: `${detenido}%` }} />
                </div>
                <div className="text-base-content/70 mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm">
                    <span>
                        <b className="text-success">{numero(avance.liberadas)}</b> liberadas
                    </span>
                    <span>
                        <b className={avance.detenidas ? 'text-error' : ''}>{numero(avance.detenidas)}</b> detenidas
                    </span>
                    <span>
                        <b>{numero(avance.recibidas)}</b> de {numero(lote.total_unidades)} recibidas
                    </span>
                    <span>
                        {numero(avance.inspeccionadas)} inspeccionadas en muestra · {numero(avance.rechazadas)} rechazadas
                    </span>
                </div>
            </div>

            <button
                type="button"
                onClick={() => setAbierto(!abierto)}
                className="text-primary flex items-center gap-1 px-4 py-3 text-sm font-semibold"
            >
                {abierto ? <ChevronDownIcon className="size-4" /> : <ChevronRightIcon className="size-4" />}
                {abierto ? 'Ocultar sublotes' : `Ver ${avance.sublotes} ${avance.sublotes === 1 ? 'sublote' : 'sublotes'}`}
            </button>

            {abierto && (
                <div className="border-base-300 space-y-2 border-t p-4">
                    {lote.grupos.map((grupo, indice) => (
                        <GrupoSublote key={grupo[0].id} grupo={grupo} numero={indice + 1} rango={rangos[indice]} />
                    ))}

                    {faltan > 0 && (
                        <p className="text-base-content/60 text-sm">
                            Faltan <b>{numero(faltan)}</b> unidades por recibir e inspeccionar.
                        </p>
                    )}
                    {avance.detenidas > 0 && (
                        <p className="text-error text-sm">
                            <b>{numero(avance.detenidas)}</b> unidades ya recibidas están en sublotes rechazados: no
                            cuentan como liberadas hasta que se reinspeccionen y pasen, o se liberen bajo concesión.
                        </p>
                    )}
                </div>
            )}
        </section>
    );
}

function Veredicto({ inspeccion }: { inspeccion: InspeccionSublote }) {
    if (inspeccion.veredicto === null) {
        return <span className="badge badge-warning badge-sm font-semibold">EN CURSO</span>;
    }

    return (
        <span className={`badge badge-sm font-semibold ${inspeccion.veredicto === 'aceptado' ? 'badge-success' : 'badge-error'}`}>
            {inspeccion.veredicto.toUpperCase()}
        </span>
    );
}

function GrupoSublote({
    grupo,
    numero: posicion,
    rango,
}: {
    grupo: InspeccionSublote[];
    numero: number;
    rango: [number, number];
}) {
    const { can } = useCan();
    const [historial, setHistorial] = useState(false);
    const ultima = grupo[grupo.length - 1];
    const rechazado = ultima.veredicto === 'rechazado';

    const borrar = (inspeccion: InspeccionSublote) => {
        if (!window.confirm(`¿Borrar la inspección ${inspeccion.numero_inspeccion} de este sublote? No se puede deshacer.`)) {
            return;
        }
        router.delete(`/admin/calidad/accesorios/sublotes/${inspeccion.id}`, { preserveScroll: true });
    };

    return (
        <div className={`rounded-lg border p-3 ${rechazado && !ultima.liberado ? 'border-error/40 bg-error/5' : 'border-base-300'}`}>
            <div className="flex flex-wrap items-center gap-2 text-sm font-semibold">
                <span>
                    Sublote #{posicion} · unidades {numero(rango[0])}–{numero(rango[1])}
                </span>
                <Veredicto inspeccion={ultima} />
                {grupo.length > 1 && (
                    <span className="badge badge-ghost badge-sm">
                        inspección {ultima.numero_inspeccion} de {grupo.length}
                    </span>
                )}
                {rechazado && ultima.liberado && <span className="badge badge-info badge-sm">liberado bajo concesión</span>}
                {ultima.sin_disposicion && <span className="badge badge-error badge-sm">sin disposición</span>}
            </div>

            <div className="text-base-content/70 mt-1 text-xs leading-relaxed">
                {[ultima.fecha, ultima.inspector, ultima.modulo && `Módulo ${ultima.modulo}`, ultima.linea && `Línea ${ultima.linea}`]
                    .filter(Boolean)
                    .join(' · ')}
                <br />
                Nivel {ultima.nivel} · muestra {ultima.muestra} · {ultima.conformes} conformes ·{' '}
                <b>{ultima.rechazadas} rechazadas</b>
                {ultima.defectos && (
                    <>
                        <br />
                        {ultima.defectos}
                    </>
                )}
                {rechazado && (
                    <>
                        <br />
                        Disposición: <b>{ultima.disposicion || 'sin definir'}</b>
                    </>
                )}
            </div>

            <div className="mt-2 flex flex-wrap gap-2">
                {rechazado && !ultima.liberado && can('qal.accesorios.crear') && (
                    <Link href={`/admin/calidad/accesorios/sublotes/${ultima.id}/reinspeccionar`} className="btn btn-xs btn-primary">
                        🔄 Reinspeccionar
                    </Link>
                )}
                {can('qal.accesorios.editar') && (
                    <Link href={`/admin/calidad/accesorios/sublotes/${ultima.id}/edit`} className="btn btn-xs btn-outline">
                        ✏️ Editar
                    </Link>
                )}
                {can('qal.registros.ver') && (
                    <Link href={`/admin/calidad/registros?que=acc&sublote=${ultima.id}`} className="btn btn-xs btn-ghost">
                        Ver ficha
                    </Link>
                )}
                {grupo.length > 1 && (
                    <button type="button" onClick={() => setHistorial(!historial)} className="btn btn-xs btn-ghost">
                        🕓 {historial ? 'Ocultar' : `Ver las ${grupo.length}`} inspecciones
                    </button>
                )}
                {can('qal.accesorios.eliminar') && (
                    <button type="button" onClick={() => borrar(ultima)} className="btn btn-xs btn-ghost text-error">
                        🗑️ Borrar
                    </button>
                )}
            </div>

            {historial && (
                <ul className="border-base-300 mt-2 space-y-1 border-t border-dashed pt-2 text-xs">
                    {grupo.map((inspeccion) => (
                        <li key={inspeccion.id} className="text-base-content/70 flex flex-wrap items-center gap-2">
                            <b className="text-base-content">Inspección {inspeccion.numero_inspeccion}</b>
                            <span>{inspeccion.fecha}</span>
                            <span>{inspeccion.inspector}</span>
                            <span>
                                muestra {inspeccion.muestra} · {inspeccion.rechazadas} rechazadas
                            </span>
                            <Veredicto inspeccion={inspeccion} />
                            {inspeccion.disposicion && <span>{inspeccion.disposicion}</span>}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
