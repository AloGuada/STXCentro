import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Las piezas del F-STX-CA-31.
 *
 * El reporte no es el tablero: es un documento que se hojea, se imprime y se
 * manda fuera. De ahí las tres reglas que se respetan en todas las hojas:
 *
 *  - **Cada hoja dice para qué está.** Número grande, título y la pregunta que
 *    responde. Un reporte de dirección se hojea; si una sección no declara qué
 *    contesta, se lee entera o no se lee ninguna.
 *  - **Todo porcentaje lleva su denominador debajo.** Las notas al pie de cada
 *    bloque no son adorno: son la diferencia entre «26 % de rechazo» y «26 % de
 *    lo liberado esta semana costó dos vueltas».
 *  - **Los mismos tres umbrales en todo el documento.** Verde, ámbar y rojo se
 *    calculan siempre igual; si cambiaran de hoja en hoja, el color dejaría de
 *    poder leerse de un vistazo.
 */

export type Estado = 'ok' | 'med' | 'mal' | 'neu';

/**
 * Incidencias de inspección: normal por debajo del 15 %, hay que actuar a
 * partir del 25 %. Son los umbrales con los que calidad viene leyendo la serie.
 */
export function estadoIncidencia(pct: number | null): Estado {
    if (pct === null || !Number.isFinite(pct)) {
        return 'neu';
    }
    return pct < 15 ? 'ok' : pct < 25 ? 'med' : 'mal';
}

/**
 * Incidencias en obra: otro orden de magnitud y por eso otra escala. Una pieza
 * con problema de cada cincuenta montadas ya es mucho.
 */
export function estadoMontaje(pct: number | null): Estado {
    if (pct === null || !Number.isFinite(pct)) {
        return 'neu';
    }
    return pct < 2 ? 'ok' : pct < 5 ? 'med' : 'mal';
}

/** El porcentaje, o `null` cuando no hay denominador. Nunca un cero de relleno. */
export function porcentaje(parte: number, total: number): number | null {
    return total > 0 ? (parte / total) * 100 : null;
}

const PASTILLA: Record<Estado, string> = {
    ok: 'bg-success/15 text-success border-success/30',
    med: 'bg-warning/15 text-warning border-warning/30',
    mal: 'bg-error/15 text-error border-error/30',
    neu: 'bg-base-200 text-base-content/60 border-base-300',
};

const PUNTO: Record<Estado, string> = {
    ok: 'bg-success',
    med: 'bg-warning',
    mal: 'bg-error',
    neu: 'bg-base-content/30',
};

export function Pastilla({ texto, estado = 'neu' }: { texto: string; estado?: Estado }) {
    return (
        <span
            className={cn(
                'inline-block rounded-full border px-2 py-0.5 text-xs font-semibold tabular-nums',
                PASTILLA[estado],
            )}
        >
            {texto}
        </span>
    );
}

/**
 * Una hoja del reporte.
 *
 * `origen` es lo único que no viene del formato en papel y no se puede quitar:
 * mientras la mitad de las hojas se calcule de verdad y la otra mitad sea
 * maqueta, cada una tiene que decir de cuál se trata. Un documento que se manda
 * a dirección no puede dejar esa duda al lector.
 */
export function Hoja({
    numero,
    titulo,
    pregunta,
    meta,
    submeta,
    origen,
    children,
}: {
    numero?: number;
    titulo: string;
    pregunta: string;
    meta?: string;
    submeta?: string;
    origen: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="rounded-box border-base-300 bg-base-100 overflow-hidden border">
            <header className="border-base-300 bg-base-200/40 flex flex-wrap items-start gap-3 border-b px-4 py-3">
                {numero !== undefined && (
                    <div className="bg-primary text-primary-content grid size-9 shrink-0 place-items-center rounded-lg text-lg font-bold">
                        {numero}
                    </div>
                )}
                <div className="min-w-[200px] flex-1">
                    <h2 className="text-base font-semibold tracking-tight uppercase">{titulo}</h2>
                    <p className="text-base-content/60 text-xs">{pregunta}</p>
                </div>
                <div className="text-right text-xs">
                    {meta && <div className="font-semibold">{meta}</div>}
                    {submeta && <div className="text-base-content/50">{submeta}</div>}
                    <div className="mt-1">{origen}</div>
                </div>
            </header>
            <div className="space-y-4 p-4">{children}</div>
        </section>
    );
}

/** Sello de procedencia de la hoja. Va en el encabezado, no al final. */
export function Origen({ real, detalle }: { real: boolean; detalle: string }) {
    return (
        <span
            className={cn(
                'inline-block rounded-full border px-2 py-0.5 text-[11px] font-semibold',
                real ? PASTILLA.ok : PASTILLA.med,
            )}
            title={detalle}
        >
            {real ? 'Datos reales' : 'Maqueta'}
        </span>
    );
}

export function FilaKpis({ children }: { children: ReactNode }) {
    return <div className="grid grid-cols-[repeat(auto-fit,minmax(170px,1fr))] gap-3">{children}</div>;
}

/**
 * Un número del encabezado de la hoja.
 *
 * El valor va en tinta normal y el estado en el punto de color de al lado: un
 * número en rojo grita antes de que se haya leído qué mide.
 */
export function Kpi({
    valor,
    etiqueta,
    sub,
    estado = 'neu',
}: {
    valor: string | number;
    etiqueta: string;
    sub?: string;
    estado?: Estado;
}) {
    return (
        <div className="rounded-box border-base-300 border p-3">
            <div className="flex items-center gap-2">
                <span className={cn('size-2 shrink-0 rounded-full', PUNTO[estado])} />
                <span className="text-base-content/70 text-xs font-medium">{etiqueta}</span>
            </div>
            <div className="mt-1.5 text-2xl leading-none font-semibold">{valor}</div>
            {sub && <div className="text-base-content/50 mt-1.5 text-xs">{sub}</div>}
        </div>
    );
}

export function Bloque({ titulo, apunte, children }: { titulo: string; apunte?: string; children: ReactNode }) {
    return (
        <div>
            <h3 className="mb-2 text-sm font-semibold">
                {titulo}
                {apunte && <span className="text-base-content/50 ml-2 text-xs font-normal">{apunte}</span>}
            </h3>
            {children}
        </div>
    );
}

export function Rejilla2({ children }: { children: ReactNode }) {
    return <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">{children}</div>;
}

/**
 * Tabla del reporte. La primera columna es el concepto y se alinea a la
 * izquierda; el resto son números y van a la derecha, en cifra tabular, que es
 * lo que permite comparar una columna de un vistazo.
 */
export function Tabla({
    columnas,
    filas,
    pie,
    vacia,
}: {
    columnas: string[];
    filas: ReactNode[][];
    pie?: ReactNode[][];
    vacia?: string;
}) {
    return (
        <div className="overflow-x-auto">
            <table className="table-sm table w-full">
                <thead>
                    <tr className="border-base-300">
                        {/* La clave es el índice y no el rótulo a propósito: una
                            tabla puede repetir cabecera —«Con rechazo previo»
                            sale dos veces, una por etapa— y el rótulo dejaría de
                            ser único. */}
                        {columnas.map((c, i) => (
                            <th key={i} className={cn('whitespace-nowrap', i > 0 && 'text-right')}>
                                {c}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {filas.length === 0 ? (
                        <tr>
                            <td colSpan={columnas.length} className="text-base-content/50 text-center text-sm">
                                {vacia ?? 'Sin datos.'}
                            </td>
                        </tr>
                    ) : (
                        filas.map((fila, i) => (
                            <tr key={i} className="border-base-300">
                                {fila.map((celda, j) => (
                                    <td
                                        key={j}
                                        className={cn(
                                            'whitespace-nowrap',
                                            j === 0 ? 'font-medium' : 'text-right tabular-nums',
                                        )}
                                    >
                                        {celda}
                                    </td>
                                ))}
                            </tr>
                        ))
                    )}
                </tbody>
                {pie && pie.length > 0 && (
                    <tfoot>
                        {pie.map((fila, i) => (
                            <tr key={i} className="border-base-300 bg-base-200/50 font-semibold">
                                {fila.map((celda, j) => (
                                    <td
                                        key={j}
                                        className={cn('whitespace-nowrap', j > 0 && 'text-right tabular-nums')}
                                    >
                                        {celda}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tfoot>
                )}
            </table>
        </div>
    );
}

/** Las dos columnas etiqueta/valor de los recuadros del formato oficial. */
export function ListaValores({
    filas,
}: {
    filas: { etiqueta: string; valor: ReactNode; destacada?: boolean }[];
}) {
    return (
        <div className="border-base-300 divide-base-300 divide-y rounded-lg border">
            {filas.map((f) => (
                <div
                    key={f.etiqueta}
                    className={cn(
                        'flex items-center justify-between gap-4 px-3 py-1.5 text-sm',
                        f.destacada && 'bg-base-200/60 font-semibold',
                    )}
                >
                    <span className={cn(!f.destacada && 'text-base-content/70')}>{f.etiqueta}</span>
                    <span className="tabular-nums">{f.valor}</span>
                </div>
            ))}
        </div>
    );
}

/** La nota que explica el denominador. Sin ella un porcentaje no dice nada. */
export function Nota({ children, aviso }: { children: ReactNode; aviso?: boolean }) {
    return (
        <p
            className={cn(
                'text-xs leading-relaxed',
                aviso ? 'border-warning text-base-content/80 border-l-2 pl-2' : 'text-base-content/50',
            )}
        >
            {children}
        </p>
    );
}

/** Lo que falta por capturar, dicho en la propia hoja en vez de en una nota. */
export function SinCaptura({ children }: { children: ReactNode }) {
    return (
        <div className="rounded-box border-base-300 bg-base-200/40 text-base-content/60 border border-dashed px-3 py-4 text-sm">
            {children}
        </div>
    );
}
