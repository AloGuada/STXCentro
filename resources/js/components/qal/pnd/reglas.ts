/**
 * Las dos reglas que la pantalla de PND calcula sola.
 *
 * Ambas están también en el servidor —`PndJunta::descomponerReferencia()` y la
 * validación del año/semana—, que es donde mandan. Aquí viven para que el
 * capturista vea el resultado mientras teclea y pueda corregirlo antes de
 * guardar; no para sustituir al servidor.
 */

/**
 * Parte la referencia del laboratorio en junta y punto de examen.
 *
 * `J-18-1-2` → junta `18-1`, spot `2`. La `J` inicial es decorativa y el último
 * segmento es el punto.
 *
 * Con menos de tres segmentos **no se separa nada**: `J-18-1` queda como junta
 * `18-1`, spot 1. La junta se nombra `módulo-junta`, así que partir dos
 * segmentos convertiría el número de junta en un spot y contaría cada junta
 * como una junta distinta.
 */
export function descomponerReferencia(referencia: string): { junta: string; spot: number } {
    const limpia = referencia.trim().replace(/^[Jj]\s*[-_\s]\s*/, '');
    const segmentos = limpia.split(/[-_\s]+/).filter(Boolean);

    if (segmentos.length >= 3 && /^\d+$/.test(segmentos[segmentos.length - 1])) {
        const spot = Number(segmentos.pop());

        return { junta: segmentos.join('-'), spot: Math.max(spot, 1) };
    }

    return { junta: segmentos.join('-') || limpia, spot: 1 };
}

/**
 * Año y semana ISO de una fecha `YYYY-MM-DD`.
 *
 * El año viene de la semana, no de la fecha: el 31 de diciembre puede ser la
 * semana 1 del año siguiente. Guardar la semana con el año de la fecha es lo
 * que hace que un informe de fin de año aparezca en el ejercicio equivocado.
 */
export function semanaIsoDe(fecha: string): { anio: number; semana: number } | null {
    const partes = fecha.slice(0, 10).split('-');

    if (partes.length < 3 || partes.some((parte) => !/^\d+$/.test(parte))) {
        return null;
    }

    const dia = new Date(Number(partes[0]), Number(partes[1]) - 1, Number(partes[2]));
    dia.setHours(0, 0, 0, 0);
    // Al jueves de esa semana: es el día que decide a qué año ISO pertenece.
    dia.setDate(dia.getDate() + 4 - (dia.getDay() || 7));

    const inicio = new Date(dia.getFullYear(), 0, 1);
    const semana = Math.ceil(((dia.getTime() - inicio.getTime()) / 86400000 + 1) / 7);

    return { anio: dia.getFullYear(), semana };
}
