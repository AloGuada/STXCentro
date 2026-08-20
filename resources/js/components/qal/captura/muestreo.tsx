/**
 * Muestreo AQL de un lote de piezas iguales.
 *
 * El inspector marca cada pieza de la muestra con UN TOQUE y sólo las que
 * fallan piden detalle. El lote se guarda como un solo registro: `cant` es el
 * tamaño del lote (lo que se libera) y `muestra` cuántas se miraron — así los
 * kilos cuadran con lo que sale de nave sin contar dos veces las piezas de la
 * muestra.
 */

import { useState } from 'react';
import { DISPOSICIONES } from './datos';
import { veredictoMuestreo, type PlanMuestreo } from './reglas';
import { Boton, CajaVeredicto, Campo, Pista, Progreso, Selector } from './ui';

export type EstadoMuestreo = {
    conformes: number;
    fallas: { detalle: string }[];
    disposicion: string;
};

export const MUESTREO_VACIO: EstadoMuestreo = { conformes: 0, fallas: [], disposicion: '' };

export function PanelMuestreo({
    plan,
    estado,
    onEstado,
    sustantivo = 'LOTE',
}: {
    plan: PlanMuestreo | null;
    estado: EstadoMuestreo;
    onEstado: (estado: EstadoMuestreo) => void;
    sustantivo?: string;
}) {
    const [detalle, setDetalle] = useState('');
    const [pidiendoDetalle, setPidiendoDetalle] = useState(false);

    if (!plan) {
        return <Pista>Indica primero el tamaño del lote.</Pista>;
    }

    const vistas = estado.conformes + estado.fallas.length;
    const completa = vistas >= plan.muestra;
    const veredicto = veredictoMuestreo(plan, estado.conformes, estado.fallas.length, sustantivo);

    const marcarConforme = () => {
        if (completa) {
            return;
        }
        onEstado({ ...estado, conformes: estado.conformes + 1 });
    };

    const anotarFalla = () => {
        onEstado({ ...estado, fallas: [...estado.fallas, { detalle: detalle.trim() }] });
        setDetalle('');
        setPidiendoDetalle(false);
    };

    const deshacer = () => {
        if (estado.fallas.length) {
            onEstado({ ...estado, fallas: estado.fallas.slice(0, -1) });
            return;
        }
        if (estado.conformes > 0) {
            onEstado({ ...estado, conformes: estado.conformes - 1 });
        }
    };

    const quitarFalla = (indice: number) => {
        onEstado({ ...estado, fallas: estado.fallas.filter((_, i) => i !== indice) });
    };

    return (
        <div>
            <p className="mt-3 text-[15px] font-extrabold text-primary">
                Inspeccionadas: {vistas} de {plan.muestra}
                {estado.fallas.length > 0 && <span className="text-error"> · {estado.fallas.length} con defecto</span>}
                <Progreso hechas={vistas} total={plan.muestra} />
            </p>

            <div className="mt-3 flex flex-wrap gap-[10px]">
                <Boton tono="ok" onClick={marcarConforme} disabled={completa} className="min-w-[130px] flex-1 py-4">
                    ✓ Conforme
                </Boton>
                <Boton
                    tono="rechazo"
                    onClick={() => setPidiendoDetalle(true)}
                    disabled={completa}
                    className="min-w-[130px] flex-1 py-4"
                >
                    ✗ Con defecto
                </Boton>
            </div>

            {pidiendoDetalle && (
                <div className="mt-3 rounded-[10px] border border-base-300 bg-base-200 p-3">
                    <Campo label="¿Qué defecto tiene esta pieza? (breve)">
                        <input
                            autoFocus
                            value={detalle}
                            onChange={(e) => setDetalle(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && anotarFalla()}
                            className="input input-bordered w-full text-base"
                        />
                    </Campo>
                    <div className="mt-2 flex gap-2">
                        <Boton tono="rechazo" onClick={anotarFalla}>
                            Añadir pieza con defecto
                        </Boton>
                        <Boton tono="claro" onClick={() => (setPidiendoDetalle(false), setDetalle(''))}>
                            Cancelar
                        </Boton>
                    </div>
                </div>
            )}

            {vistas > 0 && (
                <button type="button" onClick={deshacer} className="mt-2 text-xs text-base-content/60 underline">
                    Deshacer la última
                </button>
            )}

            {estado.fallas.length > 0 && (
                <div className="mt-[10px] text-[13px]">
                    {estado.fallas.map((falla, indice) => (
                        <div key={indice} className="mt-[5px] flex items-center gap-2 rounded-lg bg-error/10 px-[9px] py-[6px]">
                            <b className="text-error">#{indice + 1}</b>
                            <span className="flex-1">{falla.detalle || 'sin detalle'}</span>
                            <button type="button" onClick={() => quitarFalla(indice)} className="text-error">
                                ✕
                            </button>
                        </div>
                    ))}
                </div>
            )}

            {veredicto && <CajaVeredicto texto={veredicto.texto} rechazado={veredicto.rechazado} cerrado={veredicto.cerrado} />}

            {veredicto?.rechazado && (
                <div className="mt-3 rounded-[10px] bg-warning/10 px-[13px] py-[11px]">
                    <Campo label={`¿Qué se hizo con el ${sustantivo.toLowerCase()} rechazado?`}>
                        <Selector
                            value={estado.disposicion}
                            onChange={(disposicion) => onEstado({ ...estado, disposicion })}
                            opciones={DISPOSICIONES}
                            vacio="Sin definir — pendiente de decidir"
                        />
                    </Campo>
                    <Pista className="mt-2 mb-0">
                        Si se queda en <b>Sin definir</b> aparecerá en el tablero como pendiente de disposición. Es a
                        propósito: un lote rechazado sin decisión es material que se puede perder.
                    </Pista>
                </div>
            )}
        </div>
    );
}
