/**
 * La pieza física de 2ª y pintura, por su QR.
 *
 * En 1ª la pieza es la marca y un consecutivo; de 2ª en adelante ya trae
 * etiqueta, y la inspección se amarra a esa unidad concreta. El código se
 * escanea con la cámara o se teclea —el QS también sirve, que es el número que
 * la gente de planta conoce—, y lo que responde el servidor llena la tarjeta:
 * marca, lote, peso y las inspecciones que la pieza ya lleva.
 */

import { useState } from 'react';
import { LectorQr, lectorDisponible } from './lector-qr';
import { Boton, Pastilla, Pista } from './ui';

export type InspeccionPrevia = {
    folio: string;
    fase: string;
    subetapa: string | null;
    numero_inspeccion: number;
    estatus: 'liberado' | 'rechazado' | 'pendiente';
    fecha: string;
};

/** Si la marca tiene modelo 3D convertido y, si no, qué le falta a la obra. */
export type Estado3d = 'listo' | 'convirtiendo' | 'sin_marca' | 'error' | 'sin_modelo';

export type PiezaResuelta = {
    id: number;
    qr: string;
    qs: string | null;
    etiqueta: string;
    obra_id: number;
    concepto: {
        id: number;
        marca: string;
        lote: string | null;
        descripcion: string | null;
        peso_unitario: string | null;
    };
    tipo_pieza_id: number | null;
    /** La marca en el modelo 3D ya convertido de la obra: con ella se monta el visor en soldado. */
    modelo_marca_id: number | null;
    modelo_3d: Estado3d;
    inspecciones: InspeccionPrevia[];
};

const TONO: Record<InspeccionPrevia['estatus'], 'lib' | 'rej' | 'pen'> = {
    liberado: 'lib',
    rechazado: 'rej',
    pendiente: 'pen',
};

const SUBETAPA: Record<string, string> = { armado_vestido: 'Armado', soldado: 'Soldado' };

export function PiezaFisica({
    obraId,
    pieza,
    onPieza,
    fija = false,
    onAviso,
}: {
    obraId: string;
    pieza: PiezaResuelta | null;
    /** Al corregir o reinspeccionar, la pieza es la de la inspección: no se cambia. */
    fija?: boolean;
    onPieza: (pieza: PiezaResuelta | null) => void;
    onAviso: (mensaje: string, tono?: 'ok' | 'error') => void;
}) {
    const [codigo, setCodigo] = useState('');
    const [camara, setCamara] = useState(false);
    const [buscando, setBuscando] = useState(false);
    const [candidatas, setCandidatas] = useState<string[]>([]);

    const buscar = async (valor: string) => {
        const limpio = valor.trim();
        if (!limpio) {
            onAviso('Escanea o teclea el código de la pieza', 'error');
            return;
        }

        setBuscando(true);
        setCandidatas([]);
        try {
            const consulta = new URLSearchParams({ codigo: limpio });
            if (obraId) {
                consulta.set('obra_id', obraId);
            }
            const respuesta = await fetch(`/admin/calidad/piezas/resolver?${consulta}`, {
                headers: { Accept: 'application/json' },
            });
            const cuerpo = await respuesta.json();

            if (respuesta.ok) {
                onPieza(cuerpo as PiezaResuelta);
                setCodigo('');
                return;
            }
            if (respuesta.status === 409) {
                setCandidatas(cuerpo.candidatas ?? []);
            }
            onAviso(cuerpo.message ?? 'No se encontró la pieza', 'error');
        } catch {
            onAviso('No se pudo consultar la pieza. Revisa la conexión.', 'error');
        } finally {
            setBuscando(false);
        }
    };

    if (pieza) {
        return (
            <div className="rounded-box border border-primary/40 bg-primary/5 p-3">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div className="text-lg font-extrabold text-primary">{pieza.concepto.marca}</div>
                        <div className="text-xs text-base-content/70">
                            {pieza.concepto.lote && <>Lote {pieza.concepto.lote} · </>}
                            {pieza.qs && <>QS {pieza.qs} · </>}QR {pieza.qr}
                        </div>
                        {pieza.concepto.descripcion && (
                            <div className="mt-0.5 text-xs text-base-content/60">{pieza.concepto.descripcion}</div>
                        )}
                    </div>
                    {!fija && (
                        <Boton tono="claro" onClick={() => onPieza(null)} className="btn-sm">
                            Cambiar pieza
                        </Boton>
                    )}
                </div>

                {pieza.inspecciones.length > 0 ? (
                    <div className="mt-3 border-t border-base-300 pt-2">
                        <div className="mb-1 text-xs font-semibold text-base-content/70">Inspecciones previas</div>
                        <ul className="space-y-1 text-xs">
                            {pieza.inspecciones.map((previa) => (
                                <li key={previa.folio} className="flex flex-wrap items-center gap-2">
                                    <span className="font-mono">{previa.folio}</span>
                                    <span>
                                        {previa.fase}
                                        {previa.subetapa && ` · ${SUBETAPA[previa.subetapa] ?? previa.subetapa}`} · #
                                        {previa.numero_inspeccion}
                                    </span>
                                    <Pastilla tono={TONO[previa.estatus]}>{previa.estatus}</Pastilla>
                                    <span className="text-base-content/60">{previa.fecha.slice(0, 10)}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                ) : (
                    <Pista className="mt-2 mb-0">Primera vez que se inspecciona esta pieza.</Pista>
                )}
            </div>
        );
    }

    return (
        <div>
            <Pista>
                Escanea el QR de la etiqueta. Si no se deja leer, teclea el QR o el QS de la pieza.
            </Pista>
            <div className="flex gap-2">
                <input
                    value={codigo}
                    onChange={(e) => setCodigo(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && buscar(codigo)}
                    placeholder="Código de la etiqueta"
                    className="input input-bordered min-w-0 flex-1 text-base"
                />
                <Boton onClick={() => buscar(codigo)} disabled={buscando}>
                    {buscando ? 'Buscando…' : 'Buscar'}
                </Boton>
            </div>

            {lectorDisponible() && !camara && (
                <Boton tono="acero" onClick={() => setCamara(true)} className="mt-2 w-full">
                    📷 Escanear QR
                </Boton>
            )}

            {camara && (
                <LectorQr
                    onLeido={(valor) => {
                        setCamara(false);
                        setCodigo(valor);
                        buscar(valor);
                    }}
                    onCerrar={() => setCamara(false)}
                />
            )}

            {candidatas.length > 0 && (
                <div className="mt-2 rounded-box bg-warning/10 px-3 py-2 text-xs">
                    <div className="font-semibold text-warning">El código es de más de una pieza:</div>
                    <ul className="mt-1 list-disc pl-4">
                        {candidatas.map((candidata) => (
                            <li key={candidata}>{candidata}</li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}
