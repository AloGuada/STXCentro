/**
 * La marca no identifica por sí sola a una pieza: un catálogo puede repetirla en
 * varios lotes de la obra, así que el modelo es el par marca + lote. Toda la UI
 * de producción debe nombrar las piezas con estos helpers para no mostrar dos
 * modelos distintos con la misma etiqueta.
 */
export function etiquetaDePieza(
    marca: string | null | undefined,
    lote?: string | null,
): string {
    const m = (marca ?? '').trim();
    const e = (lote ?? '').trim();

    return e === '' ? m : `${m} · ${e}`;
}

/** Clave estable para agrupar o deduplicar piezas del mismo modelo. */
export function clavePieza(
    marca: string | null | undefined,
    lote?: string | null,
): string {
    return `${(marca ?? '').trim()}|${(lote ?? '').trim().toUpperCase()}`;
}
