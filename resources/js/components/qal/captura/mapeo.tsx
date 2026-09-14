/**
 * Mapeo de soldaduras — la matriz de juntas de la pieza.
 *
 * Las juntas son los cordones que trae el modelo 3D de la marca: el renglón
 * existe desde que se abre el mapeo y se llena **ahí mismo**, celda por celda,
 * como el formato de papel. No hay sub-formulario aparte ni botón de «añadir»:
 * tocar una celda crea la junta, y todas se guardan al registrar la pieza —que
 * era el tropiezo de antes, una junta a medio llenar se perdía al guardar.
 *
 * Sin modelo —o para una junta que el modelo no trae— se añade un renglón a
 * mano y se numera en su propia celda.
 *
 * Elegir un renglón elige el cordón en el visor, que lo encuadra. Es el mismo
 * estado en los dos sentidos: tocar el cordón en el visor trae aquí su renglón.
 */

import { useEffect, useRef, type ReactNode } from 'react';
import { cn } from '@/lib/utils';
import type { CordonVisor } from '../juntas3d/tipos';
import { PUNTOS_MAPEO } from './datos';
import type { Junta } from './estado';
import { evaluarFilete, PUNTO_PERFIL } from './reglas';
import { Boton, Pastilla, Pista, Tarjeta } from './ui';

const TIPOS = ['Filete', 'Ranura'];

/** El cordón del modelo dicho como lo nombra el formato de mapeo. */
const TIPO_CORDON: Record<CordonVisor['tipo'], string> = { filete: 'Filete', costura: 'Ranura' };

/** Lo que admite un punto, en el orden en que rota su celda al tocarla. */
const RESPUESTAS = ['', 'OK', 'Defecto', 'n/a'];

const GLIFO: Record<string, string> = { '': '·', OK: '✓', Defecto: '✗', 'n/a': '–' };

const TONO_PUNTO: Record<string, string> = {
    '': 'border-base-300 bg-base-100 text-base-content/40',
    OK: 'border-success bg-success/15 text-success',
    Defecto: 'border-error bg-error/15 text-error',
    'n/a': 'border-base-300 bg-base-200 text-base-content/50',
};

const CELDA = 'border-b border-base-300 px-1.5 py-1 align-middle';

/**
 * Encabezado pegado arriba. La línea va como sombra y no como borde: un borde
 * de tabla colapsada se pierde al scrollear una celda `sticky`.
 */
const ENCABEZADO = 'sticky top-0 z-20 bg-base-100 px-1.5 py-2 shadow-[inset_0_-1px_0_0_var(--color-base-300)]';

export const JUNTA_VACIA: Junta = {
    junta: '',
    tipo: '',
    soldador: '',
    esEmpate: false,
    cordonId: null,
    puntos: {},
    espesorRequerido: '',
    espesorMedido: '',
};

/** Con que un punto salga "Defecto", la junta entera queda con defecto. */
export function estadoJunta(junta: Junta): 'Con defecto' | 'Junta Correcta' {
    return Object.values(junta.puntos).includes('Defecto') ? 'Con defecto' : 'Junta Correcta';
}

/** Un renglón existe desde el principio; sin una sola respuesta no está revisado. */
function sinLlenar(junta: Junta): boolean {
    return !Object.values(junta.puntos).some((valor) => valor === 'OK' || valor === 'Defecto');
}

/**
 * Un renglón de la matriz: un cordón del modelo —con su junta si ya se
 * empezó— o una junta numerada a mano.
 */
type Renglon = {
    clave: string;
    etiqueta: string;
    tipo: string;
    cordon: CordonVisor | null;
    junta: Junta | null;
    /** Su posición en `juntas`, para escribir sobre ella o quitarla. */
    indice: number | null;
};

export function Mapeo({
    juntas,
    onJuntas,
    soldadores,
    onAviso,
    modelo3d,
    cordones = [],
    seleccionado = null,
    onSeleccionar,
}: {
    juntas: Junta[];
    onJuntas: (juntas: Junta[]) => void;
    /** [id, «NOMBRE (CLAVE)»] del padrón. */
    soldadores: [string, string][];
    onAviso: (mensaje: string, tono?: 'ok' | 'error') => void;
    /** El visor de la pieza con sus cordones, o por qué no lo hay. Va arriba de la matriz. */
    modelo3d?: ReactNode;
    /** Los cordones que trae la marca en el modelo 3D: son las juntas de la pieza. */
    cordones?: CordonVisor[];
    /** El cordón elegido, compartido con el visor. */
    seleccionado?: number | null;
    onSeleccionar?: (id: number | null) => void;
}) {
    const renglonRef = useRef<Record<number, HTMLTableRowElement | null>>({});

    // Elegir un cordón en el visor trae su renglón a la vista: la matriz es
    // ancha y el renglón puede estar fuera de la pantalla.
    useEffect(() => {
        if (seleccionado !== null) {
            renglonRef.current[seleccionado]?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        }
    }, [seleccionado]);

    const nombreSoldador = (id: string) => soldadores.find(([clave]) => clave === id)?.[1] ?? '';

    /**
     * La matriz la manda el modelo: un renglón por cordón de la marca, con la
     * junta que lleva en esta inspección si ya se empezó. Lo numerado a mano
     * —y las juntas de una pieza sin modelo— va al final, que también se guarda.
     */
    const deCordones: Renglon[] = cordones.map((cordon) => {
        const indice = juntas.findIndex((junta) => junta.cordonId === cordon.id);
        const junta = indice >= 0 ? juntas[indice] : null;
        return {
            clave: `cordon-${cordon.id}`,
            etiqueta: cordon.identificador,
            tipo: junta?.tipo || TIPO_CORDON[cordon.tipo],
            cordon,
            junta,
            indice: indice >= 0 ? indice : null,
        };
    });

    const aMano: Renglon[] = juntas.flatMap((junta, indice) =>
        cordones.some((cordon) => cordon.id === junta.cordonId)
            ? []
            : [{ clave: `mano-${indice}`, etiqueta: junta.junta, tipo: junta.tipo, cordon: null, junta, indice }],
    );

    const renglones = [...deCordones, ...aMano];
    const empezadas = juntas.filter((junta) => !sinLlenar(junta));
    const conDefecto = empezadas.filter((junta) => estadoJunta(junta) === 'Con defecto').length;
    const porRevisar = renglones.length - empezadas.length;

    /** La junta del renglón, o la que le toca si todavía no existe. */
    const juntaDe = (renglon: Renglon): Junta =>
        renglon.junta ?? {
            ...JUNTA_VACIA,
            junta: renglon.cordon?.identificador ?? '',
            tipo: renglon.cordon ? TIPO_CORDON[renglon.cordon.tipo] : '',
            cordonId: renglon.cordon?.id ?? null,
        };

    /** Escribir en un renglón crea su junta la primera vez. */
    const escribir = (renglon: Renglon, cambios: Partial<Junta>) => {
        const junta = { ...juntaDe(renglon), ...cambios };
        onJuntas(
            renglon.indice !== null ? juntas.map((previa, i) => (i === renglon.indice ? junta : previa)) : [...juntas, junta],
        );
    };

    const escribirPunto = (renglon: Renglon, clave: string, valor: string) =>
        escribir(renglon, { puntos: { ...juntaDe(renglon).puntos, [clave]: valor } });

    /** La celda rota entre las respuestas en vez de abrir un desplegable. */
    const rotarPunto = (renglon: Renglon, clave: string) => {
        const actual = juntaDe(renglon).puntos[clave] ?? '';
        escribirPunto(renglon, clave, RESPUESTAS[(RESPUESTAS.indexOf(actual) + 1) % RESPUESTAS.length]);
    };

    const cambiarTipo = (renglon: Renglon, tipo: string) => {
        const junta = juntaDe(renglon);
        escribir(renglon, {
            tipo,
            puntos: {
                ...junta.puntos,
                // El tipo de junta decide qué preparación aplica: la otra no se revisa.
                ...(tipo === 'Filete' ? { m_prepranura: 'n/a' } : {}),
                ...(tipo === 'Ranura' ? { m_prepfilete: 'n/a' } : {}),
            },
            espesorRequerido: tipo === 'Filete' ? junta.espesorRequerido : '',
            espesorMedido: tipo === 'Filete' ? junta.espesorMedido : '',
        });
    };

    const medirFilete = (renglon: Renglon, campo: 'espesorRequerido' | 'espesorMedido', valor: string) => {
        const junta = { ...juntaDe(renglon), [campo]: valor };
        const resultado = evaluarFilete(junta.espesorRequerido, junta.espesorMedido);
        escribir(renglon, {
            [campo]: valor,
            // Un filete por debajo del nominal es un defecto de perfil, y se marca solo.
            ...(resultado && !resultado.cumple ? { puntos: { ...junta.puntos, [PUNTO_PERFIL]: 'Defecto' } } : {}),
        });
    };

    const todoOk = (renglon: Renglon) => {
        const junta = juntaDe(renglon);
        const puntos: Record<string, string> = {};
        PUNTOS_MAPEO.forEach(([clave]) => (puntos[clave] = 'OK'));
        escribir(renglon, {
            puntos: {
                ...puntos,
                ...(junta.tipo === 'Filete' ? { m_prepranura: 'n/a' } : {}),
                ...(junta.tipo === 'Ranura' ? { m_prepfilete: 'n/a' } : {}),
            },
        });
        onAviso(`Junta ${junta.junta || 'sin número'} marcada correcta — ajusta lo que no cumpla`);
    };

    /** Limpiar deja el renglón del cordón en «por revisar»; el de mano desaparece. */
    const limpiar = (renglon: Renglon) => {
        if (renglon.indice === null) {
            return;
        }
        onJuntas(juntas.filter((_, i) => i !== renglon.indice));
    };

    const anadirAMano = () => {
        onJuntas([...juntas, { ...JUNTA_VACIA }]);
        onAviso('Renglón añadido: ponle número y tipo');
    };

    return (
        <Tarjeta titulo="Mapeo de soldaduras · junta por junta">
            <Pista>
                Las juntas son los <b>cordones del modelo</b>: cada renglón se llena en la misma tabla. Toca una celda
                para rotar entre <b>✓ OK</b>, <b>✗ defecto</b> y <b>– n/a</b>. Todo se guarda al pulsar Guardar, con el
                resto de la pieza.
            </Pista>

            {modelo3d && <div className="mb-4">{modelo3d}</div>}

            {renglones.length === 0 ? (
                <Pista className="mb-0">
                    La pieza no trae cordones del modelo 3D. Añade el renglón de la junta a mano y numérala en su celda.
                </Pista>
            ) : (
                <>
                    <div className="my-2 flex flex-wrap items-baseline gap-x-2 font-bold text-primary">
                        <span>
                            {cordones.length > 0 ? 'Juntas del modelo' : 'Juntas de esta pieza'} ({empezadas.length} de{' '}
                            {renglones.length})
                        </span>
                        {conDefecto > 0 && <span className="text-error">· {conDefecto} con defecto</span>}
                        {porRevisar > 0 && <span className="text-warning">· {porRevisar} por revisar</span>}
                    </div>

                    {/* La matriz es ancha (18 puntos) y larga (un renglón por cordón):
                        el encabezado y la columna de la junta se quedan fijos para no
                        perder de vista qué se está respondiendo. */}
                    <div className="max-h-[80vh] overflow-auto rounded-box border border-base-300">
                        {/* `w-full` sobre el ancho natural: si la pantalla da, las columnas
                            se reparten el sobrante; si no, la tabla manda y se scrollea. */}
                        <table className="w-full border-collapse text-[13px]">
                            <thead>
                                <tr className="text-[11px] tracking-[.5px] text-primary uppercase">
                                    <th className={cn(ENCABEZADO, 'sticky left-0 z-30 px-2 text-left')}>Junta</th>
                                    <th className={cn(ENCABEZADO, 'text-left')}>Tipo</th>
                                    <th className={cn(ENCABEZADO, 'text-left')}>Soldador</th>
                                    <th className={cn(ENCABEZADO, 'text-left')}>Empate</th>
                                    <th className={cn(ENCABEZADO, 'text-left')}>Req.</th>
                                    <th className={cn(ENCABEZADO, 'text-left')}>Medido</th>
                                    {PUNTOS_MAPEO.map(([clave, etiqueta]) => (
                                        <th key={clave} className={cn(ENCABEZADO, 'px-1 align-bottom')} title={etiqueta}>
                                            <div className="mx-auto h-[120px] w-5 rotate-180 text-left leading-tight normal-case [writing-mode:vertical-rl]">
                                                {etiqueta}
                                            </div>
                                        </th>
                                    ))}
                                    <th className={cn(ENCABEZADO, 'text-left')}>Resultado</th>
                                    <th className={ENCABEZADO} />
                                </tr>
                            </thead>
                            <tbody>
                                {renglones.map((renglon) => {
                                    const junta = renglon.junta;
                                    const elegido = renglon.cordon !== null && renglon.cordon.id === seleccionado;
                                    const esFilete = (junta?.tipo || renglon.tipo) === 'Filete';
                                    const medida = junta && evaluarFilete(junta.espesorRequerido, junta.espesorMedido);
                                    const falta = junta && (!junta.junta.trim() || !junta.tipo);

                                    return (
                                        <tr
                                            key={renglon.clave}
                                            ref={(nodo) => {
                                                if (renglon.cordon) {
                                                    renglonRef.current[renglon.cordon.id] = nodo;
                                                }
                                            }}
                                            className={cn(elegido && 'bg-info/10')}
                                        >
                                            <td
                                                className={cn(
                                                    CELDA,
                                                    'sticky left-0 z-10 border-l-4 bg-base-100 px-2 whitespace-nowrap',
                                                    elegido ? 'border-l-info' : 'border-l-transparent',
                                                )}
                                            >
                                                {renglon.cordon ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => onSeleccionar?.(elegido ? null : renglon.cordon!.id)}
                                                        className={cn('font-bold', elegido ? 'text-info' : 'text-primary')}
                                                        title="Verla en el modelo 3D"
                                                    >
                                                        {renglon.etiqueta}
                                                    </button>
                                                ) : (
                                                    <input
                                                        value={junta?.junta ?? ''}
                                                        onChange={(e) => escribir(renglon, { junta: e.target.value.toUpperCase() })}
                                                        placeholder="J1"
                                                        className="input input-bordered input-sm w-20 text-[13px] font-bold"
                                                    />
                                                )}
                                                {renglon.cordon && <span className="ml-1 text-[10px] text-info">3D</span>}
                                            </td>

                                            <td className={CELDA}>
                                                <select
                                                    value={junta?.tipo ?? renglon.tipo}
                                                    onChange={(e) => cambiarTipo(renglon, e.target.value)}
                                                    className="select select-bordered select-sm w-24 text-[13px]"
                                                >
                                                    <option value="">—</option>
                                                    {TIPOS.map((tipo) => (
                                                        <option key={tipo} value={tipo}>
                                                            {tipo}
                                                        </option>
                                                    ))}
                                                </select>
                                            </td>

                                            <td className={CELDA}>
                                                <select
                                                    value={junta?.soldador ?? ''}
                                                    onChange={(e) => escribir(renglon, { soldador: e.target.value })}
                                                    className="select select-bordered select-sm w-36 text-[13px]"
                                                    title={nombreSoldador(junta?.soldador ?? '')}
                                                >
                                                    <option value="">—</option>
                                                    {soldadores.map(([id, nombre]) => (
                                                        <option key={id} value={id}>
                                                            {nombre}
                                                        </option>
                                                    ))}
                                                </select>
                                            </td>

                                            <td className={CELDA}>
                                                <button
                                                    type="button"
                                                    onClick={() => escribir(renglon, { esEmpate: !junta?.esEmpate })}
                                                    title="Une dos tramos del mismo miembro"
                                                    className={cn(
                                                        'h-8 w-16 rounded-lg border-2 text-xs font-bold',
                                                        junta?.esEmpate
                                                            ? 'border-primary bg-primary text-primary-content'
                                                            : 'border-base-300 bg-base-100 text-base-content/50',
                                                    )}
                                                >
                                                    {junta?.esEmpate ? 'Empate' : 'No'}
                                                </button>
                                            </td>

                                            <td className={CELDA}>
                                                <input
                                                    type="number"
                                                    step="0.5"
                                                    inputMode="decimal"
                                                    disabled={!esFilete}
                                                    value={junta?.espesorRequerido ?? ''}
                                                    onChange={(e) => medirFilete(renglon, 'espesorRequerido', e.target.value)}
                                                    placeholder="plano"
                                                    className="input input-bordered input-sm w-20 text-[13px]"
                                                />
                                            </td>

                                            <td className={CELDA}>
                                                <div className="flex items-center gap-1">
                                                    <input
                                                        type="number"
                                                        step="0.5"
                                                        inputMode="decimal"
                                                        disabled={!esFilete}
                                                        value={junta?.espesorMedido ?? ''}
                                                        onChange={(e) => medirFilete(renglon, 'espesorMedido', e.target.value)}
                                                        placeholder="mm"
                                                        className="input input-bordered input-sm w-20 text-[13px]"
                                                    />
                                                    {medida && (
                                                        <Pastilla tono={medida.cumple ? 'lib' : 'rej'}>
                                                            {medida.cumple ? 'ok' : 'no'}
                                                        </Pastilla>
                                                    )}
                                                </div>
                                            </td>

                                            {PUNTOS_MAPEO.map(([clave, etiqueta]) => {
                                                const valor = junta?.puntos[clave] ?? '';
                                                return (
                                                    <td key={clave} className={cn(CELDA, 'px-0.5 text-center')}>
                                                        <button
                                                            type="button"
                                                            onClick={() => rotarPunto(renglon, clave)}
                                                            title={`${etiqueta}: ${valor || 'sin responder'}`}
                                                            className={cn(
                                                                'size-9 rounded-lg border-2 text-base font-extrabold',
                                                                TONO_PUNTO[valor] ?? TONO_PUNTO[''],
                                                            )}
                                                        >
                                                            {GLIFO[valor] ?? GLIFO['']}
                                                        </button>
                                                    </td>
                                                );
                                            })}

                                            <td className={cn(CELDA, 'whitespace-nowrap')}>
                                                {falta ? (
                                                    <Pastilla tono="rej">Falta número o tipo</Pastilla>
                                                ) : !junta || sinLlenar(junta) ? (
                                                    <Pastilla tono="pen">Por revisar</Pastilla>
                                                ) : (
                                                    <Pastilla tono={estadoJunta(junta) === 'Con defecto' ? 'rej' : 'lib'}>
                                                        {estadoJunta(junta)}
                                                    </Pastilla>
                                                )}
                                            </td>

                                            <td className={cn(CELDA, 'text-right whitespace-nowrap')}>
                                                <button
                                                    type="button"
                                                    onClick={() => todoOk(renglon)}
                                                    title="Marcar todo OK"
                                                    className="mr-1 h-8 w-8 rounded-lg border-2 border-success bg-success/10 font-extrabold text-success"
                                                >
                                                    ✓
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => limpiar(renglon)}
                                                    disabled={renglon.indice === null}
                                                    title={renglon.cordon ? 'Dejarla por revisar' : 'Quitar el renglón'}
                                                    className="h-8 w-8 rounded-lg border-2 border-base-300 disabled:opacity-30"
                                                >
                                                    🗑️
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </>
            )}

            <div className="mt-[14px] flex flex-wrap items-center gap-2">
                <Boton tono="claro" onClick={anadirAMano}>
                    ➕ Junta a mano
                </Boton>
                <span className="text-xs text-base-content/60">Para una junta que el modelo 3D no trae.</span>
            </div>
        </Tarjeta>
    );
}
