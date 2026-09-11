import { ArrowDownIcon, ArrowUpIcon, CornerDownRightIcon, StickyNoteIcon, Trash2Icon } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import type { NodoArbol, NodoEditable, NodoParaGuardar } from './tipos';

/** Hasta 1.1.3.2: la profundidad del índice más hondo que usa Steelex. Igual que en el servidor. */
export const PROFUNDIDAD_MAXIMA = 4;

type Ruta = number[];

const nuevaClave = () => (typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `n-${Date.now()}-${Math.random()}`);

export function editableDe(arbol: NodoArbol[]): NodoEditable[] {
    return arbol.map((nodo) => ({
        clave: `s-${nodo.id}`,
        id: nodo.id,
        titulo: nodo.titulo,
        nota: nodo.nota ?? '',
        hijos: editableDe(nodo.hijos),
    }));
}

export function paraGuardar(nodos: NodoEditable[]): NodoParaGuardar[] {
    return nodos.map((nodo) => ({
        id: nodo.id,
        titulo: nodo.titulo.trim(),
        nota: nodo.nota.trim() === '' ? null : nodo.nota.trim(),
        hijos: paraGuardar(nodo.hijos),
    }));
}

export function contarSecciones(nodos: { hijos: unknown[] }[]): number {
    return nodos.reduce((total, nodo) => total + 1 + contarSecciones(nodo.hijos as { hijos: unknown[] }[]), 0);
}

/** Aplica `cambio` a la lista de hermanos que cuelga de `rutaPadre`. */
function conHermanos(nodos: NodoEditable[], rutaPadre: Ruta, cambio: (hermanos: NodoEditable[]) => NodoEditable[]): NodoEditable[] {
    if (rutaPadre.length === 0) {
        return cambio(nodos);
    }

    return nodos.map((nodo, i) =>
        i === rutaPadre[0] ? { ...nodo, hijos: conHermanos(nodo.hijos, rutaPadre.slice(1), cambio) } : nodo,
    );
}

const padreDe = (ruta: Ruta) => ruta.slice(0, -1);
const ultimo = (ruta: Ruta) => ruta[ruta.length - 1];
const vacia = (): NodoEditable => ({ clave: nuevaClave(), id: null, titulo: '', nota: '', hijos: [] });

type EditorProps = {
    valor: NodoEditable[];
    onChange: (valor: NodoEditable[]) => void;
};

/**
 * El editor de un árbol de secciones: título en línea, nota, subir y bajar,
 * añadir subsección y quitar. El número se calcula en vivo de la posición,
 * igual que en el servidor, así que mover una sección renumera todo lo demás.
 *
 * Trabaja sobre una copia: nada se guarda hasta que la pantalla manda el árbol
 * entero.
 */
export function EditorDeArbol({ valor, onChange }: EditorProps) {
    const [conNota, setConNota] = useState<Set<string>>(() => new Set());

    const editar = (ruta: Ruta, cambios: Partial<NodoEditable>) =>
        onChange(conHermanos(valor, padreDe(ruta), (h) => h.map((n, j) => (j === ultimo(ruta) ? { ...n, ...cambios } : n))));

    const mover = (ruta: Ruta, paso: -1 | 1) =>
        onChange(
            conHermanos(valor, padreDe(ruta), (h) => {
                const copia = [...h];
                const i = ultimo(ruta);
                [copia[i], copia[i + paso]] = [copia[i + paso], copia[i]];

                return copia;
            }),
        );

    const quitar = (ruta: Ruta, nodo: NodoEditable) => {
        const hijas = contarSecciones(nodo.hijos);

        if (hijas > 0 && !confirm(`«${nodo.titulo || 'Sin título'}» tiene ${hijas} subsección(es). ¿Quitarla con todas ellas?`)) {
            return;
        }

        onChange(conHermanos(valor, padreDe(ruta), (h) => h.filter((_, j) => j !== ultimo(ruta))));
    };

    const agregarHija = (ruta: Ruta) =>
        onChange(conHermanos(valor, padreDe(ruta), (h) => h.map((n, j) => (j === ultimo(ruta) ? { ...n, hijos: [...n.hijos, vacia()] } : n))));

    const alternarNota = (clave: string) =>
        setConNota((actual) => {
            const siguiente = new Set(actual);

            if (siguiente.has(clave)) {
                siguiente.delete(clave);
            } else {
                siguiente.add(clave);
            }

            return siguiente;
        });

    const renglones = (nodos: NodoEditable[], prefijo: string, rutaPadre: Ruta): ReactNode =>
        nodos.map((nodo, i) => {
            const ruta = [...rutaPadre, i];
            const numero = prefijo === '' ? `${i + 1}` : `${prefijo}.${i + 1}`;
            const nivel = ruta.length;
            const notaAbierta = conNota.has(nodo.clave) || nodo.nota !== '';

            return (
                <li key={nodo.clave}>
                    <div className="hover:bg-base-200/60 flex items-center gap-1.5 rounded-md py-1 pr-1" style={{ paddingLeft: `${(nivel - 1) * 1.5}rem` }}>
                        <span className={`w-14 shrink-0 font-mono text-xs ${nivel === 1 ? 'font-bold' : 'text-base-content/60'}`}>{numero}</span>
                        <input
                            className={`input input-sm input-bordered min-w-0 flex-1 ${nivel === 1 ? 'font-semibold' : ''} ${nodo.titulo.trim() === '' ? 'input-error' : ''}`}
                            value={nodo.titulo}
                            maxLength={160}
                            placeholder="Título de la sección"
                            onChange={(e) => editar(ruta, { titulo: e.target.value })}
                        />
                        <div className="flex shrink-0">
                            <button type="button" className="btn btn-ghost btn-xs" title="Subir" disabled={i === 0} onClick={() => mover(ruta, -1)}>
                                <ArrowUpIcon className="size-3.5" />
                            </button>
                            <button
                                type="button"
                                className="btn btn-ghost btn-xs"
                                title="Bajar"
                                disabled={i === nodos.length - 1}
                                onClick={() => mover(ruta, 1)}
                            >
                                <ArrowDownIcon className="size-3.5" />
                            </button>
                            <button
                                type="button"
                                className="btn btn-ghost btn-xs"
                                title={nivel >= PROFUNDIDAD_MAXIMA ? 'Es el nivel más hondo' : 'Añadir subsección'}
                                disabled={nivel >= PROFUNDIDAD_MAXIMA}
                                onClick={() => agregarHija(ruta)}
                            >
                                <CornerDownRightIcon className="size-3.5" />
                            </button>
                            <button
                                type="button"
                                className={`btn btn-ghost btn-xs ${nodo.nota !== '' ? 'text-warning' : ''}`}
                                title="Nota de la sección"
                                onClick={() => alternarNota(nodo.clave)}
                            >
                                <StickyNoteIcon className="size-3.5" />
                            </button>
                            <button type="button" className="btn btn-ghost btn-xs text-error" title="Quitar" onClick={() => quitar(ruta, nodo)}>
                                <Trash2Icon className="size-3.5" />
                            </button>
                        </div>
                    </div>
                    {notaAbierta && (
                        <div className="pb-1" style={{ paddingLeft: `${(nivel - 1) * 1.5 + 3.9}rem` }}>
                            <textarea
                                className="textarea textarea-bordered textarea-xs w-full"
                                rows={2}
                                maxLength={2000}
                                placeholder="Qué va en esta sección, para quien arme el dosier"
                                value={nodo.nota}
                                onChange={(e) => editar(ruta, { nota: e.target.value })}
                            />
                        </div>
                    )}
                    {nodo.hijos.length > 0 && <ul>{renglones(nodo.hijos, numero, ruta)}</ul>}
                </li>
            );
        });

    return (
        <div>
            {valor.length === 0 ? (
                <div className="text-base-content/50 rounded-md border border-dashed p-6 text-center text-sm">
                    Sin secciones. Añade la primera abajo.
                </div>
            ) : (
                <ul>{renglones(valor, '', [])}</ul>
            )}
            <button type="button" className="btn btn-sm btn-outline mt-3" onClick={() => onChange([...valor, vacia()])}>
                + Sección principal
            </button>
        </div>
    );
}

type LecturaProps = {
    arbol: NodoArbol[];
    /** La sección elegida, si el árbol sirve para navegar. */
    elegida?: number | null;
    onElegir?: (nodo: NodoArbol) => void;
    /** Lo que va a la derecha de cada renglón (p. ej. cuántos PDF tiene la sección). */
    extra?: (nodo: NodoArbol) => ReactNode;
};

/** El mismo árbol, sólo para leer o para elegir una sección. */
export function ArbolDeLectura({ arbol, elegida = null, onElegir, extra }: LecturaProps) {
    const renglones = (nodos: NodoArbol[], nivel: number): ReactNode =>
        nodos.map((nodo) => (
            <li key={nodo.id}>
                <button
                    type="button"
                    disabled={!onElegir}
                    onClick={() => onElegir?.(nodo)}
                    className={`flex w-full items-start gap-2 rounded-md py-1 pr-2 text-left text-sm ${
                        elegida === nodo.id ? 'bg-primary/10' : onElegir ? 'hover:bg-base-200' : ''
                    }`}
                    style={{ paddingLeft: `${(nivel - 1) * 1.25 + 0.25}rem` }}
                >
                    <span className={`w-12 shrink-0 font-mono text-xs ${nivel === 1 ? 'font-bold' : 'text-base-content/60'}`}>{nodo.numero}</span>
                    <span className={`min-w-0 flex-1 ${nivel === 1 ? 'font-semibold' : ''}`}>
                        {nodo.titulo}
                        {nodo.nota && <span className="text-base-content/50 block text-xs">{nodo.nota}</span>}
                    </span>
                    {extra?.(nodo)}
                </button>
                {nodo.hijos.length > 0 && <ul>{renglones(nodo.hijos, nivel + 1)}</ul>}
            </li>
        ));

    return <ul>{renglones(arbol, 1)}</ul>;
}
