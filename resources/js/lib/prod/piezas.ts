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

/**
 * Cómo se nombra una unidad suelta. El QR es lo único que la identifica dentro
 * del catálogo; el QS acompaña como dato de planta y desde el layout con lotes
 * puede venir vacío, así que nunca se muestra solo.
 */
export function etiquetaDeUnidad(pieza: { qr?: string | null; qs?: string | null }): string {
    const qr = (pieza.qr ?? '').trim();
    const qs = (pieza.qs ?? '').trim();

    if (qr !== '' && qs !== '') {
        return `QR ${qr} · QS ${qs}`;
    }

    if (qr !== '') {
        return `QR ${qr}`;
    }

    return qs === '' ? '—' : `QS ${qs}`;
}

/** Clave estable para agrupar o deduplicar piezas del mismo modelo. */
export function clavePieza(
    marca: string | null | undefined,
    lote?: string | null,
): string {
    return `${(marca ?? '').trim()}|${(lote ?? '').trim().toUpperCase()}`;
}
