/**
 * Mapeo de soldaduras — junta por junta.
 *
 * Es un sub-formulario de la pieza, no una pantalla aparte: las juntas se van
 * añadiendo a una lista y se guardan todas al registrar la pieza. Sólo aplica
 * en 2ª · Soldado, porque antes de soldar no hay junta que revisar.
 *
 * Ya no lleva plano: la junta cuelga de la pieza escaneada. Cuando la obra
 * tenga su modelo 3D, la junta se elegirá sobre el cordón en lugar de
 * teclearse; mientras tanto se identifica por su número.
 */

import { useState } from 'react';
import { PUNTOS_MAPEO } from './datos';
import type { Junta } from './estado';
import { evaluarFilete, PUNTO_PERFIL } from './reglas';
import { Boton, Campo, Pastilla, Pista, Rejilla, Selector, SiNo, Tarjeta, Texto } from './ui';

const PUNTO_OPCIONES = ['OK', 'Defecto', 'n/a'];
const JUNTA_VACIA: Junta = {
    junta: '',
    tipo: '',
    soldador: '',
    esEmpate: false,
    puntos: {},
    espesorRequerido: '',
    espesorMedido: '',
};

/** Con que un punto salga "Defecto", la junta entera queda con defecto. */
export function estadoJunta(junta: Junta): 'Con defecto' | 'Junta Correcta' {
    return Object.values(junta.puntos).includes('Defecto') ? 'Con defecto' : 'Junta Correcta';
}

export function Mapeo({
    juntas,
    onJuntas,
    soldadores,
    onAviso,
}: {
    juntas: Junta[];
    onJuntas: (juntas: Junta[]) => void;
    /** [id, «NOMBRE (CLAVE)»] del padrón. */
    soldadores: [string, string][];
    onAviso: (mensaje: string, tono?: 'ok' | 'error') => void;
}) {
    const [borrador, setBorrador] = useState<Junta>(JUNTA_VACIA);

    const punto = (clave: string) => borrador.puntos[clave] ?? '';
    const setPunto = (clave: string, valor: string) =>
        setBorrador((previo) => ({ ...previo, puntos: { ...previo.puntos, [clave]: valor } }));

    const filete = evaluarFilete(borrador.espesorRequerido, borrador.espesorMedido);
    const esFilete = borrador.tipo === 'Filete';
    const nombreSoldador = (id: string) => soldadores.find(([clave]) => clave === id)?.[1] ?? '—';

    const cambiarTipo = (tipo: string) => {
        setBorrador((previo) => ({
            ...previo,
            tipo,
            puntos: {
                ...previo.puntos,
                // El tipo de junta decide qué preparación aplica: la otra no se revisa.
                ...(tipo === 'Filete' ? { m_prepranura: 'n/a' } : {}),
                ...(tipo === 'Ranura' ? { m_prepfilete: 'n/a' } : {}),
            },
            espesorRequerido: tipo === 'Filete' ? previo.espesorRequerido : '',
            espesorMedido: tipo === 'Filete' ? previo.espesorMedido : '',
        }));
    };

    const medirFilete = (campo: 'espesorRequerido' | 'espesorMedido', valor: string) => {
        setBorrador((previo) => {
            const siguiente = { ...previo, [campo]: valor };
            const resultado = evaluarFilete(siguiente.espesorRequerido, siguiente.espesorMedido);
            // Un filete por debajo del nominal es un defecto de perfil, y se marca solo.
            if (resultado && !resultado.cumple) {
                siguiente.puntos = { ...siguiente.puntos, [PUNTO_PERFIL]: 'Defecto' };
            }
            return siguiente;
        });
    };

    const marcarCorrecta = () => {
        const puntos: Record<string, string> = {};
        PUNTOS_MAPEO.forEach(([clave]) => (puntos[clave] = 'OK'));
        setBorrador((previo) => ({
            ...previo,
            puntos: {
                ...puntos,
                ...(previo.tipo === 'Filete' ? { m_prepranura: 'n/a' } : {}),
                ...(previo.tipo === 'Ranura' ? { m_prepfilete: 'n/a' } : {}),
            },
        }));
        onAviso('Junta marcada correcta — ajusta lo que no cumpla');
    };

    const anadir = () => {
        const identificador = borrador.junta.trim();
        if (!identificador) {
            onAviso('Falta el N.º de junta', 'error');
            return;
        }
        if (!borrador.tipo) {
            onAviso('Elige si la junta es de filete o de ranura', 'error');
            return;
        }
        if (juntas.some((junta) => junta.junta === identificador)) {
            onAviso(`La junta ${identificador} ya está en la lista — edítala`, 'error');
            return;
        }
        onJuntas([...juntas, { ...borrador, junta: identificador }]);
        setBorrador(JUNTA_VACIA);
        onAviso(`Junta ${identificador} añadida a la lista`, 'ok');
    };

    /** Editar saca la junta de la lista y la devuelve al formulario. */
    const editar = (indice: number) => {
        setBorrador(juntas[indice]);
        onJuntas(juntas.filter((_, i) => i !== indice));
    };

    const conDefecto = juntas.filter((junta) => estadoJunta(junta) === 'Con defecto').length;

    return (
        <Tarjeta titulo="Mapeo de soldaduras · junta por junta">
            <Pista>
                Subapartado de la pieza. Añade cada junta a la lista; <b>todas se guardan al pulsar Guardar</b> abajo
                (con el resto de la pieza).
            </Pista>

            <Rejilla>
                <Campo label="Junta N.º">
                    <Texto
                        value={borrador.junta}
                        onChange={(valor) => setBorrador((previo) => ({ ...previo, junta: valor }))}
                        placeholder="Ej. J1"
                        mayusculas
                    />
                </Campo>
                <Campo label="Tipo de junta">
                    <Selector value={borrador.tipo} onChange={cambiarTipo} opciones={['Filete', 'Ranura']} />
                </Campo>
            </Rejilla>

            <div className="mt-3">
                <Rejilla>
                    <Campo label="Soldador de la Junta">
                        <Selector
                            value={borrador.soldador}
                            onChange={(valor) => setBorrador((previo) => ({ ...previo, soldador: valor }))}
                            opciones={soldadores}
                        />
                    </Campo>
                    <Campo label="¿Es empate?" ayuda="Une dos tramos del mismo miembro.">
                        <SiNo
                            value={borrador.esEmpate ? 'si' : ''}
                            onChange={(valor) => setBorrador((previo) => ({ ...previo, esEmpate: valor === 'si' }))}
                            si={{ valor: 'si', texto: 'Empate' }}
                            no={{ valor: 'no', texto: 'No' }}
                        />
                    </Campo>
                </Rejilla>
            </div>

            {esFilete && (
                <div className="mt-3 rounded-[10px] border border-warning/40 bg-warning/5 px-[14px] py-3">
                    <label className="text-[13px] font-bold text-primary">Tamaño de la soldadura de filete</label>
                    <div className="mt-2">
                        <Rejilla cols={3}>
                            <Campo label="Espesor requerido (mm)">
                                <Texto
                                    tipo="number"
                                    paso="0.5"
                                    value={borrador.espesorRequerido}
                                    onChange={(valor) => medirFilete('espesorRequerido', valor)}
                                    placeholder="del plano · ej. 8"
                                />
                            </Campo>
                            <Campo label="Espesor medido (mm)">
                                <Texto
                                    tipo="number"
                                    paso="0.5"
                                    value={borrador.espesorMedido}
                                    onChange={(valor) => medirFilete('espesorMedido', valor)}
                                    placeholder="ej. 7.5"
                                />
                            </Campo>
                            <Campo label="¿Cumple?">
                                <Texto
                                    value={filete ? (filete.cumple ? 'CUMPLE' : 'NO CUMPLE') : ''}
                                    readOnly
                                    placeholder="—"
                                    className={filete ? (filete.cumple ? 'font-extrabold text-success' : 'font-extrabold text-error') : ''}
                                />
                            </Campo>
                        </Rejilla>
                    </div>
                    {filete && <Pista className="mt-2 mb-0">{filete.mensaje}</Pista>}
                </div>
            )}

            <div className="mt-3">
                <Rejilla cols={3}>
                    {PUNTOS_MAPEO.map(([clave, etiqueta]) => (
                        <Campo key={clave} label={etiqueta}>
                            <Selector value={punto(clave)} onChange={(valor) => setPunto(clave, valor)} opciones={PUNTO_OPCIONES} />
                        </Campo>
                    ))}
                </Rejilla>
            </div>

            <div className="mt-[14px] flex flex-wrap gap-2">
                <Boton tono="ok" onClick={marcarCorrecta}>
                    ✓ Junta Correcta (todo OK)
                </Boton>
                <Boton tono="claro" onClick={anadir}>
                    ➕ Añadir junta a la lista
                </Boton>
            </div>

            <div className="mt-[14px]">
                {!juntas.length ? (
                    <Pista>Aún no hay juntas en la lista. Llena una junta y pulsa «Añadir junta a la lista».</Pista>
                ) : (
                    <>
                        <div className="my-2 font-bold text-primary">
                            Juntas de esta pieza ({juntas.length})
                            {conDefecto > 0 && <span className="text-error"> · {conDefecto} con defecto</span>}
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-[13px]">
                                <thead>
                                    <tr className="text-[11px] tracking-[.5px] text-primary uppercase">
                                        <th className="border-b border-base-300 px-[6px] py-2 text-left">Junta</th>
                                        <th className="border-b border-base-300 px-[6px] py-2 text-left">Tipo</th>
                                        <th className="border-b border-base-300 px-[6px] py-2 text-left">Filete</th>
                                        <th className="border-b border-base-300 px-[6px] py-2 text-left">Soldador</th>
                                        <th className="border-b border-base-300 px-[6px] py-2 text-left">Resultado</th>
                                        <th className="border-b border-base-300" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {juntas.map((junta, indice) => {
                                        const resultado = estadoJunta(junta);
                                        const medida = evaluarFilete(junta.espesorRequerido, junta.espesorMedido);
                                        return (
                                            <tr key={`${junta.junta}-${indice}`}>
                                                <td className="border-b border-base-300 px-[6px] py-2">
                                                    {junta.junta}
                                                    {junta.esEmpate && <span className="ml-1 text-xs text-base-content/60">(empate)</span>}
                                                </td>
                                                <td className="border-b border-base-300 px-[6px] py-2">{junta.tipo}</td>
                                                <td className="border-b border-base-300 px-[6px] py-2">
                                                    {junta.espesorMedido ? (
                                                        <>
                                                            {junta.espesorMedido}/{junta.espesorRequerido || '?'} mm{' '}
                                                            <Pastilla tono={medida?.cumple ? 'lib' : 'rej'}>
                                                                {medida?.cumple ? 'ok' : 'no cumple'}
                                                            </Pastilla>
                                                        </>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </td>
                                                <td className="border-b border-base-300 px-[6px] py-2">{nombreSoldador(junta.soldador)}</td>
                                                <td className="border-b border-base-300 px-[6px] py-2">
                                                    <Pastilla tono={resultado === 'Con defecto' ? 'rej' : 'lib'}>{resultado}</Pastilla>
                                                </td>
                                                <td className="border-b border-base-300 px-[6px] py-2 text-right whitespace-nowrap">
                                                    <button type="button" onClick={() => editar(indice)} title="Editar" className="mr-2">
                                                        ✏️
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => onJuntas(juntas.filter((_, i) => i !== indice))}
                                                        title="Quitar"
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
                        <Pista className="mt-2">
                            Estas juntas se guardarán al pulsar <b>Guardar registro</b>, junto con el resto de la pieza.
                        </Pista>
                    </>
                )}
            </div>
        </Tarjeta>
    );
}
