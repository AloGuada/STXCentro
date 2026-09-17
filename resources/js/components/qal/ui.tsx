/**
 * El kit de interfaz del módulo de Calidad.
 *
 * Nació con el avance de producción y lo comparten ya las pantallas de
 * incidencias, así que vive fuera de la carpeta de una sola: un kit compartido
 * dentro del cajón de una pantalla se acaba duplicando.
 *
 * El color aquí significa una sola cosa, como en el resto del módulo: verde va
 * bien, ámbar vigilar, rojo actuar. Nada decorativo — quien mira esta pantalla
 * en la reunión del lunes decide con el color antes de leer el número.
 */

import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type Tono = 'ok' | 'warn' | 'error' | 'info' | 'neutro';

const TONOS: Record<Tono, string> = {
    ok: 'bg-success/15 text-success',
    warn: 'bg-warning/15 text-warning',
    error: 'bg-error/15 text-error',
    info: 'bg-info/15 text-info',
    neutro: 'bg-base-300 text-base-content/70',
};

export function Pastilla({ tono = 'neutro', children }: { tono?: Tono; children: ReactNode }) {
    return <span className={cn('rounded-full px-2.5 py-0.5 text-xs font-bold', TONOS[tono])}>{children}</span>;
}

/** Una tarjeta con su título, su nota al lado y sus acciones a la derecha. */
export function Tarjeta({
    titulo,
    nota,
    acciones,
    children,
}: {
    titulo: string;
    nota?: string;
    acciones?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="border-base-300 bg-base-100 rounded-xl border">
            <header className="border-base-300 flex flex-wrap items-center gap-x-3 gap-y-1 border-b px-4 py-3">
                <h2 className="font-semibold">{titulo}</h2>
                {nota && <span className="text-base-content/60 text-sm">{nota}</span>}
                {acciones && <div className="ml-auto flex items-center gap-2">{acciones}</div>}
            </header>
            {children}
        </section>
    );
}

/**
 * Un número de la pizarra: el dato grande, y debajo qué quiere decir.
 *
 * El pie no es adorno. «12 pendientes» no dice nada sin «8 empezadas · 4 sin
 * empezar», que es justo lo que se discute.
 */
export function Kpi({
    titulo,
    valor,
    pie,
    tono,
}: {
    titulo: string;
    valor: string | number;
    pie?: string;
    tono?: Tono;
}) {
    const borde =
        tono === 'ok'
            ? 'border-success/40'
            : tono === 'warn'
              ? 'border-warning/40'
              : tono === 'error'
                ? 'border-error/40'
                : tono === 'info'
                  ? 'border-info/40'
                  : 'border-base-300';

    return (
        <div className={cn('bg-base-100 rounded-xl border p-3', borde)}>
            <div className="text-base-content/60 text-xs font-medium">{titulo}</div>
            <div className="mt-0.5 text-2xl font-bold">{valor}</div>
            {pie && <div className="text-base-content/50 mt-0.5 text-xs">{pie}</div>}
        </div>
    );
}

/** Una barra de avance. Se llena de verde sólo al llegar al 100%. */
export function Barra({ porcentaje }: { porcentaje: number | null }) {
    const valor = Math.min(100, Math.max(0, porcentaje ?? 0));
    const color = valor >= 100 ? 'bg-success' : valor >= 70 ? 'bg-warning' : 'bg-error';

    return (
        <div className="bg-base-300 mt-1.5 h-1.5 w-full overflow-hidden rounded-full">
            <div className={cn('h-full rounded-full transition-all', color)} style={{ width: `${valor}%` }} />
        </div>
    );
}

/** El tono de un porcentaje de cumplimiento, con los cortes de siempre. */
export function tonoDeAvance(valor: number | null, bueno = 90, regular = 70): Tono | undefined {
    if (valor === null) {
        return undefined;
    }
    return valor >= bueno ? 'ok' : valor >= regular ? 'warn' : 'error';
}

/** Un aviso de contexto: explica una regla del módulo, no un error. */
export function Nota({ children }: { children: ReactNode }) {
    return (
        <div className="border-info/30 bg-info/10 text-base-content/80 rounded-lg border px-3 py-2 text-sm">
            {children}
        </div>
    );
}

/** El pie que explica cómo se calcula lo de arriba. */
export function Leyenda({ children }: { children: ReactNode }) {
    return <p className="text-base-content/60 px-4 py-3 text-xs">{children}</p>;
}
