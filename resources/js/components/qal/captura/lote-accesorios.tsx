/**
 * Lote de accesorios — 2ª y 3ª transformación.
 *
 * Un lote de accesorios NO es una pieza: son cientos de unidades iguales de la
 * misma marca que llegan en entregas, a módulos y días distintos, y se
 * controlan por muestreo. Por eso el formulario cambia entero: la pieza se
 * inspecciona al 100% y el lote se estima a partir de una muestra, y promediar
 * las dos cosas daría números que parecen buenos y no significan nada.
 *
 * Los datos de la marca son del lote entero y se heredan de la entrega
 * anterior; el muestreo es de esta entrega. Las unidades rechazadas se
 * clasifican con los defectos del catálogo, por familia.
 *
 * La misma marca se revisa dos veces: soldada en 2ª y pintada en 3ª. Es el
 * mismo muestreo y son dos entregas distintas, cada una con las familias de
 * defecto de su etapa — de ahí `esPintura`.
 */

import { useState } from 'react';
import { DISPOSICIONES, type NivelMuestreo } from './datos';
import { textoPiezaRechazada, type Campos, type PiezaRechazada } from './estado';
import { planMuestreo, veredictoMuestreo } from './reglas';
import { Boton, CajaVeredicto, Campo, Chips, Pista, Progreso, Rejilla, Selector, Tarjeta, Texto } from './ui';

const NIVELES: [string, string][] = [
    ['I', 'I — reducida'],
    ['II', 'II — normal'],
    ['III', 'III — severa'],
];

const RECHAZADA_VACIA: PiezaRechazada = { soldadura: [], dimensional: [], barrenos: [], limpieza: false, pintura: [] };

/** Un lote ya declarado en la obra: su marca hereda estos datos. */
export type LoteDeObra = {
    id: number;
    marca: string;
    descripcion: string | null;
    total_unidades: number;
    kg_unitario: string | null;
    elementos_unitarios: number | null;
    /** Por fase: las mismas unidades se reciben soldadas en 2ª y vuelven pintadas en 3ª. */
    avance: Record<string, { recibidas: number; sublotes: number }>;
};

/** Los nombres de defecto de cada familia, del catálogo. */
export type FamiliasDeDefecto = { soldadura: string[]; dimensional: string[]; barrenos: string[]; pintura: string[] };

export function LoteAccesorios({
    campos,
    conformes,
    onConformes,
    rechazadas,
    onRechazadas,
    disposicion,
    onDisposicion,
    familias,
    lotes,
    numero,
    marcaFija,
    esPintura = false,
    onAviso,
}: {
    campos: Campos;
    conformes: number;
    onConformes: (conformes: number) => void;
    rechazadas: PiezaRechazada[];
    onRechazadas: (rechazadas: PiezaRechazada[]) => void;
    disposicion: string;
    onDisposicion: (disposicion: string) => void;
    familias: FamiliasDeDefecto;
    lotes: LoteDeObra[];
    /** Qué inspección de este sublote es: 2 o más en una reinspección. */
    numero: number;
    /** Al corregir o reinspeccionar, la marca no cambia: es la identidad del lote. */
    marcaFija: boolean;
    /** La entrega se revisa ya pintada (3ª): sólo se rechaza por defectos de pintura. */
    esPintura?: boolean;
    onAviso: (mensaje: string, tono?: 'ok' | 'error') => void;
}) {
    const [modal, setModal] = useState(false);
    const [borrador, setBorrador] = useState<PiezaRechazada>(RECHAZADA_VACIA);

    const unidades = parseInt(campos.v('ac_unid'), 10);
    const nivel = (campos.v('ac_nivel') || 'II') as NivelMuestreo;
    // El sublote nunca inspecciona más unidades de las que trae la entrega.
    const plan = planMuestreo(unidades, nivel, unidades);
    const vistas = conformes + rechazadas.length;
    const completa = plan ? vistas >= plan.muestra : false;
    const veredicto = veredictoMuestreo(plan, conformes, rechazadas.length, 'SUBLOTE');

    const marca = campos.v('ac_marca').trim().toUpperCase();
    const existente = lotes.find((lote) => lote.marca === marca);

    // El avance que importa es el de la etapa que se está capturando: la misma
    // marca puede llevar 8 entregas soldadas y ninguna pintada.
    const faseTexto = esPintura ? '3ª (pintura)' : '2ª (soldadura)';
    const avanceDeLaFase = existente?.avance[esPintura ? '3ª' : '2ª'] ?? { recibidas: 0, sublotes: 0 };

    /** Si la marca ya existe, sus datos se rellenan solos donde estén vacíos. */
    const heredar = () => {
        if (!existente) {
            return;
        }
        const poner = (id: string, valor: string | number | null) => {
            if (!campos.v(id) && valor !== null && valor !== '') {
                campos.set(id, String(valor));
            }
        };
        poner('ac_desc', existente.descripcion);
        poner('ac_total', existente.total_unidades);
        poner('ac_kg', existente.kg_unitario === null ? null : Number(existente.kg_unitario));
        poner('ac_elem', existente.elementos_unitarios);
    };

    const aceptarDefecto = () => {
        const algo = esPintura
            ? borrador.pintura.length
            : borrador.soldadura.length || borrador.dimensional.length || borrador.barrenos.length || borrador.limpieza;
        if (!algo) {
            onAviso('Marca al menos un defecto', 'error');
            return;
        }
        onRechazadas([...rechazadas, borrador]);
        setBorrador(RECHAZADA_VACIA);
        setModal(false);
    };

    return (
        <>
            <Tarjeta titulo="Datos de la marca" etiqueta="lote completo">
                <Pista>
                    Si la marca ya se registró antes, estos campos se rellenan solos. Los datos de arriba (obra,
                    módulo, línea, responsable, fecha) son los de <b>esta entrega</b> y sí cambian.
                </Pista>
                <Rejilla cols={3}>
                    <Campo label="Marca del accesorio" req>
                        <Texto
                            value={campos.v('ac_marca')}
                            onChange={(valor) => campos.set('ac_marca', valor)}
                            onBlur={heredar}
                            readOnly={marcaFija}
                            placeholder="ej. ACC-123"
                            mayusculas
                        />
                    </Campo>
                    <Campo label="Descripción">
                        <Texto
                            value={campos.v('ac_desc')}
                            onChange={(valor) => campos.set('ac_desc', valor)}
                            placeholder="ej. 2 ángulos unidos por tubo"
                        />
                    </Campo>
                    <Campo label="Total de unidades de la marca" req>
                        <Texto tipo="number" value={campos.v('ac_total')} onChange={(valor) => campos.set('ac_total', valor)} placeholder="ej. 1600" />
                    </Campo>
                    <Campo label="Peso por unidad (kg)">
                        <Texto tipo="number" paso="0.01" value={campos.v('ac_kg')} onChange={(valor) => campos.set('ac_kg', valor)} placeholder="ej. 4.5" />
                    </Campo>
                    <Campo label="Elementos por unidad">
                        <Texto tipo="number" value={campos.v('ac_elem')} onChange={(valor) => campos.set('ac_elem', valor)} placeholder="ej. 3" />
                    </Campo>
                </Rejilla>

                {existente && !marcaFija && (
                    <p className="mt-3 rounded-[9px] bg-warning/10 px-[11px] py-[9px] text-xs">
                        <b>Esta marca ya existe.</b> En {faseTexto} van{' '}
                        <b>{avanceDeLaFase.recibidas.toLocaleString('es-MX')}</b> de{' '}
                        {existente.total_unidades.toLocaleString('es-MX')} unidades en {avanceDeLaFase.sublotes}{' '}
                        sublote(s). Este sería el <b>sublote #{avanceDeLaFase.sublotes + 1}</b>.
                    </p>
                )}
            </Tarjeta>

            <Tarjeta titulo="Muestreo de este sublote (AQL 10)">
                <Pista>
                    Todas las piezas son iguales: se marca conforme con un toque y sólo las que fallan piden detalle.
                    El número también se puede llevar a mano.
                </Pista>
                <Rejilla cols={3}>
                    <Campo label="Unidades de esta entrega" req>
                        <Texto tipo="number" value={campos.v('ac_unid')} onChange={(valor) => campos.set('ac_unid', valor)} placeholder="ej. 300" />
                    </Campo>
                    <Campo label="Nivel de inspección">
                        <Selector value={nivel} onChange={(valor) => campos.set('ac_nivel', valor)} opciones={NIVELES} vacio={null} />
                    </Campo>
                    <Campo label="Muestra a inspeccionar">
                        <Texto value={plan ? String(plan.muestra) : ''} readOnly placeholder="—" className="font-bold" />
                    </Campo>
                    <Campo label="# Inspección de este sublote">
                        <Texto value={String(numero)} readOnly className="max-w-[170px]" />
                    </Campo>
                </Rejilla>

                {numero > 1 && (
                    <Pista className="mt-2">
                        🔄 <b>Reinspección</b> de un sublote que salió rechazado. El muestreo se hace de nuevo desde cero
                        y al guardar se crea un registro <b>nuevo</b>: el anterior se conserva como historial.
                    </Pista>
                )}

                {plan && (
                    <Pista className="mt-2">
                        Inspecciona {plan.muestra} de {plan.lote} · Acepta el sublote si ≤ {plan.aceptacion} rechazadas ·
                        Recházalo si ≥ {plan.rechazo} rechazadas.
                    </Pista>
                )}

                {!plan ? (
                    <Pista>Indica primero las unidades de la entrega.</Pista>
                ) : (
                    <div>
                        <p className="mt-3 text-[15px] font-extrabold text-primary">
                            Inspeccionadas: {vistas} de {plan.muestra}
                            {rechazadas.length > 0 && <span className="text-error"> · {rechazadas.length} con defecto</span>}
                            <Progreso hechas={vistas} total={plan.muestra} />
                        </p>

                        <div className="mt-3 flex flex-wrap gap-[10px]">
                            <Boton
                                tono="ok"
                                disabled={completa}
                                onClick={() => onConformes(conformes + 1)}
                                className="min-w-[130px] flex-1 py-4"
                            >
                                ✓ Conforme
                            </Boton>
                            <Boton
                                tono="rechazo"
                                disabled={completa}
                                onClick={() => setModal(true)}
                                className="min-w-[130px] flex-1 py-4"
                            >
                                ✗ Con defecto
                            </Boton>
                        </div>

                        <div className="mt-3 max-w-[280px]">
                            <Campo label="Conformes (editable a mano)">
                                <div className="flex gap-2">
                                    <button
                                        type="button"
                                        onClick={() => onConformes(Math.max(0, conformes - 1))}
                                        className="btn btn-outline w-12 px-0 text-xl font-extrabold"
                                    >
                                        −
                                    </button>
                                    <input
                                        type="number"
                                        inputMode="numeric"
                                        value={conformes}
                                        onChange={(e) => {
                                            const valor = parseInt(e.target.value, 10);
                                            const tope = Math.max(0, plan.muestra - rechazadas.length);
                                            onConformes(Math.min(tope, Number.isNaN(valor) ? 0 : Math.max(0, valor)));
                                        }}
                                        className="input input-bordered w-full text-center text-base font-extrabold"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => onConformes(Math.min(Math.max(0, plan.muestra - rechazadas.length), conformes + 1))}
                                        className="btn btn-outline w-12 px-0 text-xl font-extrabold"
                                    >
                                        +
                                    </button>
                                </div>
                            </Campo>
                        </div>

                        {rechazadas.length > 0 && (
                            <div className="mt-[11px]">
                                {rechazadas.map((pieza, indice) => (
                                    <div
                                        key={indice}
                                        className="mt-[6px] flex items-start gap-[9px] rounded-[9px] bg-error/10 px-[11px] py-2 text-[12.5px]"
                                    >
                                        <b className="text-error">#{indice + 1}</b>
                                        <span className="flex-1">{textoPiezaRechazada(pieza)}</span>
                                        <button
                                            type="button"
                                            onClick={() => onRechazadas(rechazadas.filter((_, i) => i !== indice))}
                                            className="font-extrabold text-error"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}

                        {veredicto && <CajaVeredicto texto={veredicto.texto} rechazado={veredicto.rechazado} cerrado={veredicto.cerrado} />}

                        {veredicto?.rechazado && (
                            <div className="mt-3 rounded-[10px] bg-warning/10 px-[13px] py-[11px]">
                                <Campo label="¿Qué se hizo con el sublote rechazado?">
                                    <Selector
                                        value={disposicion}
                                        onChange={onDisposicion}
                                        opciones={DISPOSICIONES}
                                        vacio="Sin definir — pendiente de decidir"
                                    />
                                </Campo>
                                <Pista className="mt-2 mb-0">
                                    Si se queda en <b>Sin definir</b> aparecerá marcado en Registros. Es a propósito: un
                                    sublote rechazado sin decisión es material que se puede perder.
                                </Pista>
                            </div>
                        )}
                    </div>
                )}
            </Tarjeta>

            {modal && (
                <div className="fixed inset-0 z-[400] flex items-start justify-center overflow-y-auto bg-[rgba(16,24,40,.55)] p-5">
                    <div className="w-full max-w-[620px] rounded-2xl bg-base-100 p-5">
                        <Pista className="mt-0">
                            {esPintura
                                ? 'Marca el defecto de pintura por el que se rechaza la unidad. Puede tener varios.'
                                : 'Marca todas las familias que apliquen. Una misma pieza puede fallar por varias a la vez.'}
                        </Pista>

                        {/* Pintada sólo se juzga la pintura: lo de soldadura y barrenos
                            se revisó en 2ª, y volver a preguntarlo aquí contaría dos
                            veces el mismo defecto. */}
                        {esPintura ? (
                            <FamiliaDefecto
                                titulo="Defecto de pintura"
                                opciones={familias.pintura}
                                valor={borrador.pintura}
                                onChange={(pintura) => setBorrador({ ...borrador, pintura })}
                            />
                        ) : (
                            <>
                                <FamiliaDefecto
                                    titulo="Fallo de soldadura"
                                    opciones={familias.soldadura}
                                    valor={borrador.soldadura}
                                    onChange={(soldadura) => setBorrador({ ...borrador, soldadura })}
                                />
                                <FamiliaDefecto
                                    titulo="Fallo dimensional"
                                    opciones={familias.dimensional}
                                    valor={borrador.dimensional}
                                    onChange={(dimensional) => setBorrador({ ...borrador, dimensional })}
                                />
                                <FamiliaDefecto
                                    titulo="Fallo de barrenos habilitados"
                                    opciones={familias.barrenos}
                                    valor={borrador.barrenos}
                                    onChange={(barrenos) => setBorrador({ ...borrador, barrenos })}
                                />

                                <label className="mb-[10px] flex items-center gap-[9px] rounded-[11px] border border-base-300 p-3 font-extrabold text-primary">
                                    <input
                                        type="checkbox"
                                        checked={borrador.limpieza}
                                        onChange={(e) => setBorrador({ ...borrador, limpieza: e.target.checked })}
                                        className="size-5"
                                    />
                                    Falta de limpieza
                                </label>
                            </>
                        )}

                        <div className="mt-[14px] flex flex-wrap gap-[9px]">
                            <Boton tono="rechazo" onClick={aceptarDefecto}>
                                Añadir pieza rechazada
                            </Boton>
                            <Boton tono="claro" onClick={() => (setModal(false), setBorrador(RECHAZADA_VACIA))}>
                                Cancelar
                            </Boton>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

/**
 * Familia de defecto del modal: se despliega al marcarla. Marcada sin elegir
 * tipo entra como «Otro» cuando el catálogo lo tiene, en vez de perderse.
 */
function FamiliaDefecto({
    titulo,
    opciones,
    valor,
    onChange,
}: {
    titulo: string;
    opciones: string[];
    valor: string[];
    onChange: (valor: string[]) => void;
}) {
    const [abierta, setAbierta] = useState(false);
    const porDefecto = opciones.includes('Otro') ? ['Otro'] : [];

    return (
        <div className={`mb-[10px] rounded-[11px] border p-3 ${abierta ? 'border-warning/40 bg-warning/5' : 'border-base-300'}`}>
            <label className="flex items-center gap-[9px] text-[13.5px] font-extrabold text-primary">
                <input
                    type="checkbox"
                    checked={abierta}
                    onChange={(e) => {
                        setAbierta(e.target.checked);
                        onChange(e.target.checked ? (valor.length ? valor : porDefecto) : []);
                    }}
                    className="size-5"
                />
                {titulo}
            </label>
            {abierta && (
                <div className="mt-[10px]">
                    <Chips opciones={opciones} valor={valor} onChange={(seleccion) => onChange(seleccion.length ? seleccion : porDefecto)} />
                </div>
            )}
        </div>
    );
}
