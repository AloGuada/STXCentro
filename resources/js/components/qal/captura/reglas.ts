/**
 * Las reglas que la pantalla de captura calcula sola.
 *
 * Están aquí, fuera de los componentes, porque no son detalle de pintado: son
 * el criterio con el que se acepta o se rechaza material. Cuando exista el
 * backend, varias de éstas tendrán que vivir también del lado del servidor —
 * el formulario las adelanta para que el inspector vea el veredicto mientras
 * captura, no para ser la única autoridad.
 */

import { TABLA_MUESTREO, type NivelMuestreo } from './datos';

/** Plan de muestreo: cuántas piezas se miran y con cuántas fallas se rechaza. */
export type PlanMuestreo = {
    lote: number;
    /** Piezas a inspeccionar. */
    muestra: number;
    /** Máximo de rechazadas que todavía acepta el lote. */
    aceptacion: number;
    /** A partir de aquí el lote se rechaza. */
    rechazo: number;
};

export function planMuestreo(lote: number, nivel: NivelMuestreo, tope?: number): PlanMuestreo | null {
    if (!Number.isFinite(lote) || lote < 1) {
        return null;
    }
    const fila = TABLA_MUESTREO.find((f) => lote <= f.max) ?? TABLA_MUESTREO[TABLA_MUESTREO.length - 1];
    const [muestra, aceptacion, rechazo] = fila[nivel] ?? fila.II;

    return {
        lote,
        // El sublote de accesorios nunca inspecciona más unidades de las que trae la entrega.
        muestra: tope ? Math.min(muestra, tope) : muestra,
        aceptacion,
        rechazo,
    };
}

export type Veredicto = {
    texto: string;
    /** El veredicto ya no puede cambiar aunque se sigan mirando piezas. */
    cerrado: boolean;
    rechazado: boolean;
};

/** Veredicto AQL con lo capturado hasta ahora. */
export function veredictoMuestreo(
    plan: PlanMuestreo | null,
    conformes: number,
    rechazadas: number,
    sustantivo = 'LOTE',
): Veredicto | null {
    if (!plan) {
        return null;
    }
    const vistas = conformes + rechazadas;

    if (rechazadas >= plan.rechazo) {
        return { texto: `${sustantivo} RECHAZADO`, cerrado: true, rechazado: true };
    }
    if (vistas < plan.muestra) {
        const faltan = plan.muestra - vistas;
        return { texto: `En curso — faltan ${faltan} pieza(s)`, cerrado: false, rechazado: false };
    }
    if (rechazadas <= plan.aceptacion) {
        return { texto: `${sustantivo} ACEPTADO`, cerrado: true, rechazado: false };
    }

    return { texto: `${sustantivo} RECHAZADO`, cerrado: true, rechazado: true };
}

/** Barrenos de 1ª: se deduce de la posición y el diámetro, no se teclea. */
export function calcularBarrenos(posicion: string, diametro: string): string {
    if (posicion === 'Incorrecta' || diametro === 'Con defecto') {
        return 'Con defecto';
    }
    if (posicion === 'OK' && (diametro === 'OK' || diametro === 'n/a')) {
        return 'OK';
    }

    return '';
}

/** Dimensional de 2ª: si longitud o distancia entre placas falla, no está OK. */
export function calcularDimensional(longitud: string, placas: string): string {
    if (longitud === 'No OK' || placas === 'No OK') {
        return 'No OK';
    }
    if (longitud === 'OK' && (placas === 'OK' || placas === 'n/a')) {
        return 'OK';
    }

    return '';
}

export type Filete = { cumple: boolean; mensaje: string; diferencia: number } | null;

/**
 * Compara el tamaño medido del filete contra el requerido en el plano.
 *
 * Un filete por debajo de lo nominal es un defecto real de resistencia, no un
 * detalle: por eso el veredicto sale solo y no depende de que el inspector eche
 * la cuenta. Cuando no cumple, la junta queda marcada como defectuosa por
 * perfil (lo hace quien llama, con `PUNTO_PERFIL`).
 */
export const PUNTO_PERFIL = 'm_perfil';

export function evaluarFilete(requerido: string, medido: string): Filete {
    const req = parseFloat(requerido);
    const med = parseFloat(medido);
    if (Number.isNaN(req) || Number.isNaN(med)) {
        return null;
    }

    const diferencia = Number((med - req).toFixed(2));
    if (diferencia >= 0) {
        const cola = diferencia > 0 ? ` · ${diferencia} mm por encima` : ' · justo en el nominal';
        return { cumple: true, diferencia, mensaje: `Medido ${med} mm contra ${req} mm requeridos${cola}.` };
    }

    return {
        cumple: false,
        diferencia,
        mensaje:
            `Falta ${Math.abs(diferencia)} mm para el nominal de ${req} mm. ` +
            'Un filete por debajo de lo requerido reduce la resistencia de la junta.',
    };
}

export const ESPESOR_MEDICIONES_MAX = 15;
export const ESPESOR_MEDICIONES_BASE = 5;

export type ResumenEspesores = {
    /** Promedio de las 3 lecturas de cada medición visible; null si no se capturó. */
    promedios: (number | null)[];
    /** Promedio final de la pieza. */
    promedio: number | null;
    cumple: string;
    /** Mediciones por debajo del 80% del requerido: advertencia, no rechazo. */
    bajas: number[];
};

/**
 * Espesores de pintura (SSPC-PA2).
 *
 * Cada medición son 3 lecturas; el espesor de la pieza es el promedio de esos
 * promedios. Una medición por debajo del 80% del requerido se avisa pero NO
 * rechaza: lo que decide es el promedio final contra el requerido.
 */
export function resumirEspesores(lecturas: string[][], visibles: number, requerido: string): ResumenEspesores {
    const req = parseFloat(requerido);
    const promedios: (number | null)[] = [];
    const usados: number[] = [];
    const bajas: number[] = [];

    lecturas.forEach((medicion, indice) => {
        const valores = medicion.map((valor) => parseFloat(valor)).filter((valor) => !Number.isNaN(valor));
        if (!valores.length) {
            promedios.push(null);
            return;
        }
        const promedio = valores.reduce((a, b) => a + b, 0) / valores.length;
        promedios.push(promedio);

        if (indice < visibles) {
            usados.push(promedio);
            if (!Number.isNaN(req) && req > 0 && promedio < 0.8 * req) {
                bajas.push(indice + 1);
            }
        }
    });

    if (!usados.length) {
        return { promedios, promedio: null, cumple: '', bajas };
    }

    const promedio = usados.reduce((a, b) => a + b, 0) / usados.length;
    const cumple = Number.isNaN(req) ? '—' : promedio >= req ? 'Sí cumple' : 'NO cumple (promedio < requerido)';

    return { promedios, promedio, cumple, bajas };
}

/** Semana ISO de una fecha `YYYY-MM-DD`. Se muestra junto a la fecha, calculada. */
export function semanaIso(fecha: string): string {
    const partes = String(fecha).slice(0, 10).split('-');
    if (partes.length < 3) {
        return '';
    }
    const dia = new Date(+partes[0], +partes[1] - 1, +partes[2]);
    dia.setHours(0, 0, 0, 0);
    dia.setDate(dia.getDate() + 4 - (dia.getDay() || 7));
    const inicio = new Date(dia.getFullYear(), 0, 1);

    return String(Math.ceil(((dia.getTime() - inicio.getTime()) / 86400000 + 1) / 7));
}

export function hoyLocal(): string {
    const ahora = new Date();
    const mes = String(ahora.getMonth() + 1).padStart(2, '0');
    const dia = String(ahora.getDate()).padStart(2, '0');

    return `${ahora.getFullYear()}-${mes}-${dia}`;
}
