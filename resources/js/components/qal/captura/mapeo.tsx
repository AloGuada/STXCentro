/**
 * Mapeo de soldaduras — las juntas de la pieza.
 *
 * Las juntas son los cordones que trae el modelo 3D de la marca: el renglón
 * existe desde que se abre el mapeo y todas se guardan al registrar la pieza
 * —que era el tropiezo de antes, una junta a medio llenar se perdía al
 * guardar.
 *
 * Se capturan **de una en una**: el visor enseña la pieza y el panel de al
 * lado las columnas de la junta elegida, con los botones de anterior y
 * siguiente para recorrerlas. La matriz completa sigue detrás de «Ver la tabla
 * completa», para ver de un golpe cómo va toda la pieza.
 *
 * Sin modelo —o para una junta que el modelo no trae— se añade un renglón a
 * mano y se numera en el propio panel.
 *
 * Elegir un renglón elige el cordón en el visor, que lo encuadra. Es el mismo
 * estado en los dos sentidos: tocar el cordón en el visor trae aquí su junta.
 */

import { useEffect, useRef, useState, type ReactNode } from 'react';
import { cn } from '@/lib/utils';
import type { CordonVisor } from '../juntas3d/tipos';
import { PUNTOS_MAPEO, TIPOS_JUNTA } from './datos';
import type { Junta } from './estado';
import { PanelJunta } from './panel-junta';
import { evaluarFilete, PUNTO_PERFIL } from './reglas';
import { Boton, Pastilla, Pista, Tarjeta } from './ui';

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
 * Los 18 puntos de una junta puestos en OK.
 *
 * La preparación que no aplica a su tipo queda en «n/a», y un filete por debajo
 * del nominal sigue siendo defecto de perfil: el servidor lo marca igual al
 * guardar (`RegistradorInspeccion::guardarJuntas`), así que pintarlo verde aquí
 * sólo enseñaría algo distinto de lo que queda registrado.
 */
function puntosEnVerde(junta: Junta): Record<string, string> {
    const puntos: Record<string, string> = {};
    PUNTOS_MAPEO.forEach(([clave]) => (puntos[clave] = 'OK'));

    if (junta.tipo === 'Filete') {
        puntos.m_prepranura = 'n/a';
    }
    if (junta.tipo === 'Ranura') {
        puntos.m_prepfilete = 'n/a';
    }

    const medida = evaluarFilete(junta.espesorRequerido, junta.espesorMedido);
    if (medida && !medida.cumple) {
        puntos[PUNTO_PERFIL] = 'Defecto';
    }

    return puntos;
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
    /** La junta elegida a mano o con ‹ ›. El cordón del visor manda sobre ella. */
    const [claveSel, setClaveSel] = useState<string | null>(null);
    const [tablaAbierta, setTablaAbierta] = useState(false);
    const [confirmarVerde, setConfirmarVerde] = useState(false);

    // Elegir un cordón en el visor trae su renglón a la vista: la matriz es
    // ancha y el renglón puede estar fuera de la pantalla.
    useEffect(() => {
        if (seleccionado !== null) {
            renglonRef.current[seleccionado]?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        }
    }, [seleccionado]);

    // Tocar un cordón en el visor es elegir su junta: se lee de `seleccionado` en
    // vez de copiarlo a un estado propio, que son dos verdades para lo mismo.
    const claveActual = seleccionado !== null ? `cordon-${seleccionado}` : claveSel;

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

    // La junta del panel. Sin nada elegido —o si su renglón desapareció— es la
    // primera: el panel nunca se queda en blanco teniendo juntas que capturar.
    const indiceSel = renglones.findIndex((renglon) => renglon.clave === claveActual);
    const indiceActual = indiceSel >= 0 ? indiceSel : 0;
    const actual: Renglon | null = renglones[indiceActual] ?? null;

    /** Moverse a una junta la elige también en el visor, que la encuadra. */
    const ir = (indice: number) => {
        const destino = renglones[indice];
        if (!destino) {
            return;
        }
        setClaveSel(destino.clave);
        onSeleccionar?.(destino.cordon?.id ?? null);
    };

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
        escribir(renglon, { puntos: puntosEnVerde(junta) });
        onAviso(`Junta ${junta.junta || 'sin número'} marcada correcta — ajusta lo que no cumpla`);
    };

    /**
     * Todas las juntas de la pieza en verde de un golpe, para la pieza que sale
     * limpia. Arrasa con lo capturado —por eso se confirma— y crea de paso la
     * junta de cada cordón que todavía no tenía renglón.
     */
    const marcarTodasVerde = () => {
        onJuntas(
            renglones.map((renglon) => {
                const junta = juntaDe(renglon);
                // El cordón ya dice si es filete o costura: sin tipo elegido, se toma el suyo.
                const conTipo = { ...junta, tipo: junta.tipo || renglon.tipo };

                return { ...conTipo, puntos: puntosEnVerde(conTipo) };
            }),
        );
        setConfirmarVerde(false);
        onAviso(`${renglones.length} junta(s) marcadas correctas — ajusta las que no cumplan`);
    };

    /** Limpiar deja el renglón del cordón en «por revisar»; el de mano desaparece. */
    const limpiar = (renglon: Renglon) => {
        if (renglon.indice === null) {
            return;
        }
        // Al quitar un renglón a mano su clave deja de existir: el panel se queda
        // en el anterior en vez de saltar solo al principio de la pieza.
        if (!renglon.cordon) {
            ir(Math.max(0, renglones.findIndex((otro) => otro.clave === renglon.clave) - 1));
        }
        onJuntas(juntas.filter((_, i) => i !== renglon.indice));
    };

    const anadirAMano = () => {
        onJuntas([...juntas, { ...JUNTA_VACIA }]);
        // La nueva junta entra en el panel: lo primero que le falta es el número.
        setClaveSel(`mano-${juntas.length}`);
        onSeleccionar?.(null);
        onAviso('Junta añadida: ponle número y tipo');
    };

    return (
        <Tarjeta titulo="Mapeo de soldaduras · junta por junta">
            <Pista>
                Las juntas son los <b>cordones del modelo</b>: se capturan de una en una en el panel de la derecha y se
                recorren con <b>‹</b> y <b>›</b>. Todo se guarda al pulsar Guardar, con el resto de la pieza.
            </Pista>

            {/* La zona de captura: la pieza a un lado y la junta que se está
                contestando al otro, para no perder de vista cuál es cuál. */}
            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_400px]">
                <div className="min-w-0">{modelo3d}</div>
                {actual && (
                    <div className="lg:sticky lg:top-4 lg:self-start">
                        <PanelJunta
                            etiqueta={actual.etiqueta}
                            esDelModelo={actual.cordon !== null}
                            junta={juntaDe(actual)}
                            soldadores={soldadores}
                            indice={indiceActual}
                            total={renglones.length}
                            onIr={ir}
                            onCambio={(cambios) => escribir(actual, cambios)}
                            onTipo={(tipo) => cambiarTipo(actual, tipo)}
                            onMedida={(campo, valor) => medirFilete(actual, campo, valor)}
                            onPunto={(clave, valor) => escribirPunto(actual, clave, valor)}
                            onTodoOk={() => todoOk(actual)}
                            onLimpiar={() => limpiar(actual)}
                            puedeLimpiar={actual.indice !== null}
                            estado={
                                !actual.junta || sinLlenar(actual.junta) ? 'Por revisar' : estadoJunta(actual.junta)
                            }
                        />
                    </div>
                )}
            </div>

            {renglones.length === 0 ? (
                <Pista className="mt-3 mb-0">
                    La pieza no trae cordones del modelo 3D. Añade la junta a mano y numérala en el panel.
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

                        {/* La matriz entera queda a un clic: para repasar la pieza de
                            un golpe, no para capturarla renglón por renglón. */}
                        <button
                            type="button"
                            onClick={() => setTablaAbierta(!tablaAbierta)}
                            className="ml-auto text-xs font-semibold text-primary underline"
                        >
                            {tablaAbierta ? 'Ocultar la tabla completa' : 'Ver la tabla completa'}
                        </button>
                    </div>

                    {/* La matriz es ancha (18 puntos) y larga (un renglón por cordón):
                        el encabezado y la columna de la junta se quedan fijos para no
                        perder de vista qué se está respondiendo. Cerrada se desmonta:
                        dejarla escondida repinta cientos de celdas a cada tecleo. */}
                    {tablaAbierta && (
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
                                                        {TIPOS_JUNTA.map((tipo) => (
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
                    )}
                </>
            )}

            <div className="mt-[14px] flex flex-wrap items-center gap-2">
                {/* Pisa lo capturado, así que se pregunta antes: el aviso va en el
                    propio botón para que se lea justo donde se va a pulsar. */}
                {confirmarVerde ? (
                    <>
                        <Boton tono="ok" onClick={marcarTodasVerde}>
                            Sí, marcar las {renglones.length}
                        </Boton>
                        <Boton tono="claro" onClick={() => setConfirmarVerde(false)}>
                            Cancelar
                        </Boton>
                        <span className="text-xs text-error">
                            Se sobrescriben las {empezadas.length} junta(s) ya capturadas
                            {conDefecto > 0 && `, incluidas las ${conDefecto} con defecto`}.
                        </span>
                    </>
                ) : (
                    <Boton tono="ok" onClick={() => setConfirmarVerde(true)} disabled={renglones.length === 0}>
                        ✓ Marcar todas en verde
                    </Boton>
                )}

                <Boton tono="claro" onClick={anadirAMano}>
                    ➕ Junta a mano
                </Boton>
                <span className="text-xs text-base-content/60">Para una junta que el modelo 3D no trae.</span>

            </div>
        </Tarjeta>
    );
}
