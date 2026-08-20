/**
 * Las gráficas de Calidad, sobre Recharts (lo que ya usa el admin).
 *
 * Viven aquí y no dentro de una pantalla porque las comparten el tablero y el
 * reporte semanal: el mismo dato dibujado de dos formas distintas en dos
 * pantallas del mismo módulo es una discrepancia esperando a que alguien la
 * note en una junta.
 *
 * Reglas que se siguen en todas y que explican por qué no son las del tablero
 * anterior:
 *
 *  - **Ningún eje doble.** El Pareto clásico dibuja las barras contra un eje y
 *    la línea acumulada contra otro; la alineación entre las dos escalas es
 *    arbitraria, así que la gráfica inventa una relación que el dato no tiene.
 *    Aquí el acumulado va como etiqueta sobre cada barra: mismo dato, sin la
 *    escala inventada.
 *  - **Las causas de rechazo son barras, no dona.** La pregunta es cuál pesa
 *    más, y comparar ángulos parecidos es justo lo que una dona no deja hacer.
 *  - **Rejilla y ejes en línea continua fina.** Punteado añade ruido y se lee
 *    como «proyección» cuando sólo es una rejilla.
 *  - **Leyenda siempre que haya 2 series o más; ninguna cuando hay una** (el
 *    título ya la nombra), y etiqueta directa sólo en lo que importa, nunca un
 *    número sobre cada punto.
 *  - **Toda gráfica tiene su tabla**, porque un valor no puede depender de
 *    pasar el ratón por encima.
 */

import { useState, type ReactNode } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    LabelList,
    Legend,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { num, pct, usePaleta, type PaletaTablero } from './paleta';

const ALTO = 260;

/** Ejes y rejilla, iguales en todas las gráficas. */
function ejes(p: PaletaTablero) {
    return {
        tick: { fill: p.tintaSuave, fontSize: 11 },
        axisLine: { stroke: p.rejilla },
        tickLine: false as const,
    };
}

function tooltipEstilo(p: PaletaTablero) {
    return {
        contentStyle: {
            background: p.tinta === '#e5e7eb' ? '#111827' : '#ffffff',
            border: `1px solid ${p.rejilla}`,
            borderRadius: 8,
            fontSize: 12,
            color: p.tinta,
        },
        labelStyle: { color: p.tintaSuave },
    };
}

/**
 * Envoltura con su tabla. La gráfica se ve; la tabla está a un clic y contiene
 * exactamente los mismos números.
 */
export function ConTabla({
    columnas,
    filas,
    children,
}: {
    columnas: string[];
    filas: (string | number)[][];
    children: ReactNode;
}) {
    const [tabla, setTabla] = useState(false);

    return (
        <div>
            {tabla ? (
                <div className="overflow-x-auto" style={{ maxHeight: ALTO }}>
                    <table className="table table-xs">
                        <thead>
                            <tr>
                                {columnas.map((c, i) => (
                                    <th key={c} className={i === 0 ? '' : 'text-right'}>
                                        {c}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {filas.map((fila, i) => (
                                <tr key={i}>
                                    {fila.map((celda, j) => (
                                        <td key={j} className={j === 0 ? '' : 'text-right font-mono tabular-nums'}>
                                            {celda}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <div style={{ height: ALTO }}>{children}</div>
            )}

            <button
                type="button"
                onClick={() => setTabla(!tabla)}
                className="text-base-content/50 hover:text-primary mt-1 text-xs underline"
            >
                {tabla ? 'Ver la gráfica' : 'Ver la tabla'}
            </button>
        </div>
    );
}

// ---------------------------------------------------------------------------

/** Evolución del rechazo: dos etapas, dos series. Identidad, no estado. */
export function EvolucionRechazo({ datos }: { datos: { semana: string; fabricacion: number; pintura: number }[] }) {
    const p = usePaleta();
    const e = ejes(p);

    return (
        <ConTabla
            columnas={['Semana', 'Fabricación (2ª)', 'Pintura (3ª)']}
            filas={datos.map((d) => [d.semana, pct(d.fabricacion), pct(d.pintura)])}
        >
            <ResponsiveContainer width="100%" height="100%">
                <LineChart data={datos} margin={{ top: 8, right: 16, left: -12, bottom: 0 }}>
                    <CartesianGrid stroke={p.rejilla} vertical={false} />
                    <XAxis dataKey="semana" {...e} />
                    <YAxis unit="%" {...e} />
                    <Tooltip {...tooltipEstilo(p)} formatter={(v: number) => pct(v)} />
                    <Legend wrapperStyle={{ fontSize: 12, color: p.tintaSuave }} />
                    <Line
                        type="monotone"
                        dataKey="fabricacion"
                        name="Fabricación (2ª)"
                        stroke={p.serie1}
                        strokeWidth={2}
                        dot={{ r: 3 }}
                        activeDot={{ r: 5 }}
                    />
                    <Line
                        type="monotone"
                        dataKey="pintura"
                        name="Pintura (3ª)"
                        stroke={p.serie2}
                        strokeWidth={2}
                        dot={{ r: 3 }}
                        activeDot={{ r: 5 }}
                    />
                </LineChart>
            </ResponsiveContainer>
        </ConTabla>
    );
}

/** Un ranking: barras horizontales, una sola serie, sin leyenda. */
export function BarrasRanking({
    datos,
    unidad = '',
    decimales = 1,
    color,
}: {
    datos: { nombre: string; valor: number; nota?: string }[];
    unidad?: string;
    decimales?: number;
    color?: string;
}) {
    const p = usePaleta();
    const e = ejes(p);
    const tinta = color ?? p.acento;

    return (
        <ConTabla
            columnas={['', unidad === '%' ? 'Rechazo' : 'Valor', 'n']}
            filas={datos.map((d) => [d.nombre, d.valor.toFixed(decimales) + unidad, d.nota ?? ''])}
        >
            <ResponsiveContainer width="100%" height="100%">
                <BarChart data={datos} layout="vertical" margin={{ top: 4, right: 44, left: 8, bottom: 4 }}>
                    <CartesianGrid stroke={p.rejilla} horizontal={false} />
                    <XAxis type="number" unit={unidad} {...e} />
                    <YAxis type="category" dataKey="nombre" width={140} {...e} />
                    <Tooltip
                        {...tooltipEstilo(p)}
                        formatter={(v: number) => v.toFixed(decimales) + unidad}
                        cursor={{ fill: p.rejilla, opacity: 0.35 }}
                    />
                    <Bar dataKey="valor" fill={tinta} radius={[0, 4, 4, 0]} barSize={14} name="Valor">
                        <LabelList
                            dataKey="valor"
                            position="right"
                            formatter={(v: number) => v.toFixed(decimales) + unidad}
                            style={{ fill: p.tintaSuave, fontSize: 11 }}
                        />
                    </Bar>
                </BarChart>
            </ResponsiveContainer>
        </ConTabla>
    );
}

/** Barras verticales de una serie: las tres transformaciones. */
export function BarrasSimples({
    datos,
    unidad = '%',
    decimales = 1,
}: {
    datos: { nombre: string; valor: number; nota?: string }[];
    unidad?: string;
    decimales?: number;
}) {
    const p = usePaleta();
    const e = ejes(p);

    return (
        <ConTabla
            columnas={['', 'Rechazo', 'n']}
            filas={datos.map((d) => [d.nombre, d.valor.toFixed(decimales) + unidad, d.nota ?? ''])}
        >
            <ResponsiveContainer width="100%" height="100%">
                <BarChart data={datos} margin={{ top: 18, right: 16, left: -12, bottom: 0 }}>
                    <CartesianGrid stroke={p.rejilla} vertical={false} />
                    <XAxis dataKey="nombre" {...e} />
                    <YAxis unit={unidad} {...e} />
                    <Tooltip
                        {...tooltipEstilo(p)}
                        formatter={(v: number) => v.toFixed(decimales) + unidad}
                        cursor={{ fill: p.rejilla, opacity: 0.35 }}
                    />
                    <Bar dataKey="valor" fill={p.acento} radius={[4, 4, 0, 0]} barSize={48} name="Rechazo">
                        <LabelList
                            dataKey="valor"
                            position="top"
                            formatter={(v: number) => v.toFixed(decimales) + unidad}
                            style={{ fill: p.tintaSuave, fontSize: 11 }}
                        />
                    </Bar>
                </BarChart>
            </ResponsiveContainer>
        </ConTabla>
    );
}

/**
 * Resultado final por obra: liberadas, rechazadas y pendientes apiladas.
 *
 * Los tres colores son de ESTADO, no de identidad, y por eso llevan leyenda
 * siempre: quien no distinga el verde del ámbar tiene que poder leerlo igual.
 * El hueco de 2 px entre segmentos es el separador; no se dibuja borde.
 */
export function ResultadoPorObra({
    datos,
}: {
    datos: { obra: string; liberadas: number; rechazadas: number; pendientes: number }[];
}) {
    const p = usePaleta();
    const e = ejes(p);

    return (
        <ConTabla
            columnas={['Obra', 'Liberadas', 'Rechazadas', 'Pendientes']}
            filas={datos.map((d) => [d.obra, num(d.liberadas), num(d.rechazadas), num(d.pendientes)])}
        >
            <ResponsiveContainer width="100%" height="100%">
                <BarChart data={datos} layout="vertical" margin={{ top: 4, right: 16, left: 8, bottom: 4 }}>
                    <CartesianGrid stroke={p.rejilla} horizontal={false} />
                    <XAxis type="number" {...e} />
                    <YAxis type="category" dataKey="obra" width={150} {...e} />
                    <Tooltip {...tooltipEstilo(p)} cursor={{ fill: p.rejilla, opacity: 0.35 }} />
                    <Legend wrapperStyle={{ fontSize: 12, color: p.tintaSuave }} />
                    <Bar dataKey="liberadas" name="Liberadas" stackId="a" fill={p.bien} barSize={16} />
                    <Bar dataKey="rechazadas" name="Rechazadas" stackId="a" fill={p.mal} barSize={16} />
                    <Bar dataKey="pendientes" name="Pendientes" stackId="a" fill={p.pendiente} barSize={16} radius={[0, 4, 4, 0]} />
                </BarChart>
            </ResponsiveContainer>
        </ConTabla>
    );
}

/** Tendencia de reproceso: una sola serie, sin leyenda. */
export function TendenciaLinea({ datos }: { datos: { periodo: string; pct: number }[] }) {
    const p = usePaleta();
    const e = ejes(p);

    return (
        <ConTabla columnas={['Periodo', 'Reproceso']} filas={datos.map((d) => [d.periodo, pct(d.pct)])}>
            <ResponsiveContainer width="100%" height="100%">
                <LineChart data={datos} margin={{ top: 8, right: 24, left: -12, bottom: 0 }}>
                    <CartesianGrid stroke={p.rejilla} vertical={false} />
                    <XAxis dataKey="periodo" {...e} />
                    <YAxis unit="%" {...e} />
                    <Tooltip {...tooltipEstilo(p)} formatter={(v: number) => pct(v)} />
                    <Line
                        type="monotone"
                        dataKey="pct"
                        name="Reproceso"
                        stroke={p.acento}
                        strokeWidth={2}
                        dot={{ r: 3 }}
                        activeDot={{ r: 5 }}
                    >
                        {/* Sólo el último punto lleva número: el resto lo dice el eje. */}
                        <LabelList
                            dataKey="pct"
                            position="top"
                            formatter={(v: number, _n: unknown, i: number) =>
                                i === datos.length - 1 ? pct(v) : ''
                            }
                            style={{ fill: p.tintaSuave, fontSize: 11 }}
                        />
                    </Line>
                </LineChart>
            </ResponsiveContainer>
        </ConTabla>
    );
}

/**
 * Pareto: barras ordenadas de mayor a menor, con el acumulado como etiqueta.
 *
 * El acumulado NO va como segunda línea contra un segundo eje. Ese es el error
 * clásico del Pareto: dos escalas alineadas a ojo sobre el mismo dibujo. Va
 * escrito encima de cada barra, que es donde se lee.
 */
export function Pareto({ datos }: { datos: { causa: string; n: number }[] }) {
    const p = usePaleta();
    const e = ejes(p);

    const total = datos.reduce((a, d) => a + d.n, 0);
    let corrido = 0;
    const conAcumulado = datos.map((d) => {
        corrido += d.n;
        return { ...d, acumulado: total > 0 ? (corrido / total) * 100 : 0 };
    });

    return (
        <ConTabla
            columnas={['Causa', 'Defectos', 'Acumulado']}
            filas={conAcumulado.map((d) => [d.causa, num(d.n), pct(d.acumulado, 0)])}
        >
            <ResponsiveContainer width="100%" height="100%">
                <BarChart data={conAcumulado} margin={{ top: 24, right: 16, left: -12, bottom: 0 }}>
                    <CartesianGrid stroke={p.rejilla} vertical={false} />
                    <XAxis dataKey="causa" {...e} interval={0} angle={-12} textAnchor="end" height={52} />
                    <YAxis {...e} />
                    <Tooltip
                        {...tooltipEstilo(p)}
                        cursor={{ fill: p.rejilla, opacity: 0.35 }}
                        formatter={(v: number, nombre: string) =>
                            nombre === 'Acumulado' ? pct(v, 0) : num(v)
                        }
                    />
                    <Bar dataKey="n" name="Defectos" fill={p.acento} radius={[4, 4, 0, 0]} barSize={40}>
                        <LabelList
                            dataKey="acumulado"
                            position="top"
                            formatter={(v: number) => pct(v, 0)}
                            style={{ fill: p.tintaSuave, fontSize: 11 }}
                        />
                    </Bar>
                </BarChart>
            </ResponsiveContainer>
        </ConTabla>
    );
}

/** Causas de rechazo. Barras horizontales; la primera se destaca. */
export function CausasRechazo({ datos }: { datos: { causa: string; n: number }[] }) {
    const p = usePaleta();
    const e = ejes(p);

    return (
        <ConTabla columnas={['Causa', 'Defectos']} filas={datos.map((d) => [d.causa, num(d.n)])}>
            <ResponsiveContainer width="100%" height="100%">
                <BarChart data={datos} layout="vertical" margin={{ top: 4, right: 36, left: 8, bottom: 4 }}>
                    <CartesianGrid stroke={p.rejilla} horizontal={false} />
                    <XAxis type="number" {...e} />
                    <YAxis type="category" dataKey="causa" width={130} {...e} />
                    <Tooltip {...tooltipEstilo(p)} cursor={{ fill: p.rejilla, opacity: 0.35 }} />
                    <Bar dataKey="n" name="Defectos" radius={[0, 4, 4, 0]} barSize={16}>
                        {/* Emphasis: la causa que más pesa en el acento, el resto atenuado. */}
                        {datos.map((_, i) => (
                            <Cell key={i} fill={p.acento} fillOpacity={i === 0 ? 1 : 0.45} />
                        ))}
                        <LabelList
                            dataKey="n"
                            position="right"
                            style={{ fill: p.tintaSuave, fontSize: 11 }}
                        />
                    </Bar>
                </BarChart>
            </ResponsiveContainer>
        </ConTabla>
    );
}

/**
 * Un total y la parte de ese total que cumple una condición, lado a lado.
 *
 * La barra grande son las piezas liberadas; la pequeña, las que antes de
 * liberarse habían sido rechazadas al menos una vez. **Una está contenida en la
 * otra.** Dibujarlas como «a la primera» contra «con rechazo previo» era
 * correcto de número y malo de lectura —parecía que lo rechazado superaba a lo
 * liberado—, así que se pinta el total y su parte, como en el formato en Excel.
 *
 * No lleva línea de porcentaje uniendo las obras: sugiere una tendencia entre
 * proyectos que no existe, porque dos obras no son dos puntos de una serie.
 */
export function BarrasTotalYParte({
    datos,
    nombreTotal,
    nombreParte,
}: {
    datos: { nombre: string; total: number; parte: number }[];
    nombreTotal: string;
    nombreParte: string;
}) {
    const p = usePaleta();
    const e = ejes(p);

    return (
        <ConTabla
            columnas={['', nombreTotal, nombreParte, '%']}
            filas={datos.map((d) => [
                d.nombre,
                num(d.total),
                num(d.parte),
                pct(d.total > 0 ? (d.parte / d.total) * 100 : null, 0),
            ])}
        >
            <ResponsiveContainer width="100%" height="100%">
                <BarChart data={datos} margin={{ top: 8, right: 16, left: -12, bottom: 0 }}>
                    <CartesianGrid stroke={p.rejilla} vertical={false} />
                    <XAxis dataKey="nombre" {...e} interval={0} height={44} angle={-12} textAnchor="end" />
                    <YAxis {...e} />
                    <Tooltip {...tooltipEstilo(p)} cursor={{ fill: p.rejilla, opacity: 0.35 }} />
                    <Legend wrapperStyle={{ fontSize: 12, color: p.tintaSuave }} />
                    <Bar dataKey="total" name={nombreTotal} fill={p.serie1} radius={[4, 4, 0, 0]} barSize={22} />
                    <Bar dataKey="parte" name={nombreParte} fill={p.serie2} radius={[4, 4, 0, 0]} barSize={22} />
                </BarChart>
            </ResponsiveContainer>
        </ConTabla>
    );
}

/**
 * Carta-p de control estadístico.
 *
 * Cuatro series: la proporción observada, los dos límites de control y la línea
 * central. Los límites **no son rectos** y eso es correcto: el tamaño de
 * subgrupo cambia cada semana, y una semana de 12 piezas tolera más variación
 * que una de 90. Dibujarlos rectos es lo que hace que una semana floja parezca
 * fuera de control.
 *
 * Un punto por encima del UCL indica causa asignable —algo pasó—, no la
 * variación normal del proceso. Por eso los límites van punteados y la serie
 * observada continua: lo que se mira es el dato, no la referencia.
 */
export function CartaP({
    datos,
    pbar,
}: {
    datos: { semana: string; p: number; ucl: number; lcl: number; n: number }[];
    pbar: number;
}) {
    const p = usePaleta();
    const e = ejes(p);
    const conCentral = datos.map((d) => ({ ...d, pbar }));

    return (
        <ConTabla
            columnas={['Semana', 'Proporción', 'LCL', 'UCL', 'n']}
            filas={datos.map((d) => [d.semana, pct(d.p), pct(d.lcl), pct(d.ucl), num(d.n)])}
        >
            <ResponsiveContainer width="100%" height="100%">
                <LineChart data={conCentral} margin={{ top: 8, right: 24, left: -12, bottom: 0 }}>
                    <CartesianGrid stroke={p.rejilla} vertical={false} />
                    <XAxis dataKey="semana" {...e} />
                    <YAxis unit="%" {...e} />
                    <Tooltip {...tooltipEstilo(p)} />
                    <Legend wrapperStyle={{ fontSize: 12, color: p.tintaSuave }} />
                    <Line
                        type="monotone"
                        dataKey="ucl"
                        name="UCL"
                        unit="%"
                        stroke={p.mal}
                        strokeDasharray="5 4"
                        dot={false}
                        strokeWidth={1.5}
                    />
                    <Line
                        type="monotone"
                        dataKey="lcl"
                        name="LCL"
                        unit="%"
                        stroke={p.bien}
                        strokeDasharray="5 4"
                        dot={false}
                        strokeWidth={1.5}
                    />
                    <Line
                        type="monotone"
                        dataKey="pbar"
                        name="p̄"
                        unit="%"
                        stroke={p.tintaSuave}
                        strokeDasharray="2 3"
                        dot={false}
                        strokeWidth={1.5}
                    />
                    <Line
                        type="monotone"
                        dataKey="p"
                        name="Proporción"
                        unit="%"
                        stroke={p.acento}
                        strokeWidth={2}
                        dot={{ r: 3, fill: p.acento }}
                    />
                </LineChart>
            </ResponsiveContainer>
        </ConTabla>
    );
}

/**
 * Histograma del margen sobre el mínimo exigido.
 *
 * El cero es el mínimo del proyecto, y las barras a su izquierda —piezas por
 * debajo de norma— van en rojo. Es lo único que el color marca aquí: no es una
 * escala, es un umbral, y un umbral sí se puede leer de un vistazo.
 */
export function HistogramaMargen({ bins }: { bins: { desde: number; n: number }[] }) {
    const p = usePaleta();
    const e = ejes(p);
    const datos = bins.map((b) => ({
        etiqueta: `${b.desde >= 0 ? '+' : ''}${Math.round(b.desde)}%`,
        n: b.n,
        bajo: b.desde < 0,
    }));

    return (
        <ConTabla
            columnas={['Margen', 'Piezas']}
            filas={datos.map((d) => [d.etiqueta, num(d.n)])}
        >
            <ResponsiveContainer width="100%" height="100%">
                <BarChart data={datos} margin={{ top: 18, right: 16, left: -12, bottom: 0 }}>
                    <CartesianGrid stroke={p.rejilla} vertical={false} />
                    <XAxis dataKey="etiqueta" {...e} />
                    <YAxis {...e} allowDecimals={false} />
                    <Tooltip {...tooltipEstilo(p)} cursor={{ fill: p.rejilla, opacity: 0.35 }} />
                    <Bar dataKey="n" name="Piezas" unit=" pieza(s)" radius={[4, 4, 0, 0]}>
                        {datos.map((d) => (
                            <Cell key={d.etiqueta} fill={d.bajo ? p.mal : p.serie2} />
                        ))}
                        <LabelList
                            dataKey="n"
                            position="top"
                            style={{ fill: p.tintaSuave, fontSize: 11 }}
                        />
                    </Bar>
                </BarChart>
            </ResponsiveContainer>
        </ConTabla>
    );
}
