/**
 * 1ª transformación — corte y habilitado.
 *
 * Perfil y placa se inspeccionan distinto (la placa es el formato F-STX-CA-03),
 * así que el sub-tipo cambia el último bloque. El muestreo es opcional: sirve
 * para lotes de piezas iguales cortadas con el mismo setup.
 */

import { useState } from 'react';
import type { NivelMuestreo } from './datos';
import type { Campos } from './estado';
import { MUESTREO_VACIO, PanelMuestreo, type EstadoMuestreo } from './muestreo';
import { calcularBarrenos, planMuestreo } from './reglas';
import { Boton, Campo, OK_DEFECTO, OK_TOLERANCIA, Pista, Rejilla, Segmentado, Selector, Tarjeta, Texto } from './ui';

const NIVELES: [string, string][] = [
    ['I', 'I — reducida'],
    ['II', 'II — normal'],
    ['III', 'III — severa'],
];

export function FasePrimera({
    campos,
    muestreo,
    onMuestreo,
}: {
    campos: Campos;
    muestreo: EstadoMuestreo;
    onMuestreo: (estado: EstadoMuestreo) => void;
}) {
    const [abierto, setAbierto] = useState(false);
    const esPlaca = campos.v('p1_subtipo') === 'Placa';

    const nivel = (campos.v('p1_nivel') || 'II') as NivelMuestreo;
    const plan = planMuestreo(parseInt(campos.v('p1_lote'), 10), nivel);
    const barrenos = calcularBarrenos(campos.v('p1_posbar'), campos.v('p1_diam'));

    return (
        <>
            <Tarjeta titulo="Tipo de pieza (1ª)">
                <Pista>
                    Perfil y placa se inspeccionan distinto (placa = formato F-STX-CA-03). Elige el tipo.
                </Pista>
                <Segmentado
                    value={campos.v('p1_subtipo') || 'Perfil'}
                    onChange={(valor) => campos.set('p1_subtipo', valor)}
                    opciones={[
                        { valor: 'Perfil', texto: 'Perfil' },
                        { valor: 'Placa', texto: 'Placa' },
                    ]}
                />
            </Tarjeta>

            <Tarjeta titulo="Muestreo del lote (1ª)" etiqueta="opcional">
                <Pista>
                    Para lotes de piezas <b>iguales</b> — por ejemplo 100 endplates cortados con el mismo setup. Indica
                    el lote y el nivel; la app calcula la muestra. Marca cada pieza con un toque; sólo las que fallan
                    piden detalle. Si no aplica, deja la sección cerrada.
                </Pista>
                <Boton tono="acero" onClick={() => setAbierto(!abierto)}>
                    {abierto ? 'Cerrar muestreo del lote' : '📦 Abrir muestreo del lote'}
                </Boton>

                {abierto && (
                    <div className="mt-3 border-t border-base-300 pt-3">
                        <Rejilla cols={3}>
                            <Campo label="Tamaño del lote (piezas)">
                                <Texto
                                    tipo="number"
                                    value={campos.v('p1_lote')}
                                    placeholder="ej. 100"
                                    onChange={(valor) => {
                                        campos.set('p1_lote', valor);
                                        // El lote entero es lo que se libera: la cantidad de la pieza
                                        // es el tamaño del lote, no el de la muestra.
                                        campos.set('cant', valor);
                                    }}
                                />
                            </Campo>
                            <Campo label="Nivel de inspección">
                                <Selector
                                    value={nivel}
                                    onChange={(valor) => campos.set('p1_nivel', valor)}
                                    opciones={NIVELES}
                                    vacio={null}
                                />
                            </Campo>
                            <Campo label="Muestra a inspeccionar">
                                <Texto value={plan ? String(plan.muestra) : ''} readOnly placeholder="—" className="font-bold" />
                            </Campo>
                        </Rejilla>

                        {plan && (
                            <Pista className="mt-2">
                                Inspecciona {plan.muestra} de {plan.lote} · Acepta el lote si ≤ {plan.aceptacion}{' '}
                                rechazadas · Recházalo si ≥ {plan.rechazo} rechazadas.
                            </Pista>
                        )}

                        <PanelMuestreo plan={plan} estado={muestreo ?? MUESTREO_VACIO} onEstado={onMuestreo} />
                    </div>
                )}
            </Tarjeta>

            <Tarjeta titulo="Inspección visual y dimensional (1ª)">
                <Rejilla cols={3}>
                    <Campo label="Dimensión de la pieza">
                        <Selector
                            value={campos.v('p1_dim')}
                            onChange={(valor) => campos.set('p1_dim', valor)}
                            opciones={['OK', 'Fuera de tol.', 'n/a']}
                        />
                    </Campo>
                    <Campo label="Defectos de corte">
                        <Selector value={campos.v('p1_corte')} onChange={(valor) => campos.set('p1_corte', valor)} opciones={OK_DEFECTO} />
                    </Campo>
                    <Campo label="Preparación junta (bisel)">
                        <Selector value={campos.v('p1_bisel')} onChange={(valor) => campos.set('p1_bisel', valor)} opciones={OK_DEFECTO} />
                    </Campo>
                </Rejilla>

                <div className="mt-3">
                    <Rejilla cols={3}>
                        <Campo label="Posición de barrenos">
                            <Selector
                                value={campos.v('p1_posbar')}
                                onChange={(valor) => campos.set('p1_posbar', valor)}
                                opciones={['OK', 'Incorrecta', 'n/a']}
                            />
                        </Campo>
                        <Campo label="Diámetro de barrenos">
                            <Selector value={campos.v('p1_diam')} onChange={(valor) => campos.set('p1_diam', valor)} opciones={OK_DEFECTO} />
                        </Campo>
                        <Campo label="Barrenos (automático)">
                            <Texto value={barrenos} readOnly placeholder="según posición y diámetro" />
                        </Campo>
                    </Rejilla>
                </div>
            </Tarjeta>

            {!esPlaca && (
                <Tarjeta titulo="Perfil — dimensional" etiqueta="perfil">
                    <Rejilla cols={3}>
                        <Campo label="No. de empates / juntas">
                            <Texto
                                tipo="number"
                                value={campos.v('p1_empates')}
                                onChange={(valor) => campos.set('p1_empates', valor)}
                                placeholder="opcional"
                            />
                        </Campo>
                        <Campo label="Longitud">
                            <Selector value={campos.v('p1_long')} onChange={(valor) => campos.set('p1_long', valor)} opciones={OK_TOLERANCIA} />
                        </Campo>
                        <Campo label="Deflexión">
                            <Selector value={campos.v('p1_defl')} onChange={(valor) => campos.set('p1_defl', valor)} opciones={OK_TOLERANCIA} />
                        </Campo>
                    </Rejilla>
                    <div className="mt-3">
                        <Rejilla>
                            <Campo label="Torsión">
                                <Selector value={campos.v('p1_tors')} onChange={(valor) => campos.set('p1_tors', valor)} opciones={OK_TOLERANCIA} />
                            </Campo>
                            <Campo label="Descuadre de patín">
                                <Selector value={campos.v('p1_patin')} onChange={(valor) => campos.set('p1_patin', valor)} opciones={OK_TOLERANCIA} />
                            </Campo>
                        </Rejilla>
                    </div>
                </Tarjeta>
            )}

            {esPlaca && (
                <Tarjeta titulo="Placa — inspección (F-STX-CA-03)" etiqueta="placa">
                    <Pista>La dimensión, corte, barrenos y bisel de arriba también aplican a la placa.</Pista>
                    <Rejilla>
                        <Campo label="Limpieza">
                            <Selector value={campos.v('p1_limpieza')} onChange={(valor) => campos.set('p1_limpieza', valor)} opciones={OK_DEFECTO} />
                        </Campo>
                        <Campo label="Estado de inspección">
                            <Selector
                                value={campos.v('p1_edoinsp')}
                                onChange={(valor) => campos.set('p1_edoinsp', valor)}
                                opciones={['Aceptado', 'Rechazado']}
                            />
                        </Campo>
                    </Rejilla>
                </Tarjeta>
            )}
        </>
    );
}
