/**
 * La ficha de un registro: lo único de la pantalla que enseña todo lo capturado.
 *
 * La tabla lista pocas columnas porque más no caben; aquí sale la inspección
 * entera, con los nombres que usa calidad al hablar, agrupada como el
 * formulario para que quien capturó reconozca lo que ve.
 *
 * Trae el historial de la pieza (RF-18.4): una pieza reinspeccionada es una
 * historia, no hechos sueltos, y desde cualquiera de sus inspecciones se llega
 * a las demás. Las acciones (corregir, reinspeccionar, borrar) salen de aquí
 * porque es donde se ve lo que se va a tocar.
 */

import { Link, router } from '@inertiajs/react';
import { XIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { useCan } from '@/hooks/use-can';
import { cn } from '@/lib/utils';
import { PastillaEstatus, PastillaVeredicto } from './ui';

type Fila = { etiqueta: string; valor: string };

export type FichaInspeccion = {
    id: number;
    folio: string;
    titulo: string;
    nota: string;
    estatus: string;
    cabecera: Fila[];
    puntos: { seccion: string; puntos: { etiqueta: string; valor: string | null; resultado: string | null }[] }[];
    defectos: { nombre: string; cantidad: number }[];
    muestreo: {
        tamano_lote: number;
        nivel: string;
        muestra: number;
        aceptacion: number;
        rechazo: number;
        conformes: number;
        rechazadas: number;
        veredicto: string | null;
        disposicion: string | null;
        detalle_fallas: string | null;
    } | null;
    juntas: {
        identificador: string;
        tipo: string;
        soldador: string | null;
        espesor_requerido_mm: string | null;
        espesor_medido_mm: string | null;
        espesor_cumple: boolean | null;
        es_empate: boolean;
        intento: number;
        resultado: string;
        defectos: string[];
    }[];
    pintura: {
        espesor_requerido_mils: string | null;
        metodo: string | null;
        area_m2: string | null;
        mediciones_visibles: number;
        promedio_mils: string | null;
        cumple: boolean | null;
        mediciones_bajas: number[];
        revision: string | null;
        accion: string | null;
        lecturas: { medicion: number; valores: (string | null)[] }[];
    } | null;
    adherencia: {
        metodo: string;
        resultado: string | null;
        tiras: string[];
        fotos: { id: number; nombre: string; url: string; esImagen: boolean }[];
    } | null;
    historial: { id: number; folio: string; etapa: string; numero_inspeccion: number; fecha: string; estatus: string }[];
    puedeReinspeccionar: boolean;
};

export type FichaDeSublote = {
    id: number;
    titulo: string;
    nota: string;
    veredicto: string | null;
    liberado: boolean;
    sinDisposicion: boolean;
    cabecera: Fila[];
    rechazadas: { unidad: number; defectos: string[] }[];
    historial: {
        id: number;
        numero_inspeccion: number;
        fecha: string;
        veredicto: string | null;
        muestra: number;
        rechazadas: number;
        disposicion: string | null;
    }[];
    puedeReinspeccionar: boolean;
    tieneReinspecciones: boolean;
};

function Cabecera({ titulo, nota, onCerrar, children }: { titulo: string; nota: string; onCerrar: () => void; children?: ReactNode }) {
    return (
        <div className="-m-6 mb-4 flex items-start justify-between gap-4 rounded-t-2xl bg-neutral px-5 py-4 text-neutral-content">
            <div>
                <h2 className="flex flex-wrap items-center gap-2 text-base font-bold">
                    {titulo}
                    {children}
                </h2>
                <p className="mt-0.5 text-xs opacity-70">{nota}</p>
            </div>
            <button
                type="button"
                onClick={onCerrar}
                aria-label="Cerrar"
                className="rounded-lg bg-neutral-content/15 p-1.5 hover:bg-neutral-content/25"
            >
                <XIcon className="size-4" />
            </button>
        </div>
    );
}

function Seccion({ titulo, children }: { titulo: string; children: ReactNode }) {
    return (
        <div className="mt-4">
            <h3 className="text-base-content/60 border-base-200 mb-1 border-b pb-1 text-xs font-semibold tracking-wider uppercase">
                {titulo}
            </h3>
            {children}
        </div>
    );
}

function TablaFilas({ filas }: { filas: Fila[] }) {
    return (
        <table className="w-full text-sm">
            <tbody>
                {filas.map((fila) => (
                    <tr key={fila.etiqueta} className="border-base-200 border-b last:border-0">
                        <td className="text-base-content/60 w-2/5 py-1.5 pr-3 align-top">{fila.etiqueta}</td>
                        <td className="py-1.5 font-semibold whitespace-pre-line">{fila.valor}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

const TONO_RESULTADO: Record<string, string> = {
    ok: 'text-success',
    no_ok: 'text-error',
    no_aplica: 'text-base-content/50',
};

function Acciones({ children }: { children: ReactNode }) {
    return <div className="mb-3 flex flex-wrap gap-2">{children}</div>;
}

export function FichaPieza({
    ficha,
    onIr,
    onCerrar,
}: {
    ficha: FichaInspeccion | null;
    onIr: (id: number) => void;
    onCerrar: () => void;
}) {
    const { can } = useCan();

    if (!ficha) {
        return null;
    }

    const borrar = () => {
        if (!window.confirm(`¿Borrar la inspección ${ficha.folio}? Se van también sus juntas, espesores y evidencia. No se puede deshacer.`)) {
            return;
        }
        router.delete(`/admin/calidad/inspecciones/${ficha.id}`);
    };

    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent className="max-h-[90vh] max-w-3xl overflow-y-auto">
                <Cabecera titulo={ficha.titulo} nota={ficha.nota} onCerrar={onCerrar}>
                    <PastillaEstatus estatus={ficha.estatus} />
                </Cabecera>

                <div className="border-base-300 bg-base-200/50 mb-4 rounded-xl border p-3">
                    <div className="text-base-content/60 mb-2 text-xs font-semibold">
                        Historial de la pieza · {ficha.historial.length}{' '}
                        {ficha.historial.length === 1 ? 'inspección' : 'inspecciones'}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {ficha.historial.map((previa) => (
                            <button
                                key={previa.id}
                                type="button"
                                onClick={() => onIr(previa.id)}
                                className={cn(
                                    'flex items-center gap-2 rounded-lg border px-2.5 py-1.5 text-xs transition-colors',
                                    previa.id === ficha.id
                                        ? 'border-primary bg-primary/10 font-semibold'
                                        : 'border-base-300 bg-base-100 hover:border-primary/50',
                                )}
                            >
                                <span>{previa.etapa}</span>
                                <span className="font-mono">#{previa.numero_inspeccion}</span>
                                <span className="text-base-content/60">{previa.fecha}</span>
                                <PastillaEstatus estatus={previa.estatus} />
                            </button>
                        ))}
                    </div>
                </div>

                <Acciones>
                    {ficha.puedeReinspeccionar && can('qal.inspecciones.crear') && (
                        <Link href={`/admin/calidad/inspecciones/${ficha.id}/reinspeccionar`} className="btn btn-sm btn-primary">
                            🔄 Reinspeccionar
                        </Link>
                    )}
                    {can('qal.inspecciones.editar') && (
                        <Link href={`/admin/calidad/inspecciones/${ficha.id}/edit`} className="btn btn-sm btn-outline">
                            ✏️ Corregir
                        </Link>
                    )}
                    {can('qal.inspecciones.eliminar') && (
                        <button type="button" onClick={borrar} className="btn btn-sm btn-ghost text-error">
                            🗑️ Borrar
                        </button>
                    )}
                </Acciones>

                <TablaFilas filas={ficha.cabecera} />

                {ficha.puntos.map((grupo) => (
                    <Seccion key={grupo.seccion} titulo={grupo.seccion}>
                        <table className="w-full text-sm">
                            <tbody>
                                {grupo.puntos.map((punto) => (
                                    <tr key={punto.etiqueta} className="border-base-200 border-b last:border-0">
                                        <td className="text-base-content/60 w-2/5 py-1.5 pr-3">{punto.etiqueta}</td>
                                        <td className={cn('py-1.5 font-semibold', punto.resultado && TONO_RESULTADO[punto.resultado])}>
                                            {punto.valor ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </Seccion>
                ))}

                {ficha.defectos.length > 0 && (
                    <Seccion titulo="Defectos">
                        <div className="flex flex-wrap gap-2 text-sm">
                            {ficha.defectos.map((defecto) => (
                                <span key={defecto.nombre} className="bg-error/10 text-error rounded-full px-3 py-1 font-semibold">
                                    {defecto.nombre}
                                    {defecto.cantidad > 1 && ` × ${defecto.cantidad}`}
                                </span>
                            ))}
                        </div>
                    </Seccion>
                )}

                {ficha.muestreo && (
                    <Seccion titulo="Muestreo del lote">
                        <TablaFilas
                            filas={[
                                { etiqueta: 'Lote', valor: `${ficha.muestreo.tamano_lote} piezas · nivel ${ficha.muestreo.nivel}` },
                                {
                                    etiqueta: 'Muestra',
                                    valor: `${ficha.muestreo.muestra} · acepta con ≤ ${ficha.muestreo.aceptacion}, rechaza con ≥ ${ficha.muestreo.rechazo}`,
                                },
                                { etiqueta: 'Conformes / rechazadas', valor: `${ficha.muestreo.conformes} / ${ficha.muestreo.rechazadas}` },
                                { etiqueta: 'Veredicto', valor: ficha.muestreo.veredicto?.toUpperCase() ?? 'En curso' },
                                ...(ficha.muestreo.disposicion ? [{ etiqueta: 'Disposición', valor: ficha.muestreo.disposicion }] : []),
                                ...(ficha.muestreo.detalle_fallas ? [{ etiqueta: 'Piezas con defecto', valor: ficha.muestreo.detalle_fallas }] : []),
                            ]}
                        />
                    </Seccion>
                )}

                {ficha.juntas.length > 0 && (
                    <Seccion titulo={`Mapeo de soldaduras · ${ficha.juntas.length} juntas`}>
                        <div className="overflow-x-auto">
                            <table className="table-xs table w-full">
                                <thead>
                                    <tr>
                                        <th>Junta</th>
                                        <th>Tipo</th>
                                        <th>Filete</th>
                                        <th>Soldador</th>
                                        <th>Intento</th>
                                        <th>Resultado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {ficha.juntas.map((junta) => (
                                        <tr key={junta.identificador}>
                                            <td className="font-semibold">
                                                {junta.identificador}
                                                {junta.es_empate && <span className="text-base-content/50 ml-1 text-xs">(empate)</span>}
                                            </td>
                                            <td>{junta.tipo}</td>
                                            <td className={junta.espesor_cumple === false ? 'text-error font-semibold' : ''}>
                                                {junta.espesor_medido_mm ? `${junta.espesor_medido_mm} / ${junta.espesor_requerido_mm ?? '?'} mm` : '—'}
                                            </td>
                                            <td>{junta.soldador ?? '—'}</td>
                                            <td className="font-mono">{junta.intento}</td>
                                            <td>
                                                {junta.resultado === 'con_defecto' ? (
                                                    <span className="text-error font-semibold">{junta.defectos.join(', ') || 'Con defecto'}</span>
                                                ) : (
                                                    <span className="text-success font-semibold">Correcta</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Seccion>
                )}

                {ficha.pintura && (
                    <Seccion titulo="Espesores de pintura">
                        <TablaFilas
                            filas={[
                                { etiqueta: 'Requerido', valor: ficha.pintura.espesor_requerido_mils ? `${ficha.pintura.espesor_requerido_mils} mils` : '—' },
                                {
                                    etiqueta: 'Promedio',
                                    valor: ficha.pintura.promedio_mils
                                        ? `${ficha.pintura.promedio_mils} mils · ${ficha.pintura.cumple === null ? 'sin requerido' : ficha.pintura.cumple ? 'cumple' : 'NO cumple'}`
                                        : '—',
                                },
                                ...(ficha.pintura.mediciones_bajas.length
                                    ? [{ etiqueta: 'Bajo el 80 %', valor: ficha.pintura.mediciones_bajas.map((m) => `Med ${m}`).join(', ') }]
                                    : []),
                                ...(ficha.pintura.metodo ? [{ etiqueta: 'Método', valor: ficha.pintura.metodo }] : []),
                                ...(ficha.pintura.area_m2 ? [{ etiqueta: 'Área', valor: `${ficha.pintura.area_m2} m²` }] : []),
                                ...(ficha.pintura.revision ? [{ etiqueta: 'Revisión', valor: ficha.pintura.revision }] : []),
                                ...(ficha.pintura.accion ? [{ etiqueta: 'Acción', valor: ficha.pintura.accion }] : []),
                            ]}
                        />
                        {ficha.pintura.lecturas.length > 0 && (
                            <div className="mt-2 overflow-x-auto">
                                <table className="table-xs table">
                                    <tbody>
                                        {[0, 1, 2].map((lectura) => (
                                            <tr key={lectura}>
                                                <th className="text-base-content/60">P{lectura + 1}</th>
                                                {ficha.pintura!.lecturas.map((medicion) => (
                                                    <td key={medicion.medicion} className="text-center font-mono">
                                                        {medicion.valores[lectura] ?? ''}
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Seccion>
                )}

                {ficha.adherencia && (
                    <Seccion titulo="Prueba de adherencia">
                        <TablaFilas
                            filas={[
                                { etiqueta: 'Método', valor: ficha.adherencia.metodo },
                                ...(ficha.adherencia.resultado ? [{ etiqueta: 'Resultado', valor: ficha.adherencia.resultado }] : []),
                                ...(ficha.adherencia.tiras.length ? [{ etiqueta: 'Tiras', valor: ficha.adherencia.tiras.join(' · ') }] : []),
                            ]}
                        />
                        {ficha.adherencia.fotos.length > 0 && (
                            <div className="mt-2 flex flex-wrap gap-2">
                                {ficha.adherencia.fotos.map((foto) => (
                                    <a key={foto.id} href={foto.url} target="_blank" rel="noreferrer" title={foto.nombre}>
                                        {foto.esImagen ? (
                                            <img src={foto.url} alt={foto.nombre} className="border-base-300 size-24 rounded-lg border object-cover" />
                                        ) : (
                                            <span className="border-base-300 bg-base-200 flex size-24 items-center justify-center rounded-lg border p-1 text-center text-[11px] break-all">
                                                📄 {foto.nombre}
                                            </span>
                                        )}
                                    </a>
                                ))}
                            </div>
                        )}
                    </Seccion>
                )}
            </DialogContent>
        </Dialog>
    );
}

export function FichaSublote({
    ficha,
    onIr,
    onCerrar,
}: {
    ficha: FichaDeSublote | null;
    onIr: (id: number) => void;
    onCerrar: () => void;
}) {
    const { can } = useCan();

    if (!ficha) {
        return null;
    }

    const borrar = () => {
        if (!window.confirm('¿Borrar esta inspección del sublote? No se puede deshacer.')) {
            return;
        }
        router.delete(`/admin/calidad/accesorios/sublotes/${ficha.id}`, { onSuccess: onCerrar });
    };

    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent className="max-h-[90vh] max-w-3xl overflow-y-auto">
                <Cabecera titulo={ficha.titulo} nota={ficha.nota} onCerrar={onCerrar}>
                    <PastillaVeredicto veredicto={ficha.veredicto} liberado={ficha.liberado} />
                    {ficha.sinDisposicion && <span className="badge badge-error badge-sm">sin disposición</span>}
                </Cabecera>

                {ficha.historial.length > 1 && (
                    <div className="border-base-300 bg-base-200/50 mb-4 rounded-xl border p-3">
                        <div className="text-base-content/60 mb-2 text-xs font-semibold">
                            Inspecciones de este sublote · {ficha.historial.length}
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {ficha.historial.map((previa) => (
                                <button
                                    key={previa.id}
                                    type="button"
                                    onClick={() => onIr(previa.id)}
                                    className={cn(
                                        'flex items-center gap-2 rounded-lg border px-2.5 py-1.5 text-xs transition-colors',
                                        previa.id === ficha.id
                                            ? 'border-primary bg-primary/10 font-semibold'
                                            : 'border-base-300 bg-base-100 hover:border-primary/50',
                                    )}
                                >
                                    <span className="font-mono">#{previa.numero_inspeccion}</span>
                                    <span className="text-base-content/60">{previa.fecha}</span>
                                    <span>{previa.rechazadas} rech.</span>
                                    <PastillaVeredicto veredicto={previa.veredicto} />
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                <Acciones>
                    {ficha.puedeReinspeccionar && can('qal.accesorios.crear') && (
                        <Link href={`/admin/calidad/accesorios/sublotes/${ficha.id}/reinspeccionar`} className="btn btn-sm btn-primary">
                            🔄 Reinspeccionar
                        </Link>
                    )}
                    {can('qal.accesorios.editar') && (
                        <Link href={`/admin/calidad/accesorios/sublotes/${ficha.id}/edit`} className="btn btn-sm btn-outline">
                            ✏️ Corregir
                        </Link>
                    )}
                    {can('qal.accesorios.eliminar') &&
                        (ficha.tieneReinspecciones ? (
                            <span className="text-base-content/60 self-center text-xs">
                                Tiene reinspecciones: se borran de la más reciente a la original.
                            </span>
                        ) : (
                            <button type="button" onClick={borrar} className="btn btn-sm btn-ghost text-error">
                                🗑️ Borrar
                            </button>
                        ))}
                </Acciones>

                <TablaFilas filas={ficha.cabecera} />

                {ficha.rechazadas.length > 0 && (
                    <Seccion titulo="Unidades rechazadas">
                        <ul className="space-y-1 text-sm">
                            {ficha.rechazadas.map((unidad) => (
                                <li key={unidad.unidad} className="bg-error/10 rounded-lg px-3 py-1.5">
                                    <b className="text-error">#{unidad.unidad}</b> {unidad.defectos.join(' · ')}
                                </li>
                            ))}
                        </ul>
                    </Seccion>
                )}
            </DialogContent>
        </Dialog>
    );
}
