/**
 * 2ª transformación — armado, vestido y soldadura.
 *
 * La sub-etapa parte el formulario en dos: en Armado/Vestido se revisa la
 * preparación de las juntas (todavía no hay soldadura que juzgar) y en Soldado
 * el producto terminado, que es donde vive el mapeo. Saber en qué momento del
 * proceso se inspeccionó es lo que permite decir dónde nacen los errores.
 */

import { useState } from 'react';
import type { Campos, Junta } from './estado';
import { Mapeo } from './mapeo';
import { calcularDimensional } from './reglas';
import {
    Boton,
    Campo,
    ChipsContados,
    Contador,
    OK_DEFECTO,
    Pista,
    Rejilla,
    Segmentado,
    Selector,
    SiNo,
    Tarjeta,
    Texto,
} from './ui';

const DESVIACIONES = ['Deflexión', 'Torsión', 'Flecha', 'Contraflecha', 'Hi-Low', 'Alabeo en patín', 'Pandeo de alma', 'Otro'];

export function FaseSegunda({
    campos,
    defectos,
    onDefectos,
    defectosCatalogo,
    juntas,
    onJuntas,
    soldadores,
    subetapaFija = false,
    onAviso,
    onRechazar,
}: {
    campos: Campos;
    /** Por nombre: los chips cuentan por nombre y al guardar se traducen a id. */
    defectos: Record<string, number>;
    onDefectos: (defectos: Record<string, number>) => void;
    /** Los defectos de soldadura activos del catálogo. */
    defectosCatalogo: string[];
    juntas: Junta[];
    onJuntas: (juntas: Junta[]) => void;
    soldadores: [string, string][];
    /** Al corregir o reinspeccionar, la sub-etapa es parte de la identidad: no cambia. */
    subetapaFija?: boolean;
    onAviso: (mensaje: string, tono?: 'ok' | 'error') => void;
    onRechazar: () => void;
}) {
    const [mapeoAbierto, setMapeoAbierto] = useState(false);

    const subetapa = campos.v('p2_subetapa');
    const soldado = subetapa === 'Soldado';
    const armado = subetapa === 'Armado-Vestido';
    const conDesviacion = campos.v('p2_desv') === 'Con desviación';

    const totalDefectos = Object.values(defectos).reduce((suma, cantidad) => suma + cantidad, 0);
    const dimensional = campos.v('p2_dimok') || calcularDimensional(campos.v('p2_long'), campos.v('p2_placas'));
    const faltaVestido = parseInt(campos.v('p2_faltavest'), 10) || 0;

    return (
        <>
            <Tarjeta titulo="Punto de inspección (2ª)">
                <Pista>
                    ¿En qué momento del proceso se hace esta inspección? Ayuda a saber <b>dónde</b> nacen los errores.
                </Pista>
                <Campo label="Sub-etapa">
                    <Segmentado
                        value={subetapa}
                        onChange={(valor) => !subetapaFija && campos.set('p2_subetapa', valor)}
                        opciones={[
                            { valor: 'Armado-Vestido', texto: 'Armado / Vestido' },
                            { valor: 'Soldado', texto: 'Soldado (producto terminado)' },
                        ]}
                    />
                </Campo>

                <div className="mt-[14px]">
                    <Rejilla>
                        <Campo
                            label="Giro o caída de placas o accesorios"
                            ayuda="Cuántas placas o accesorios se giraron o cayeron. 0 si ninguno."
                        >
                            <Contador value={campos.v('p2_diaf') || '0'} onChange={(valor) => campos.set('p2_diaf', valor)} />
                        </Campo>
                        <Campo label="¿Desviación dimensional por soldadura?">
                            <Selector
                                value={campos.v('p2_desv')}
                                onChange={(valor) => campos.set('p2_desv', valor)}
                                opciones={['OK (sin desviación)', 'Con desviación']}
                            />
                        </Campo>
                    </Rejilla>
                </div>

                {conDesviacion && (
                    <div className="mt-3">
                        <Rejilla>
                            <Campo label="Tipo de desviación">
                                <Selector
                                    value={campos.v('p2_desvtipo')}
                                    onChange={(valor) => campos.set('p2_desvtipo', valor)}
                                    opciones={DESVIACIONES}
                                />
                            </Campo>
                            <Campo label="Tamaño de desviación">
                                <Selector
                                    value={campos.v('p2_desvsize')}
                                    onChange={(valor) => campos.set('p2_desvsize', valor)}
                                    opciones={['Leve', 'Moderada', 'Severa']}
                                />
                            </Campo>
                        </Rejilla>
                    </div>
                )}
            </Tarjeta>

            {soldado && (
                <Tarjeta titulo="Mapeo de soldaduras">
                    <Pista>
                        Inspección detallada <b>junta por junta</b> (formato de mapeo). Es opcional: ábrelo sólo cuando
                        toque mapear la pieza. El formulario normal de soldadura sigue disponible.
                    </Pista>
                    <Boton tono="acero" onClick={() => setMapeoAbierto(!mapeoAbierto)}>
                        {mapeoAbierto ? 'Cerrar mapeo de soldaduras' : '🔧 Abrir mapeo de soldaduras'}
                    </Boton>
                </Tarjeta>
            )}

            {soldado && mapeoAbierto && (
                <Mapeo juntas={juntas} onJuntas={onJuntas} soldadores={soldadores} onAviso={onAviso} />
            )}

            <Tarjeta titulo="Inspección dimensional (2ª)">
                <Rejilla cols={3}>
                    <Campo label="Longitud total">
                        <Selector value={campos.v('p2_long')} onChange={(valor) => campos.set('p2_long', valor)} opciones={['OK', 'No OK']} />
                    </Campo>
                    <Campo label="Dist. entre placas">
                        <Selector
                            value={campos.v('p2_placas')}
                            onChange={(valor) => campos.set('p2_placas', valor)}
                            opciones={['OK', 'No OK', 'n/a']}
                        />
                    </Campo>
                    <Campo label="¿Dimensiones OK?">
                        <Selector value={dimensional} onChange={(valor) => campos.set('p2_dimok', valor)} opciones={['OK', 'No OK']} />
                    </Campo>
                </Rejilla>
            </Tarjeta>

            <Tarjeta titulo="Barrenos habilitados (2ª)">
                <Pista>
                    Se revisan los barrenos <b>habilitados (hechos) en 2ª</b>, no los que ya venían de 1ª.
                </Pista>
                <Rejilla>
                    <Campo label="Diámetro">
                        <Selector value={campos.v('p2_bardiam')} onChange={(valor) => campos.set('p2_bardiam', valor)} opciones={OK_DEFECTO} />
                    </Campo>
                    <Campo label="Posición / distancia">
                        <Selector value={campos.v('p2_bardist')} onChange={(valor) => campos.set('p2_bardist', valor)} opciones={OK_DEFECTO} />
                    </Campo>
                </Rejilla>

                <div className="mt-3 grid grid-cols-[1fr_82px_82px] items-end gap-[10px] rounded-[9px] border border-base-300 bg-base-200 p-[10px]">
                    <div className="self-center font-bold">Barrenos habilitados</div>
                    <Campo label="N.º habilitados">
                        <Texto
                            tipo="number"
                            value={campos.v('p2_bartot')}
                            onChange={(valor) => campos.set('p2_bartot', valor)}
                            className="bg-warning/10 px-1 text-center text-lg font-semibold"
                        />
                    </Campo>
                    <Campo label="N.º defectuosos">
                        <Texto
                            tipo="number"
                            value={campos.v('p2_bardef')}
                            onChange={(valor) => campos.set('p2_bardef', valor)}
                            className="bg-error/10 px-1 text-center text-lg font-semibold"
                        />
                    </Campo>
                </div>
            </Tarjeta>

            {soldado && (
                <>
                    <Tarjeta titulo="Soldadura (2ª)">
                        <Pista>
                            Registra el número de defectos por tipo. La pieza se normaliza por su <b>nº de elementos</b>{' '}
                            (del plano), no por metros lineales.
                        </Pista>
                        <div className="grid grid-cols-[1fr_120px] items-end gap-[10px] rounded-[9px] border border-base-300 bg-base-200 p-[10px]">
                            <div className="self-center font-bold">Soldadura</div>
                            <Campo label="Total defectos (auto)">
                                <Texto value={String(totalDefectos || '')} readOnly className="px-1 text-center text-lg font-semibold" />
                            </Campo>
                        </div>

                        <div className="mt-3">
                            <Campo label="Nº de elementos de la pieza (del plano)">
                                <Texto
                                    tipo="number"
                                    value={campos.v('p2_elem')}
                                    onChange={(valor) => campos.set('p2_elem', valor)}
                                    placeholder="ej. 11 — cantidad total de la lista de materiales"
                                />
                            </Campo>
                        </div>

                        <div className="mt-[14px]">
                            <Campo label="Tipo(s) de defecto en soldadura (AWS D1.1 · placa/no tubular)">
                                <ChipsContados opciones={defectosCatalogo} valor={defectos} onChange={onDefectos} />
                            </Campo>
                            <Pista className="mt-2">La lista sale de Catálogos → Defectos, ámbito soldadura.</Pista>
                        </div>
                    </Tarjeta>

                    <Tarjeta titulo="Control de proceso · durante la soldadura (2ª)">
                        <Pista>Verifica estos controles aunque no se haga mapeo, para dejar constancia de que se revisaron.</Pista>
                        <Rejilla>
                            <Campo label="Precalentamiento">
                                <Selector value={campos.v('p2_precal')} onChange={(valor) => campos.set('p2_precal', valor)} opciones={OK_DEFECTO} />
                            </Campo>
                            <Campo label="Limpieza entre pasadas">
                                <Selector
                                    value={campos.v('p2_limppasadas')}
                                    onChange={(valor) => campos.set('p2_limppasadas', valor)}
                                    opciones={OK_DEFECTO}
                                />
                            </Campo>
                        </Rejilla>
                    </Tarjeta>

                    <Tarjeta titulo="Limpieza mecánica y etiqueta (2ª)">
                        <Rejilla>
                            <Campo label="Limpieza mecánica">
                                <SiNo
                                    value={campos.v('p2_limpieza')}
                                    onChange={(valor) => campos.set('p2_limpieza', valor)}
                                    si={{ valor: 'OK', texto: 'Lista' }}
                                    no={{ valor: 'Falta', texto: 'Falta' }}
                                />
                            </Campo>
                            <Campo label="Etiqueta">
                                <SiNo
                                    value={campos.v('p2_etiqueta')}
                                    onChange={(valor) => campos.set('p2_etiqueta', valor)}
                                    si={{ valor: 'OK', texto: 'Tiene' }}
                                    no={{ valor: 'Falta', texto: 'Falta' }}
                                />
                            </Campo>
                        </Rejilla>
                    </Tarjeta>
                </>
            )}

            {armado && (
                <Tarjeta titulo="Preparación de Juntas (2ª · Armado/Vestido)">
                    <Pista>Revisión de la preparación de las juntas antes de soldar.</Pista>
                    <Rejilla cols={3}>
                        <Campo label="Ángulo de bisel">
                            <Selector value={campos.v('p2_bisel')} onChange={(valor) => campos.set('p2_bisel', valor)} opciones={OK_DEFECTO} />
                        </Campo>
                        <Campo label="Pulido (oxicorte)">
                            <Selector value={campos.v('p2_pulido')} onChange={(valor) => campos.set('p2_pulido', valor)} opciones={OK_DEFECTO} />
                        </Campo>
                        <Campo label="Separación de raíz">
                            <Selector value={campos.v('p2_raiz')} onChange={(valor) => campos.set('p2_raiz', valor)} opciones={OK_DEFECTO} />
                        </Campo>
                        <Campo label="Placa de respaldo">
                            <Selector value={campos.v('p2_respaldo')} onChange={(valor) => campos.set('p2_respaldo', valor)} opciones={OK_DEFECTO} />
                        </Campo>
                        <Campo label="Hombro">
                            <Selector value={campos.v('p2_hombro')} onChange={(valor) => campos.set('p2_hombro', valor)} opciones={OK_DEFECTO} />
                        </Campo>
                        <Campo label="Acceso de soldadura">
                            <Selector value={campos.v('p2_acceso')} onChange={(valor) => campos.set('p2_acceso', valor)} opciones={OK_DEFECTO} />
                        </Campo>
                    </Rejilla>

                    <div className="mt-3">
                        <Rejilla>
                            <Campo label="Corte (destajo)">
                                <Selector value={campos.v('p2_corte')} onChange={(valor) => campos.set('p2_corte', valor)} opciones={OK_DEFECTO} />
                            </Campo>
                            <Campo
                                label="Falta de vestido — elementos faltantes"
                                ayuda={
                                    faltaVestido > 0 ? (
                                        <span className="text-error">⚠ {faltaVestido} faltante(s) → pieza rechazada</span>
                                    ) : undefined
                                }
                            >
                                <Contador
                                    value={campos.v('p2_faltavest') || '0'}
                                    onChange={(valor) => {
                                        campos.set('p2_faltavest', valor);
                                        // Una pieza a la que le faltan elementos no está bien: se rechaza sola.
                                        if ((parseInt(valor, 10) || 0) > 0) {
                                            onRechazar();
                                        }
                                    }}
                                />
                            </Campo>
                        </Rejilla>
                    </div>
                </Tarjeta>
            )}

        </>
    );
}
