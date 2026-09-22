/**
 * Lo que queda del cálculo en el front: presentación.
 *
 * El cruce entre lo que producción programó y lo que calidad vio —el arrastre,
 * los rechazos hasta la semana— se hace en el servidor
 * (`app/Services/Qal/AvanceProduccion.php`). Aquí sólo el porcentaje con su
 * «—» y el estado con que se pinta una línea.
 */

import type { EstadoLinea, Fase } from './tipos';

/** Porcentaje entero, o `null` cuando el denominador es cero. */
export function porcentaje(parte: number, total: number): number | null {
    return total > 0 ? Math.round((parte * 100) / total) : null;
}

/** El estado con el que se pinta una línea en la tabla pieza por pieza. */
export function estadoDeFila(
    linea: EstadoLinea,
    fase: Fase,
): { texto: string; tono: 'ok' | 'warn' | 'error' | 'info' | 'neutro' } {
    if (linea.pendientes > 0) {
        return linea.empezadas > 0
            ? { texto: fase === '3' ? 'Lista para pintar' : 'Empezada, sin terminar', tono: 'info' }
            : { texto: 'Sin empezar', tono: 'neutro' };
    }
    if (linea.enReparacion) {
        return { texto: 'En reparación', tono: 'error' };
    }
    if (linea.liberadas > 0) {
        return { texto: 'Liberada', tono: 'ok' };
    }
    return { texto: fase === '3' ? 'Pintada, sin liberar' : 'Fabricada, sin liberar', tono: 'warn' };
}
