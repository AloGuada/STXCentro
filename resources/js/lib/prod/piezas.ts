/**
 * La marca no identifica por sí sola a una pieza: un catálogo puede repetirla en
 * varias etapas de la obra, así que el modelo es el par marca + etapa. Toda la UI
 * de producción debe nombrar las piezas con estos helpers para no mostrar dos
 * modelos distintos con la misma etiqueta.
 */
export function etiquetaDePieza(
    marca: string | null | undefined,
    etapa?: string | null,
): string {
    const m = (marca ?? '').trim();
    const e = (etapa ?? '').trim();

    return e === '' ? m : `${m} · ${e}`;
}

/** Clave estable para agrupar o deduplicar piezas del mismo modelo. */
export function clavePieza(
    marca: string | null | undefined,
    etapa?: string | null,
): string {
    return `${(marca ?? '').trim()}|${(etapa ?? '').trim().toUpperCase()}`;
}
