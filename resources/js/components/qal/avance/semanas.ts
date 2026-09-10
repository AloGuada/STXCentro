/**
 * Semanas ISO, escritas siempre con sus fechas.
 *
 * «2026-S31» no le dice a nadie de qué días habla. La aplicación anterior
 * aprendió que enseñar el rango —«27 jul – 2 ago»— es la diferencia entre
 * entender la pantalla y no entenderla, porque quien programa razona en días de
 * taller, no en números de semana.
 */

const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

/** La clave de semana de una fecha `YYYY-MM-DD`: `2026-S37`. */
export function claveSemana(fecha: string): string {
    const d = new Date(`${fecha.slice(0, 10)}T00:00:00`);
    if (isNaN(d.getTime())) {
        return '';
    }
    const jueves = new Date(d);
    jueves.setHours(0, 0, 0, 0);
    jueves.setDate(jueves.getDate() + 4 - (jueves.getDay() || 7));
    const enero = new Date(jueves.getFullYear(), 0, 1);
    const numero = Math.ceil(((jueves.getTime() - enero.getTime()) / 86400000 + 1) / 7);
    return `${jueves.getFullYear()}-S${String(numero).padStart(2, '0')}`;
}

/** El lunes de una clave de semana. */
export function lunesDe(clave: string): Date | null {
    const partes = clave.match(/^(\d{4})-S(\d{2})$/);
    if (!partes) {
        return null;
    }
    // El 4 de enero cae siempre en la semana 1 por definición de la norma ISO.
    const enero4 = new Date(+partes[1], 0, 4);
    const primerLunes = new Date(enero4);
    primerLunes.setDate(enero4.getDate() - ((enero4.getDay() || 7) - 1));
    const lunes = new Date(primerLunes);
    lunes.setDate(primerLunes.getDate() + (+partes[2] - 1) * 7);
    return lunes;
}

/** El rango legible de una semana: «7 sep – 13 sep». */
export function rangoSemana(clave: string): string {
    const lunes = lunesDe(clave);
    if (!lunes) {
        return '';
    }
    const domingo = new Date(lunes);
    domingo.setDate(lunes.getDate() + 6);
    const dime = (d: Date) => `${d.getDate()} ${MESES[d.getMonth()]}`;
    return `${dime(lunes)} – ${dime(domingo)}`;
}

/** El número de semana suelto, para los títulos. */
export function numeroSemana(clave: string): string {
    return clave.replace(/^\d{4}-S/, '');
}

/** La semana que está `saltos` semanas más adelante (o atrás, si es negativo). */
export function semanaMas(clave: string, saltos: number): string {
    const lunes = lunesDe(clave);
    if (!lunes) {
        return clave;
    }
    lunes.setDate(lunes.getDate() + saltos * 7);
    return claveSemana(lunes.toISOString().slice(0, 10));
}

/** Días transcurridos desde una fecha `YYYY-MM-DD` hasta hoy. */
export function diasDesde(fecha: string, hoy = new Date()): number {
    const d = new Date(`${fecha}T00:00:00`);
    return isNaN(d.getTime()) ? 0 : Math.round((hoy.getTime() - d.getTime()) / 86400000);
}
