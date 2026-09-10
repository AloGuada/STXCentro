/**
 * 2ª transformación — armado, vestido y soldadura.
 *
 * La sub-etapa parte el formulario en dos: en Armado/Vestido se revisa la
 * preparación de las juntas (todavía no hay soldadura que juzgar) y en Soldado
 * el producto terminado, que es donde vive el mapeo. Saber en qué momento del
 * proceso se inspeccionó es lo que permite decir dónde nacen los errores.
 */

import { useEffect, useMemo, useState } from 'react';
import { PanelCordon } from '../juntas3d/panel-cordon';
import { cargarMarca, type CordonVisor, type EstadoCordon, type MarcaVisor } from '../juntas3d/tipos';
import { Visor } from '../juntas3d/visor';
import type { Campos, Junta } from './estado';
import { estadoJunta, JUNTA_VACIA, Mapeo } from './mapeo';
import type { Estado3d } from './pieza-fisica';
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

/** Por qué no sale el visor, dicho para que el inspector sepa a quién pedírselo. */
const SIN_VISOR: Record<Exclude<Estado3d, 'listo'>, string> = {
    sin_modelo: 'La obra todavía no tiene modelo 3D: su IFC se sube en Producción → Catálogos → la obra → Modelo 3D.',
    convirtiendo: 'El modelo 3D de la obra se está convirtiendo; cuando termine, la pieza aparece aquí.',
    sin_marca: 'Esta marca no viene en el modelo 3D convertido de la obra.',
    error: 'La conversión del modelo 3D de la obra falló: revísala en Producción → Catálogos → la obra → Modelo 3D.',
};

export function FaseSegunda({
    campos,
    defectos,
    onDefectos,
    defectosCatalogo,
    juntas,
    onJuntas,
    soldadores,
    subetapaFija = false,
    modelo3d = null,
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
    /**
     * La marca de la pieza en el modelo 3D ya convertido de la obra, o por qué
     * no la hay. Nulo mientras no se ha escaneado la pieza.
     */
    modelo3d?: { marcaId: number | null; estado: Estado3d } | null;
    onAviso: (mensaje: string, tono?: 'ok' | 'error') => void;
    onRechazar: () => void;
}) {
    const modeloMarcaId = modelo3d?.marcaId ?? null;
    const [mapeoAbierto, setMapeoAbierto] = useState(false);
    const [borrador, setBorrador] = useState<Junta>(JUNTA_VACIA);
    const [marca3d, setMarca3d] = useState<MarcaVisor | null>(null);
    const [error3d, setError3d] = useState<{ id: number; mensaje: string } | null>(null);
    const [cordonSel, setCordonSel] = useState<number | null>(null);

    const subetapa = campos.v('p2_subetapa');
    const soldado = subetapa === 'Soldado';
    const armado = subetapa === 'Armado-Vestido';
    const conDesviacion = campos.v('p2_desv') === 'Con desviación';

    const totalDefectos = Object.values(defectos).reduce((suma, cantidad) => suma + cantidad, 0);
    const dimensional = campos.v('p2_dimok') || calcularDimensional(campos.v('p2_long'), campos.v('p2_placas'));
    const faltaVestido = parseInt(campos.v('p2_faltavest'), 10) || 0;

    // El modelo de la marca se pide al abrir el mapeo de soldado, que es donde
    // se capturan las juntas sobre sus cordones.
    useEffect(() => {
        if (!soldado || !mapeoAbierto || !modeloMarcaId) {
            return;
        }
        let vivo = true;
        cargarMarca(modeloMarcaId)
            .then((marca) => {
                if (vivo) {
                    setMarca3d(marca);
                }
            })
            .catch((error: Error) => {
                if (vivo) {
                    setError3d({ id: modeloMarcaId, mensaje: error.message });
                }
            });
        return () => {
            vivo = false;
        };
    }, [soldado, mapeoAbierto, modeloMarcaId]);

    const marcaVisible = marca3d && marca3d.id === modeloMarcaId ? marca3d : null;
    const falla3d = error3d && error3d.id === modeloMarcaId ? error3d.mensaje : null;

    /** En la captura, cada cordón se pinta con la junta que lleva en esta inspección. */
    const cordonesCaptura = useMemo<CordonVisor[]>(() => {
        if (!marcaVisible) {
            return [];
        }
        const porCordon = new Map<number, EstadoCordon>();
        [...juntas, borrador].forEach((junta) => {
            if (junta.cordonId !== null && junta.junta) {
                porCordon.set(junta.cordonId, estadoJunta(junta) === 'Con defecto' ? 'defecto' : 'correcta');
            }
        });
        return marcaVisible.cordones.map((cordon) => ({ ...cordon, estado: porCordon.get(cordon.id) ?? 'sin' }));
    }, [marcaVisible, juntas, borrador]);

    const cordonElegido = cordonesCaptura.find((cordon) => cordon.id === cordonSel) ?? null;
    const conJunta = juntas.filter((junta) => junta.cordonId !== null).length;

    /** Abre la junta del cordón: la que ya estaba en la lista, o una nueva numerada como el cordón. */
    const abrirCordon = (cordon: CordonVisor) => {
        const existente = juntas.findIndex((junta) => junta.cordonId === cordon.id);
        if (existente >= 0) {
            setBorrador(juntas[existente]);
            onJuntas(juntas.filter((_, i) => i !== existente));
        } else {
            const tipo = cordon.tipo === 'filete' ? 'Filete' : 'Ranura';
            setBorrador({
                ...JUNTA_VACIA,
                junta: cordon.identificador,
                tipo,
                cordonId: cordon.id,
                puntos: tipo === 'Filete' ? { m_prepranura: 'n/a' } : { m_prepfilete: 'n/a' },
            });
        }
        setMapeoAbierto(true);
    };

    /** Lo primero del mapeo: la pieza con sus cordones, o por qué no está. */
    const visor3d = !modelo3d ? (
        <Pista className="mb-0">
            Escanea o teclea el QR de la pieza para ver su modelo 3D y capturar cada junta sobre su cordón.
        </Pista>
    ) : modelo3d.estado !== 'listo' || !modeloMarcaId ? (
        <Pista className="mb-0">
            {modelo3d.estado !== 'listo' && SIN_VISOR[modelo3d.estado]} Mientras, las juntas se numeran a mano.
        </Pista>
    ) : (
        <>
            <Pista>
                Toca un cordón de la pieza para capturar su junta. Naranja: falta revisarlo; verde: correcta; rojo:
                con defecto.
            </Pista>
            {falla3d && <p className="text-sm text-error">{falla3d}</p>}
            {!marcaVisible && !falla3d && <Pista>Cargando el modelo de la marca…</Pista>}
            {marcaVisible && (
                <>
                    <Visor
                        key={marcaVisible.glb_url}
                        glbUrl={marcaVisible.glb_url}
                        cordones={cordonesCaptura}
                        seleccionado={cordonSel}
                        onSeleccionar={setCordonSel}
                        className="h-[360px]"
                    />
                    <Pista className="mt-2 mb-0">
                        {conJunta} de {marcaVisible.cordones.length} cordones con junta en esta inspección.
                    </Pista>
                    {cordonElegido && (
                        <div className="mt-3">
                            <PanelCordon cordon={cordonElegido}>
                                <Boton tono="acero" onClick={() => abrirCordon(cordonElegido)}>
                                    {juntas.some((junta) => junta.cordonId === cordonElegido.id)
                                        ? `Editar la junta ${cordonElegido.identificador}`
                                        : `Capturar la junta ${cordonElegido.identificador}`}
                                </Boton>
                            </PanelCordon>
                        </div>
                    )}
                </>
            )}
        </>
    );

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
                        Inspección detallada <b>junta por junta</b> (formato de mapeo) sobre el modelo 3D de la pieza. Es
                        opcional: ábrelo sólo cuando toque mapear la pieza. El formulario normal de soldadura sigue
                        disponible.
                    </Pista>
                    <Boton tono="acero" onClick={() => setMapeoAbierto(!mapeoAbierto)}>
                        {mapeoAbierto ? 'Cerrar mapeo de soldaduras' : '🔧 Abrir mapeo de soldaduras'}
                    </Boton>
                </Tarjeta>
            )}

            {soldado && mapeoAbierto && (
                <Mapeo
                    juntas={juntas}
                    onJuntas={onJuntas}
                    borrador={borrador}
                    onBorrador={setBorrador}
                    soldadores={soldadores}
                    onAviso={onAviso}
                    modelo3d={visor3d}
                />
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
