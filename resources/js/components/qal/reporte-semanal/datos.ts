/**
 * Los números de las hojas que todavía no se pueden calcular. Son FALSOS, a
 * propósito, y cada hoja que los usa lo dice en su encabezado.
 *
 * Cuatro de las seis hojas del F-STX-CA-31 se alimentan de tablas que no
 * existen en `qal_`:
 *
 *  - Hojas 0, 1 y 3 salen de la **inspección visual** (`qal_inspecciones` y sus
 *    hijas). La captura del inspector ya está construida en el front, pero
 *    todavía no guarda.
 *  - Hojas 4 y 5 salen del **montaje y las incidencias en obra**, que en la
 *    aplicación anterior vivían en dos tablas propias (`obra_montaje`,
 *    `obra_incidencias`) y aquí no tienen equivalente.
 *
 * Lo que NO es relleno y hay que conservar cuando esto se sustituya por props
 * son las **definiciones**, que vienen discutidas del formato en Excel:
 *
 *  - El porcentaje de la hoja 1 es **piezas liberadas que traían rechazo previo
 *    ÷ piezas liberadas**. No es «rechazadas ÷ liberadas de la misma semana»:
 *    eso daba porcentajes imposibles —140 %, 240 %— porque son dos conjuntos
 *    distintos de piezas. Cada pieza cuenta una vez, en la semana en que se
 *    liberó, arrastrando toda su historia; los rechazos pueden ser de semanas
 *    anteriores.
 *  - Es un FPY al revés: 100 menos ese número son las piezas que salieron bien
 *    a la primera. Por eso nunca puede pasar de 100.
 *  - Las incidencias en obra se calculan siempre sobre **piezas montadas**, y
 *    taller y montaje se publican por separado: una incidencia de taller es un
 *    defecto que se escapó de planta y una de montaje es un problema aparecido
 *    en sitio. Juntarlas en un solo porcentaje esconde cuál manda.
 */

/** Una obra en la hoja de inspección visual de la semana. */
export type FilaVisual = {
    obra: string;
    /** Piezas de 2ª transformación liberadas en la semana. */
    liberadas2t: number;
    /** De esas liberadas, cuántas habían sido rechazadas antes. Subconjunto. */
    conRechazo2t: number;
    liberadasPintura: number;
    conRechazoPintura: number;
};

export const INSPECCION_VISUAL: FilaVisual[] = [
    { obra: 'AMPLIACION T4 CANCUN', liberadas2t: 84, conRechazo2t: 13, liberadasPintura: 61, conRechazoPintura: 4 },
    { obra: 'CANCUN PARKS II NAVE A', liberadas2t: 52, conRechazo2t: 11, liberadasPintura: 38, conRechazoPintura: 5 },
    { obra: 'TRES GUERRAS VILLA MAGNA', liberadas2t: 31, conRechazo2t: 9, liberadasPintura: 12, conRechazoPintura: 1 },
    { obra: 'TOTEM PRIME CENTER', liberadas2t: 18, conRechazo2t: 2, liberadasPintura: 0, conRechazoPintura: 0 },
];

/** Kilos de estructura principal liberados en la semana. */
export const KG_LIBERADOS = 412_500;

/** Piezas con incidencia registrada en taller y obra durante la semana. */
export const PIEZAS_CON_INCIDENCIA_SEMANA = 7;

/** La serie del año con la misma fórmula que la hoja 1. `null` = sin base. */
export type PuntoSerie = { semana: number; t2: number | null; t3: number | null };

export const SERIE_ANIO: PuntoSerie[] = [
    { semana: 26, t2: 24, t3: 7 },
    { semana: 27, t2: 22, t3: 9 },
    { semana: 28, t2: 26, t3: 6 },
    { semana: 29, t2: 19, t3: 8 },
    { semana: 30, t2: 21, t3: 11 },
    { semana: 31, t2: 23, t3: 7 },
    { semana: 32, t2: 20, t3: 8 },
    { semana: 33, t2: 18, t3: 6 },
];

/**
 * Una obra en las hojas de montaje.
 *
 * `a` y `b` son las dos áreas que el formato publica por separado: en la hoja 4
 * son taller y montaje; en la 5, taller de pintura y pintura en obra. Van sin
 * nombre aquí porque la cuenta es idéntica y sólo cambian las etiquetas.
 */
export type FilaMontaje = {
    obra: string;
    /** Piezas montadas hasta la semana de corte, acumuladas. */
    montadas: number;
    /** Piezas del proyecto completo. `null` = no está en la ficha de la obra. */
    totales: number | null;
    a: number;
    b: number;
    /** Las de esta semana, subconjunto del acumulado. */
    aSemana: number;
    bSemana: number;
};

export const MONTAJE: FilaMontaje[] = [
    { obra: 'AMPLIACION T4 CANCUN', montadas: 1_842, totales: 3_100, a: 21, b: 14, aSemana: 2, bSemana: 1 },
    { obra: 'CANCUN PARKS II NAVE A', montadas: 964, totales: 1_480, a: 12, b: 9, aSemana: 1, bSemana: 0 },
    { obra: 'TRES GUERRAS VILLA MAGNA', montadas: 318, totales: null, a: 4, b: 6, aSemana: 0, bSemana: 2 },
];

export const PINTURA: FilaMontaje[] = [
    { obra: 'AMPLIACION T4 CANCUN', montadas: 1_842, totales: 3_100, a: 9, b: 17, aSemana: 1, bSemana: 3 },
    { obra: 'CANCUN PARKS II NAVE A', montadas: 964, totales: 1_480, a: 5, b: 8, aSemana: 0, bSemana: 1 },
    { obra: 'TRES GUERRAS VILLA MAGNA', montadas: 318, totales: null, a: 2, b: 3, aSemana: 0, bSemana: 0 },
];

/** Piezas montadas durante la semana de corte, en todas las obras. */
export const MONTADAS_SEMANA = 96;

/** Suma las dos áreas de una tabla de montaje. */
export function totalMontaje(filas: FilaMontaje[]) {
    return filas.reduce(
        (acumulado, fila) => ({
            montadas: acumulado.montadas + fila.montadas,
            a: acumulado.a + fila.a,
            b: acumulado.b + fila.b,
            aSemana: acumulado.aSemana + fila.aSemana,
            bSemana: acumulado.bSemana + fila.bSemana,
        }),
        { montadas: 0, a: 0, b: 0, aSemana: 0, bSemana: 0 },
    );
}

/** Suma la hoja de inspección visual. */
export function totalVisual(filas: FilaVisual[]) {
    return filas.reduce(
        (acumulado, fila) => ({
            liberadas2t: acumulado.liberadas2t + fila.liberadas2t,
            conRechazo2t: acumulado.conRechazo2t + fila.conRechazo2t,
            liberadasPintura: acumulado.liberadasPintura + fila.liberadasPintura,
            conRechazoPintura: acumulado.conRechazoPintura + fila.conRechazoPintura,
        }),
        { liberadas2t: 0, conRechazo2t: 0, liberadasPintura: 0, conRechazoPintura: 0 },
    );
}
