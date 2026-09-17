/**
 * La ficha de un cordón del modelo: lo que se sabe de él sólo con la geometría.
 *
 * Los mínimos son de AISC 360 (J2.4 y J2.2b) y la preparación es la junta
 * prequalificada de AWS D1.1 que corresponde al ángulo y al espesor. El cateto
 * de diseño no está: depende de las fuerzas de la conexión, que el IFC no
 * trae. Por eso el mínimo se enseña como piso, nunca como el tamaño del cordón.
 */

import type { ReactNode } from 'react';
import { ETIQUETA_ESTADO, type CordonVisor } from './tipos';

const JUNTA: Record<string, string> = { T: 'en T', solape: 'de solape', tope: 'a tope' };

function Fila({ etiqueta, children }: { etiqueta: string; children: ReactNode }) {
    return (
        <div className="flex justify-between gap-3 border-b border-base-200 py-1 last:border-0">
            <span className="text-base-content/60">{etiqueta}</span>
            <span className="text-right font-semibold">{children}</span>
        </div>
    );
}

const mm = (valor: string | null) => (valor === null ? '—' : `${Number(valor)} mm`);

export function PanelCordon({ cordon, children }: { cordon: CordonVisor; children?: ReactNode }) {
    const tono = cordon.estado === 'defecto' ? 'text-error' : cordon.estado === 'correcta' ? 'text-success' : 'text-warning';

    return (
        <div className="rounded-box border border-base-300 bg-base-100 p-3 text-sm">
            <div className="mb-2 flex flex-wrap items-center gap-2">
                <span className="text-lg font-extrabold text-primary">{cordon.identificador}</span>
                <span className="badge badge-ghost badge-sm">
                    {cordon.tipo === 'filete' ? 'Filete' : 'Costura'} {cordon.junta && JUNTA[cordon.junta] ? JUNTA[cordon.junta] : ''}
                </span>
                <span className={`text-xs font-semibold ${tono}`}>
                    {ETIQUETA_ESTADO[cordon.estado]}
                    {(cordon.correctas > 0 || cordon.con_defecto > 0) &&
                        ` · ${cordon.correctas} pieza(s) bien, ${cordon.con_defecto} con defecto`}
                </span>
            </div>

            <Fila etiqueta="Largo">{mm(cordon.largo_mm)}</Fila>
            {cordon.angulo !== null && <Fila etiqueta="Ángulo del rincón">{Number(cordon.angulo)}°</Fila>}
            <Fila etiqueta="Espesores (apoya / base)">
                {mm(cordon.t1_mm)} / {mm(cordon.t2_mm)}
            </Fila>
            {cordon.tipo === 'filete' && (
                <>
                    <Fila etiqueta="Cateto mínimo AISC J2.4">{mm(cordon.cateto_min_mm)}</Fila>
                    <Fila etiqueta="Cateto máximo por borde">{mm(cordon.cateto_max_mm)}</Fila>
                    <Fila etiqueta="Garganta mínima">{mm(cordon.garganta_min_mm)}</Fila>
                </>
            )}
            {cordon.preparacion && (
                <Fila etiqueta="Preparación sugerida">
                    {cordon.preparacion.bisel}
                    {cordon.preparacion.angulo_bisel ? ` ${cordon.preparacion.angulo_bisel}°` : ''} ·{' '}
                    {cordon.preparacion.lados === 2 ? 'dos caras' : 'una cara'}
                    <div className="text-xs font-normal text-base-content/60">{cordon.preparacion.nota}</div>
                </Fila>
            )}
            {cordon.avisos.length > 0 && (
                <ul className="mt-2 list-disc space-y-0.5 pl-4 text-xs text-warning">
                    {cordon.avisos.map((aviso) => (
                        <li key={aviso}>{aviso}</li>
                    ))}
                </ul>
            )}

            {children && <div className="mt-3 flex flex-wrap gap-2">{children}</div>}
        </div>
    );
}
