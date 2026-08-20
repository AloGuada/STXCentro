/**
 * El estado del formulario.
 *
 * Se guarda plano —un diccionario de `id → valor` con las mismas claves que el
 * formulario anterior (`p1_defl`, `p2_bisel`, `p3_req`…)— y es deliberado: son
 * las claves que reconocen los inspectores y las que hoy nombran las columnas.
 * Cuando el backend exista, cada una será una fila de `qal_inspeccion_puntos`
 * apuntando a su punto del catálogo, y este diccionario es exactamente el mapa
 * que hace falta para esa traducción.
 */

import { useCallback, useState } from 'react';

export type Campos = {
    /** Valor de un campo; cadena vacía si no se ha capturado. */
    v: (id: string) => string;
    set: (id: string, valor: string) => void;
    /** Vacía varios campos de golpe (cambio de fase, re-inspección, limpiar). */
    limpiar: (ids: string[]) => void;
};

export function useCampos(iniciales: Record<string, string> = {}): [Campos, () => void] {
    const [valores, setValores] = useState<Record<string, string>>(iniciales);

    const v = useCallback((id: string) => valores[id] ?? '', [valores]);
    const set = useCallback((id: string, valor: string) => setValores((previos) => ({ ...previos, [id]: valor })), []);
    const limpiar = useCallback(
        (ids: string[]) =>
            setValores((previos) => {
                const copia = { ...previos };
                ids.forEach((id) => delete copia[id]);
                return copia;
            }),
        [],
    );
    const reiniciar = useCallback(() => setValores(iniciales), [iniciales]);

    return [{ v, set, limpiar }, reiniciar];
}

/** Una junta del mapeo, tal como se añade a la lista de la pieza. */
export type Junta = {
    junta: string;
    tipo: string;
    soldador: string;
    /** Resultado de cada punto de `PUNTOS_MAPEO`. */
    puntos: Record<string, string>;
    espesorRequerido: string;
    espesorMedido: string;
};

/** Una unidad rechazada del sublote de accesorios, por familias. */
export type PiezaRechazada = {
    soldadura: string[];
    dimensional: string[];
    barrenos: string[];
    limpieza: boolean;
};

export function textoPiezaRechazada(pieza: PiezaRechazada): string {
    const partes: string[] = [];
    if (pieza.soldadura.length) {
        partes.push(`Soldadura: ${pieza.soldadura.join(', ')}`);
    }
    if (pieza.dimensional.length) {
        partes.push(`Dimensional: ${pieza.dimensional.join(', ')}`);
    }
    if (pieza.barrenos.length) {
        partes.push(`Barrenos: ${pieza.barrenos.join(', ')}`);
    }
    if (pieza.limpieza) {
        partes.push('Falta de limpieza');
    }

    return partes.join(' · ');
}
