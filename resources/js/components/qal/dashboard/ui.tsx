import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import type { Kpi } from './tipos';

/**
 * Las piezas del tablero: tarjeta de número, panel, tarjeta de gráfica y tabs.
 *
 * Dos reglas que vienen de cómo se leen los tableros y que aquí se respetan a
 * propósito:
 *
 *  - **El número va en tinta normal, no en el color de su serie.** El color
 *    identifica el dato dentro de una gráfica; en un número suelto sólo grita.
 *    El estado se marca con un punto de color al lado, que sí se puede leer
 *    junto a la etiqueta.
 *  - **El valor grande no lleva `tabular-nums`.** Los dígitos de ancho fijo
 *    sirven para alinear columnas; en un número de 28 px hacen que «1,284» se
 *    vea suelto. Van en las tablas y en los ejes, no aquí.
 */

const PUNTO = {
    neutro: 'bg-base-content/30',
    bueno: 'bg-success',
    malo: 'bg-error',
    pendiente: 'bg-warning',
};

export function TarjetaKpi({ kpi, onClick }: { kpi: Kpi; onClick?: () => void }) {
    const tono = kpi.tono ?? 'neutro';
    const delta = kpi.delta;

    // Por defecto bajar es bueno (es un rechazo). Menos de 0.05 no es un
    // movimiento, es ruido: se pinta plano y sin flecha de color.
    const mejorSiBaja = kpi.mejorSiBaja !== false;
    const plano = delta == null || !Number.isFinite(delta) || Math.abs(delta) < 0.05;
    const bueno = delta != null && delta > 0 !== mejorSiBaja;

    return (
        <div
            onClick={onClick}
            className={cn(
                'rounded-box border border-base-300 bg-base-100 p-4',
                onClick && 'hover:border-primary/40 cursor-pointer transition',
            )}
        >
            <div className="flex items-center gap-2">
                <span className={cn('size-2 shrink-0 rounded-full', PUNTO[tono])} />
                <span className="text-base-content/70 text-xs font-medium">{kpi.etiqueta}</span>
                {onClick && <span className="text-base-content/40 ml-auto text-xs">↗</span>}
            </div>

            <div className="mt-1.5 text-3xl leading-none font-semibold">{kpi.valor}</div>

            {!plano && delta != null ? (
                <div className={cn('mt-2 text-xs font-medium', bueno ? 'text-success' : 'text-error')}>
                    {delta > 0 ? '▲' : '▼'} {delta > 0 ? '+' : ''}
                    {delta.toFixed(1)}
                    {kpi.sufijo ?? ''}
                    <span className="text-base-content/50 font-normal"> vs periodo previo</span>
                </div>
            ) : (
                kpi.nota && <div className="text-base-content/50 mt-2 text-xs">{kpi.nota}</div>
            )}

            {!plano && kpi.nota && <div className="text-base-content/50 mt-1 text-xs">{kpi.nota}</div>}
        </div>
    );
}

export function FilaKpis({ children }: { children: ReactNode }) {
    return <div className="grid grid-cols-[repeat(auto-fit,minmax(190px,1fr))] gap-3">{children}</div>;
}

export function Panel({
    titulo,
    apunte,
    children,
    acciones,
}: {
    titulo: string;
    apunte?: string;
    acciones?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="rounded-box border border-base-300 bg-base-100 p-4">
            <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                <h2 className="text-lg font-semibold">
                    {titulo}
                    {apunte && <span className="text-base-content/50 ml-2 text-sm font-normal">{apunte}</span>}
                </h2>
                {acciones}
            </div>
            {children}
        </section>
    );
}

/**
 * Tarjeta de una gráfica.
 *
 * `controles` es el desplegable que acota lo que se está viendo y va en el
 * encabezado, pegado al título: quien cambia «Pareto de soldadura» por «Pareto
 * de pintura» está cambiando de pregunta, no filtrando la pantalla.
 *
 * `pie` es la nota que explica el denominador. No es decorativa: sin ella, un
 * porcentaje no dice sobre qué está calculado.
 */
export function TarjetaGrafica({
    titulo,
    apunte,
    controles,
    pie,
    ancha,
    children,
}: {
    titulo: string;
    apunte?: string;
    controles?: ReactNode;
    pie?: ReactNode;
    ancha?: boolean;
    children: ReactNode;
}) {
    return (
        <div className={cn('rounded-box border border-base-300 bg-base-100 p-4', ancha && 'lg:col-span-2')}>
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-sm font-semibold">
                    {titulo}
                    {apunte && <span className="text-base-content/50 ml-2 text-xs font-normal">{apunte}</span>}
                </h3>
                {controles && <div className="flex flex-wrap items-center gap-2">{controles}</div>}
            </div>
            {children}
            {pie && <div className="text-base-content/50 mt-2 text-xs">{pie}</div>}
        </div>
    );
}

/** Desplegable compacto para los controles de una tarjeta. */
export function SelectorTarjeta<T extends string>({
    value,
    onChange,
    opciones,
    etiqueta,
}: {
    value: T;
    onChange: (valor: T) => void;
    opciones: { valor: T; texto: string }[];
    etiqueta: string;
}) {
    return (
        <select
            aria-label={etiqueta}
            className="select select-bordered select-xs w-auto"
            value={value}
            onChange={(e) => onChange(e.target.value as T)}
        >
            {opciones.map((o) => (
                <option key={o.valor} value={o.valor}>
                    {o.texto}
                </option>
            ))}
        </select>
    );
}

export type Tab<T extends string> = { valor: T; titulo: string; nota: string };

export function Tabs<T extends string>({
    value,
    onChange,
    tabs,
}: {
    value: T;
    onChange: (valor: T) => void;
    tabs: Tab<T>[];
}) {
    return (
        <div className="flex flex-wrap gap-2">
            {tabs.map((tab) => {
                const activo = tab.valor === value;
                return (
                    <button
                        key={tab.valor}
                        type="button"
                        onClick={() => onChange(tab.valor)}
                        aria-pressed={activo}
                        className={cn(
                            'rounded-box border px-4 py-2.5 text-left transition',
                            activo
                                ? 'border-primary bg-primary text-primary-content'
                                : 'border-base-300 bg-base-100 hover:border-primary/40',
                        )}
                    >
                        <div className="text-sm font-semibold">{tab.titulo}</div>
                        <div className={cn('text-xs', activo ? 'text-primary-content/70' : 'text-base-content/50')}>
                            {tab.nota}
                        </div>
                    </button>
                );
            })}
        </div>
    );
}

export function Rejilla2({ children }: { children: ReactNode }) {
    return <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">{children}</div>;
}

/** Aviso de lo que el tablero decide NO publicar, y por qué. */
export function NotaCallada({ children }: { children: ReactNode }) {
    return (
        <div className="rounded-box border border-base-300 bg-base-200/50 px-3 py-2 text-xs text-base-content/60">
            {children}
        </div>
    );
}

// ---------------------------------------------------------------------------
// Piezas de las pestañas Estadística y Diagnóstico. Ahí la respuesta casi nunca
// es una gráfica: son tablas con su veredicto al lado, y el veredicto necesita
// poder leerse sin depender del color.
// ---------------------------------------------------------------------------

export type Tono = 'ok' | 'alerta' | 'malo' | 'neutro';

const ETIQUETA: Record<Tono, string> = {
    ok: 'border-success/30 bg-success/15 text-success',
    alerta: 'border-warning/30 bg-warning/15 text-warning',
    malo: 'border-error/30 bg-error/15 text-error',
    neutro: 'border-base-300 bg-base-200 text-base-content/60',
};

export function Etiqueta({ texto, tono = 'neutro' }: { texto: string; tono?: Tono }) {
    return (
        <span className={cn('inline-block rounded-full border px-2 py-0.5 text-xs font-semibold', ETIQUETA[tono])}>
            {texto}
        </span>
    );
}

/**
 * Tabla del tablero. La primera columna es el concepto y va a la izquierda; el
 * resto son números y van a la derecha en cifra tabular, que es lo que deja
 * comparar una columna de un vistazo.
 *
 * `atenuadas` marca las filas que se publican pero no se sostienen —cobertura
 * por debajo del 30 %, categorías sin muestra—: siguen estando, en gris, porque
 * esconderlas haría creer que el dato no existe.
 */
export function Tabla({
    columnas,
    filas,
    atenuadas,
    vacia,
}: {
    columnas: string[];
    filas: ReactNode[][];
    atenuadas?: boolean[];
    vacia?: string;
}) {
    return (
        <div className="overflow-x-auto">
            <table className="table-sm table w-full">
                <thead>
                    <tr className="border-base-300">
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
                            <tr key={i} className={cn('border-base-300', atenuadas?.[i] && 'opacity-60')}>
                                {fila.map((celda, j) => (
                                    <td key={j} className={cn(j === 0 ? '' : 'text-right tabular-nums')}>
                                        {celda}
                                    </td>
                                ))}
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
        </div>
    );
}

/** Barra de cobertura: cuánto del universo tiene ese dato capturado. */
export function BarraCobertura({ pct: porcentaje }: { pct: number }) {
    const flojo = porcentaje < 30;

    return (
        <span className="inline-flex items-center gap-2">
            <span className="bg-base-300 inline-block h-1.5 w-16 overflow-hidden rounded-full">
                <span
                    className={cn('block h-full rounded-full', flojo ? 'bg-error' : 'bg-primary')}
                    style={{ width: `${Math.min(100, porcentaje)}%` }}
                />
            </span>
            <span className="text-base-content/60 text-xs tabular-nums">{Math.round(porcentaje)}%</span>
        </span>
    );
}

/**
 * Barra de distribución de un campo del formulario: OK, con defecto, no aplica
 * y sin contestar. El blanco tiene su propio segmento a propósito — **una
 * casilla en blanco no es un OK**, y meterla en el mismo tramo lo escondería.
 */
export function BarraCampo({
    ok,
    defecto,
    noAplica,
    vacio,
    total,
}: {
    ok: number;
    defecto: number;
    noAplica: number;
    vacio: number;
    total: number;
}) {
    const ancho = (v: number) => (total > 0 ? `${(v / total) * 100}%` : '0%');

    return (
        <span
            className="bg-base-200 inline-flex h-2 w-24 overflow-hidden rounded-full align-middle"
            title={`${ok} OK · ${defecto} con defecto · ${noAplica} no aplica · ${vacio} sin contestar`}
        >
            <span className="bg-success block h-full" style={{ width: ancho(ok) }} />
            <span className="bg-error block h-full" style={{ width: ancho(defecto) }} />
            <span className="bg-base-content/25 block h-full" style={{ width: ancho(noAplica) }} />
            <span className="bg-base-300 block h-full" style={{ width: ancho(vacio) }} />
        </span>
    );
}
