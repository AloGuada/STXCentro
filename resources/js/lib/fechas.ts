/**
 * Fechas que llegan del backend.
 *
 * Un cast `date` de Eloquent se serializa como `2026-07-20T00:00:00.000000Z`,
 * o sea medianoche **UTC**. Pasarlo por `new Date(...)` y formatearlo en México
 * (UTC-6) lo corre al día anterior, y concatenarle `'T00:00:00'` produce un
 * `Invalid Date`. Por eso una fecha de calendario se lee tal cual viene escrita,
 * sin convertir zona horaria: el 20 de julio es el 20 de julio en cualquier lado.
 *
 * Los timestamps reales (`fecha_cambio`, `created_at`) sí son un instante en el
 * tiempo y ahí la conversión a la zona del usuario es la correcta: para esos va
 * {@link formatFechaHora}.
 */

/** `YYYY-MM-DD` para un `<input type="date">`. Acepta ISO completo o fecha suelta. */
export function fechaParaInput(valor?: string | null): string {
    return valor ? valor.slice(0, 10) : '';
}

/** Fecha de calendario como `Date` a medianoche local, sin correrse de día. */
export function parseFecha(valor?: string | null): Date | null {
    const iso = fechaParaInput(valor);

    if (!iso) {
        return null;
    }

    const [anio, mes, dia] = iso.split('-').map(Number);

    if (!anio || !mes || !dia) {
        return null;
    }

    return new Date(anio, mes - 1, dia);
}

/** Fecha de calendario en dd/mm/aaaa. Devuelve `-` si viene vacía o inválida. */
export function formatFecha(valor?: string | null, opciones?: Intl.DateTimeFormatOptions): string {
    const fecha = parseFecha(valor);

    return fecha === null
        ? '-'
        : fecha.toLocaleDateString('es-MX', opciones ?? { day: '2-digit', month: '2-digit', year: 'numeric' });
}

/** Momento exacto (timestamps): aquí sí se convierte a la zona del usuario. */
export function formatFechaHora(valor?: string | null): string {
    if (!valor) {
        return '-';
    }

    const fecha = new Date(valor);

    return Number.isNaN(fecha.getTime()) ? '-' : fecha.toLocaleString('es-MX');
}
