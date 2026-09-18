/**
 * El panel de una junta — captura de un solo renglón del mapeo.
 *
 * La matriz completa son 24 columnas: llenarla celda por celda obliga a
 * scrollear a lo ancho y a perder de vista qué junta se está contestando. El
 * panel enseña **una** junta, con todas sus columnas en vertical y al lado de
 * la pieza en el visor, que es como se inspecciona en el piso: se mira la
 * junta en el modelo, se contesta, se pasa a la siguiente.
 *
 * Los 18 puntos van en dos bloques porque se revisan en dos momentos: primero
 * cómo se preparó la junta, después qué se ve en el cordón ya depositado.
 */

import { cn } from '@/lib/utils';
import { PUNTOS_MAPEO, PUNTOS_PREPARACION, TIPOS_JUNTA } from './datos';
import type { Junta } from './estado';
import { evaluarFilete } from './reglas';
import { Pastilla } from './ui';

/** Lo que admite un punto: valor, glifo y su color cuando está elegido. */
const RESPUESTAS: [string, string, string][] = [
    ['OK', '✓', 'border-success bg-success/15 text-success'],
    ['Defecto', '✗', 'border-error bg-error/15 text-error'],
    ['n/a', '–', 'border-base-300 bg-base-200 text-base-content/60'],
];

const APAGADO = 'border-base-300 bg-base-100 text-base-content/25';

function Renglon({ etiqueta, children }: { etiqueta: string; children: React.ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-3 border-b border-base-200 py-1.5 last:border-0">
            <span className="text-[13px] leading-tight">{etiqueta}</span>
            <div className="shrink-0">{children}</div>
        </div>
    );
}

function Punto({ etiqueta, valor, onCambio }: { etiqueta: string; valor: string; onCambio: (valor: string) => void }) {
    return (
        <Renglon etiqueta={etiqueta}>
            <div className="flex gap-1">
                {RESPUESTAS.map(([respuesta, glifo, tono]) => (
                    <button
                        key={respuesta}
                        type="button"
                        // Volver a tocar la respuesta elegida la deshace: sin ella el
                        // punto quedaría contestado para siempre a la primera.
                        onClick={() => onCambio(valor === respuesta ? '' : respuesta)}
                        title={`${etiqueta}: ${respuesta}`}
                        className={cn(
                            'size-8 rounded-lg border-2 text-sm font-extrabold',
                            valor === respuesta ? tono : APAGADO,
                        )}
                    >
                        {glifo}
                    </button>
                ))}
            </div>
        </Renglon>
    );
}

function Bloque({ titulo, children }: { titulo: string; children: React.ReactNode }) {
    return (
        <div className="mt-3">
            <div className="mb-1 text-[11px] font-bold tracking-[.5px] text-primary uppercase">{titulo}</div>
            {children}
        </div>
    );
}

export function PanelJunta({
    etiqueta,
    esDelModelo,
    junta,
    soldadores,
    indice,
    total,
    onIr,
    onCambio,
    onTipo,
    onMedida,
    onPunto,
    onTodoOk,
    onLimpiar,
    puedeLimpiar,
    estado,
}: {
    /** Cómo se llama la junta: el identificador del cordón, o lo tecleado. */
    etiqueta: string;
    /** Del modelo 3D no se renombra: su identificador lo manda el IFC. */
    esDelModelo: boolean;
    junta: Junta;
    soldadores: [string, string][];
    /** Posición en el recorrido, base 0. */
    indice: number;
    total: number;
    onIr: (indice: number) => void;
    onCambio: (cambios: Partial<Junta>) => void;
    onTipo: (tipo: string) => void;
    onMedida: (campo: 'espesorRequerido' | 'espesorMedido', valor: string) => void;
    onPunto: (clave: string, valor: string) => void;
    onTodoOk: () => void;
    onLimpiar: () => void;
    puedeLimpiar: boolean;
    estado: 'Por revisar' | 'Con defecto' | 'Junta Correcta';
}) {
    const esFilete = junta.tipo === 'Filete';
    const medida = evaluarFilete(junta.espesorRequerido, junta.espesorMedido);
    const preparacion = PUNTOS_MAPEO.filter(([clave]) => PUNTOS_PREPARACION.has(clave));
    const discontinuidades = PUNTOS_MAPEO.filter(([clave]) => !PUNTOS_PREPARACION.has(clave));

    return (
        <div className="flex max-h-[calc(100vh-2rem)] flex-col overflow-hidden rounded-box border border-base-300 bg-base-100">
            {/* La cabecera no scrollea: el número de junta y el paso al siguiente
                son lo que se está mirando todo el rato. */}
            <div className="border-b border-base-300 p-3">
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => onIr(indice - 1)}
                        disabled={indice <= 0}
                        title="Junta anterior"
                        className="h-10 w-11 shrink-0 rounded-lg border-2 border-base-300 text-lg font-extrabold disabled:opacity-30"
                    >
                        ‹
                    </button>

                    <div className="min-w-0 flex-1 text-center">
                        {esDelModelo ? (
                            <div className="truncate text-xl leading-tight font-extrabold text-primary">{etiqueta}</div>
                        ) : (
                            <input
                                value={junta.junta}
                                onChange={(evento) => onCambio({ junta: evento.target.value.toUpperCase() })}
                                placeholder="J1"
                                className="input input-bordered input-sm w-24 text-center text-lg font-extrabold"
                            />
                        )}
                        <div className="text-[11px] text-base-content/60">
                            Junta {indice + 1} de {total}
                            {esDelModelo && <span className="ml-1 text-info">· 3D</span>}
                        </div>
                    </div>

                    <button
                        type="button"
                        onClick={() => onIr(indice + 1)}
                        disabled={indice >= total - 1}
                        title="Junta siguiente"
                        className="h-10 w-11 shrink-0 rounded-lg border-2 border-base-300 text-lg font-extrabold disabled:opacity-30"
                    >
                        ›
                    </button>
                </div>

                <div className="mt-2 flex items-center gap-2">
                    <Pastilla tono={estado === 'Con defecto' ? 'rej' : estado === 'Por revisar' ? 'pen' : 'lib'}>
                        {estado}
                    </Pastilla>
                    <div className="ml-auto flex gap-1">
                        <button
                            type="button"
                            onClick={onTodoOk}
                            title="Marcar todos los puntos de esta junta en OK"
                            className="h-8 rounded-lg border-2 border-success bg-success/10 px-2 text-xs font-bold text-success"
                        >
                            ✓ Todo OK
                        </button>
                        <button
                            type="button"
                            onClick={onLimpiar}
                            disabled={!puedeLimpiar}
                            title={esDelModelo ? 'Dejarla por revisar' : 'Quitar el renglón'}
                            className="h-8 w-9 rounded-lg border-2 border-base-300 disabled:opacity-30"
                        >
                            🗑️
                        </button>
                    </div>
                </div>
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto p-3">
                <Renglon etiqueta="Tipo">
                    <select
                        value={junta.tipo}
                        onChange={(evento) => onTipo(evento.target.value)}
                        className="select select-bordered select-sm w-28 text-[13px]"
                    >
                        <option value="">—</option>
                        {TIPOS_JUNTA.map((tipo) => (
                            <option key={tipo} value={tipo}>
                                {tipo}
                            </option>
                        ))}
                    </select>
                </Renglon>

                <Renglon etiqueta="Soldador">
                    <select
                        value={junta.soldador}
                        onChange={(evento) => onCambio({ soldador: evento.target.value })}
                        className="select select-bordered select-sm w-44 text-[13px]"
                    >
                        <option value="">—</option>
                        {soldadores.map(([id, nombre]) => (
                            <option key={id} value={id}>
                                {nombre}
                            </option>
                        ))}
                    </select>
                </Renglon>

                <Renglon etiqueta="Empate">
                    <button
                        type="button"
                        onClick={() => onCambio({ esEmpate: !junta.esEmpate })}
                        title="Une dos tramos del mismo miembro"
                        className={cn(
                            'h-8 w-20 rounded-lg border-2 text-xs font-bold',
                            junta.esEmpate
                                ? 'border-primary bg-primary text-primary-content'
                                : 'border-base-300 bg-base-100 text-base-content/50',
                        )}
                    >
                        {junta.esEmpate ? 'Empate' : 'No'}
                    </button>
                </Renglon>

                {/* El tamaño del filete sólo aplica al filete; en ranura ni se pregunta. */}
                {esFilete && (
                    <Bloque titulo="Tamaño del filete">
                        <div className="flex gap-2">
                            <label className="flex-1">
                                <span className="text-[11px] text-base-content/60">Requerido (plano)</span>
                                <input
                                    type="number"
                                    step="0.5"
                                    inputMode="decimal"
                                    value={junta.espesorRequerido}
                                    onChange={(evento) => onMedida('espesorRequerido', evento.target.value)}
                                    placeholder="mm"
                                    className="input input-bordered input-sm w-full text-[13px]"
                                />
                            </label>
                            <label className="flex-1">
                                <span className="text-[11px] text-base-content/60">Medido</span>
                                <input
                                    type="number"
                                    step="0.5"
                                    inputMode="decimal"
                                    value={junta.espesorMedido}
                                    onChange={(evento) => onMedida('espesorMedido', evento.target.value)}
                                    placeholder="mm"
                                    className="input input-bordered input-sm w-full text-[13px]"
                                />
                            </label>
                        </div>
                        {medida && (
                            <p className={cn('mt-1 text-xs', medida.cumple ? 'text-success' : 'text-error')}>
                                {medida.mensaje}
                            </p>
                        )}
                    </Bloque>
                )}

                <Bloque titulo="Preparación y proceso">
                    {preparacion.map(([clave, texto]) => (
                        <Punto
                            key={clave}
                            etiqueta={texto}
                            valor={junta.puntos[clave] ?? ''}
                            onCambio={(valor) => onPunto(clave, valor)}
                        />
                    ))}
                </Bloque>

                <Bloque titulo="Discontinuidades del cordón">
                    {discontinuidades.map(([clave, texto]) => (
                        <Punto
                            key={clave}
                            etiqueta={texto}
                            valor={junta.puntos[clave] ?? ''}
                            onCambio={(valor) => onPunto(clave, valor)}
                        />
                    ))}
                </Bloque>
            </div>
        </div>
    );
}
