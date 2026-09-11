/**
 * Las formas de las hojas del F-STX-CA-31 y las cuentas que se hacen en la
 * pantalla con ellas. Todos los números llegan del servidor.
 *
 * Lo que hay que conservar son las **definiciones**, que vienen discutidas del
 * formato en Excel y se calculan en `EstadisticaInspecciones`:
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
    obra_id: number;
    obra: string;
    /** Piezas de 2ª transformación liberadas en la semana. */
    liberadas2t: number;
    /** De esas liberadas, cuántas habían sido rechazadas antes. Subconjunto. */
    conRechazo2t: number;
    liberadasPintura: number;
    conRechazoPintura: number;
};

/** La serie del año con la misma fórmula que la hoja 1. `null` = sin liberadas esa semana. */
export type PuntoSerie = { semana: number; t2: number | null; t3: number | null };

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
