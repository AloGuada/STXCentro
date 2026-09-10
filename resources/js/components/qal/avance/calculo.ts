/**
 * El cruce entre lo que producción programó y lo que calidad vio.
 *
 * Es la parte del módulo que no es dato ni pantalla, sino criterio, y se porta
 * entera de la aplicación anterior. Lo que se escribe es sólo lo que no se puede
 * deducir —qué se piensa hacer—; todo lo demás sale de las inspecciones.
 *
 * Las dos decisiones que resuelven los problemas difíciles:
 *
 *  - **El arrastre es una lista de piezas, no un número.** Una marca no puede
 *    contarse dos veces porque es la misma marca, y siempre se puede señalar
 *    cuál lleva tres semanas sin hacerse. Un contador acumulado no permite ni
 *    una cosa ni la otra.
 *  - **Las reparaciones no vuelven al plan.** Una pieza rechazada ya está
 *    fabricada: reprogramarla la contaría dos veces, y además repararla no
 *    cuesta lo mismo que armarla. Vive en su propio bloque, con su antigüedad.
 */

import type { Fase, PiezaVista, Plan } from './datos';
import { leerMarcas, tipoDeMarca, type MarcaPlan } from './marcas';

/** Porcentaje entero, o `null` cuando el denominador es cero. */
export function porcentaje(parte: number, total: number): number | null {
    return total > 0 ? Math.round((parte * 100) / total) : null;
}

/** El índice de piezas por `marca|obra`, que es como viene el plan. */
export type IndicePiezas = Map<string, PiezaVista[]>;

export function indexar(piezas: PiezaVista[]): IndicePiezas {
    const indice: IndicePiezas = new Map();
    piezas.forEach((p) => {
        const clave = `${p.marca}|${p.obra}`;
        const previas = indice.get(clave);
        if (previas) {
            previas.push(p);
        } else {
            indice.set(clave, [p]);
        }
    });
    return indice;
}

/** El estado de una línea del plan: lo programado contra lo que vio calidad. */
export type EstadoLinea = {
    marca: string;
    tipo: string;
    cantidad: number;
    /** Presentadas a inspección hasta esta semana, inclusive. */
    fabricadas: number;
    fabricadasSemana: number;
    liberadas: number;
    liberadasSemana: number;
    rechazadas: number;
    rechazadasSemana: number;
    /** Piezas que ahora mismo están rechazadas y sin volver a liberarse. */
    enReparacion: number;
    pendientes: number;
    /** De lo pendiente, lo que ya está empezado. */
    empezadas: number;
    sinEmpezar: number;
    piezas: PiezaVista[];
    /** Viene de una semana anterior que no se cerró. */
    arrastrada: boolean;
    /** La semana en que se programó por primera vez. */
    desde: string;
};

/**
 * Cruza una marca del plan con sus piezas.
 *
 * Los rechazos se cuentan **hasta esta semana**, no sólo los de la semana en
 * curso. Contar sólo los del lunes dejaba los contadores a cero cuando el plan
 * arrastraba piezas rechazadas antes: del plan importa qué ha pasado con sus
 * piezas, no qué pasó en siete días.
 */
export function estadoDeLinea(
    indice: IndicePiezas,
    enProceso: Map<string, number>,
    marca: string,
    obra: string,
    cantidad: number,
    semana: string,
): Omit<EstadoLinea, 'arrastrada' | 'desde'> {
    const piezas = indice.get(`${marca}|${obra}`) ?? [];

    const fabricadas = piezas.filter((p) => p.semanaFabricada <= semana).length;
    const liberadas = piezas.filter((p) => p.semanaLiberada && p.semanaLiberada <= semana).length;
    const pendientes = Math.max(0, cantidad - fabricadas);
    // Lo empezado nunca puede pasar de lo pendiente: una pieza ya fabricada no
    // sigue «en proceso» por mucho que su marca tenga otras a medias.
    const empezadas = Math.min(pendientes, enProceso.get(`${marca}|${obra}`) ?? 0);

    return {
        marca,
        tipo: tipoDeMarca(marca),
        cantidad,
        fabricadas,
        fabricadasSemana: piezas.filter((p) => p.semanaFabricada === semana).length,
        liberadas,
        liberadasSemana: piezas.filter((p) => p.semanaLiberada === semana).length,
        rechazadas: piezas.filter((p) => p.semanasRechazada.some((w) => w <= semana)).length,
        rechazadasSemana: piezas.filter((p) => p.semanasRechazada.includes(semana)).length,
        enReparacion: piezas.filter((p) => p.estatus === 'Rechazado').length,
        pendientes,
        empezadas,
        sinEmpezar: Math.max(0, pendientes - empezadas),
        piezas,
    };
}

/**
 * Las líneas de la semana: lo programado ahora más lo que se arrastra.
 *
 * El arrastre recorre los planes anteriores de esa obra y esa transformación, y
 * se queda con las marcas que siguen sin fabricarse y que no vuelven a estar en
 * el plan de esta semana. Las dadas de baja se caen: para eso existe la lista de
 * bajas — declarar que una pieza ya no se va a hacer es la única forma de que
 * deje de arrastrarse para siempre.
 */
export function lineasDeLaSemana(
    planes: Plan[],
    indice: IndicePiezas,
    enProceso: Map<string, number>,
    obra: string,
    fase: Fase,
    semana: string,
): EstadoLinea[] {
    const suyos = planes.filter((p) => p.obra === obra && p.fase === fase);
    const plan = suyos.find((p) => p.semana === semana) ?? null;

    const bajas = new Set<string>();
    suyos.forEach((p) => leerMarcas(p.bajas).forEach((x) => bajas.add(x.marca)));

    const deEstaSemana: MarcaPlan[] = plan ? leerMarcas(plan.marcas).filter((x) => !bajas.has(x.marca)) : [];
    const enPlan = new Set(deEstaSemana.map((x) => x.marca));

    const lineas: EstadoLinea[] = deEstaSemana.map((x) => ({
        ...estadoDeLinea(indice, enProceso, x.marca, obra, x.cantidad, semana),
        arrastrada: false,
        desde: semana,
    }));

    const yaArrastrada = new Set<string>();
    suyos
        .filter((p) => p.semana < semana)
        .sort((a, b) => a.semana.localeCompare(b.semana))
        .forEach((p) => {
            leerMarcas(p.marcas).forEach((x) => {
                if (bajas.has(x.marca) || enPlan.has(x.marca) || yaArrastrada.has(x.marca)) {
                    return;
                }
                const estado = estadoDeLinea(indice, enProceso, x.marca, obra, x.cantidad, semana);
                if (estado.pendientes > 0) {
                    yaArrastrada.add(x.marca);
                    lineas.push({ ...estado, arrastrada: true, desde: p.semana });
                }
            });
        });

    // Lo atrasado primero: es de lo que se habla en la reunión.
    return lineas.sort((a, b) => {
        if (a.arrastrada !== b.arrastrada) {
            return a.arrastrada ? -1 : 1;
        }
        return a.marca.localeCompare(b.marca, 'es', { numeric: true });
    });
}

export type Totales = {
    programadas: number;
    nuevas: number;
    arrastre: number;
    fabricadas: number;
    fabricadasSemana: number;
    pendientes: number;
    liberadas: number;
    liberadasSemana: number;
    rechazadas: number;
    rechazadasSemana: number;
    enReparacion: number;
    empezadas: number;
    sinEmpezar: number;
    /** Se fabricó lo que se dijo. Mide a producción. */
    cumplimiento: number | null;
    /** De lo fabricado, cuánto se rechazó. */
    tasaRechazo: number | null;
    /** De lo fabricado, cuánto pasó el filtro. */
    tasaLiberacion: number | null;
    /** De lo planeado, cuánto terminó saliendo. El número de la reunión. */
    salieron: number | null;
};

export function totalizar(lineas: EstadoLinea[]): Totales {
    const suma = (dime: (l: EstadoLinea) => number) => lineas.reduce((a, l) => a + dime(l), 0);

    const programadas = suma((l) => l.cantidad);
    const fabricadas = suma((l) => l.fabricadas);
    const liberadas = suma((l) => l.liberadas);
    const rechazadas = suma((l) => l.rechazadas);

    return {
        programadas,
        nuevas: lineas.filter((l) => !l.arrastrada).reduce((a, l) => a + l.cantidad, 0),
        arrastre: lineas.filter((l) => l.arrastrada).reduce((a, l) => a + l.pendientes, 0),
        fabricadas,
        fabricadasSemana: suma((l) => l.fabricadasSemana),
        pendientes: suma((l) => l.pendientes),
        liberadas,
        liberadasSemana: suma((l) => l.liberadasSemana),
        rechazadas,
        rechazadasSemana: suma((l) => l.rechazadasSemana),
        enReparacion: suma((l) => l.enReparacion),
        empezadas: suma((l) => l.empezadas),
        sinEmpezar: suma((l) => l.sinEmpezar),
        cumplimiento: porcentaje(fabricadas, programadas),
        tasaRechazo: porcentaje(rechazadas, fabricadas),
        tasaLiberacion: porcentaje(liberadas, fabricadas),
        salieron: porcentaje(liberadas, programadas),
    };
}

export type FilaTipo = {
    tipo: string;
    programadas: number;
    fabricadas: number;
    pendientes: number;
    liberadas: number;
    rechazadas: number;
    empezadas: number;
};

/** El corte por tipo de pieza, que es como está escrita la pizarra del taller. */
export function porTipoDePieza(lineas: EstadoLinea[]): FilaTipo[] {
    const grupos = new Map<string, FilaTipo>();

    lineas.forEach((l) => {
        const tipo = l.tipo || '—';
        const fila = grupos.get(tipo) ?? {
            tipo,
            programadas: 0,
            fabricadas: 0,
            pendientes: 0,
            liberadas: 0,
            rechazadas: 0,
            empezadas: 0,
        };
        fila.programadas += l.cantidad;
        fila.fabricadas += l.fabricadas;
        fila.pendientes += l.pendientes;
        fila.liberadas += l.liberadas;
        fila.rechazadas += l.rechazadas;
        fila.empezadas += l.empezadas;
        grupos.set(tipo, fila);
    });

    return [...grupos.values()].sort((a, b) => a.tipo.localeCompare(b.tipo, 'es'));
}

/** El estado con el que se pinta una línea en la tabla pieza por pieza. */
export function estadoDeFila(
    linea: EstadoLinea,
    fase: Fase,
): { texto: string; tono: 'ok' | 'warn' | 'error' | 'info' | 'neutro' } {
    if (linea.pendientes >= linea.cantidad) {
        return linea.empezadas > 0
            ? { texto: fase === '3' ? 'Lista para pintar' : 'Empezada, sin terminar', tono: 'info' }
            : { texto: 'Sin empezar', tono: 'neutro' };
    }
    if (linea.enReparacion) {
        return { texto: 'En reparación', tono: 'error' };
    }
    if (linea.liberadas >= linea.cantidad) {
        return { texto: 'Liberada', tono: 'ok' };
    }
    if (linea.fabricadas >= linea.cantidad) {
        return { texto: 'Fabricada, sin liberar', tono: 'warn' };
    }
    return { texto: `${linea.fabricadas} de ${linea.cantidad} fabricadas`, tono: 'warn' };
}
