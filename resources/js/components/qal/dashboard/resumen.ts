/**
 * Las tarjetas y las frases del tablero, armadas con lo que calcula el
 * servidor. Aquí no se cuenta nada: se decide qué se dice y qué se calla.
 */

import { num, pct } from '@/components/qal/paleta';
import {
    COBERTURA_MINIMA,
    JUNTAS_MINIMAS,
    type BaseTasa,
    type DimensionRechazo,
    type FaseReportada,
    type Kpi,
    type OperacionTablero,
    type RechazoPorDimension,
    type ResumenTablero,
    type TasaNormalizada,
} from './tipos';

const NOMBRE_FASE: Record<FaseReportada, string> = { '2ª': 'fabricación', '3ª': 'pintura' };

const NOMBRE_BASE: Record<BaseTasa, string> = { elem: 'por elemento', ton: 'por tonelada', m2: 'por m²' };

const FASES: FaseReportada[] = ['2ª', '3ª'];

/** Una tasa normalizada se publica sólo con cobertura suficiente. */
export function tasaPublicable(tasa: TasaNormalizada): boolean {
    return tasa.tasa !== null && tasa.cobertura.pct !== null && tasa.cobertura.pct >= COBERTURA_MINIMA;
}

/**
 * Cuatro números: cuánto se hizo, cuánto se rechazó en cada transformación y
 * qué falta por cerrar. El rechazo va partido en dos porque fabricación y
 * pintura hoy ni son las mismas piezas ni tienen el mismo nivel.
 */
export function kpisEjecutivo(resumen: ResumenTablero): Kpi[] {
    const rechazo = (fase: FaseReportada): Kpi => {
        const datos = resumen.rechazo[fase];

        return {
            etiqueta: fase === '2ª' ? 'Rechazo en fabricación (2ª)' : 'Rechazo en pintura (3ª)',
            valor: pct(datos?.pct ?? null),
            nota: datos
                ? `${num(datos.conRechazo)} de ${num(datos.piezas)} piezas con rechazo · ${num(datos.reprocesos)} reprocesos`
                : 'sin piezas con veredicto',
            delta: datos?.delta ?? null,
            sufijo: 'pp',
            tono: 'malo',
        };
    };

    return [
        {
            etiqueta: 'Piezas liberadas',
            valor: num(resumen.liberadas.unidades),
            nota: `${num(resumen.liberadas.piezas)} pieza(s) distintas · ${num(resumen.liberadas.obras)} obra(s)`,
            tono: 'neutro',
        },
        rechazo('2ª'),
        rechazo('3ª'),
        { etiqueta: 'Pendientes', valor: num(resumen.pendientes), nota: 'piezas sin veredicto todavía', tono: 'pendiente' },
    ];
}

/** El detalle que sostiene a los cuatro números; cada rendimiento, con su n. */
export function kpisAnalitica(resumen: ResumenTablero, tasas: Record<BaseTasa, TasaNormalizada>): Kpi[] {
    const { inspecciones } = resumen;
    const kpis: Kpi[] = [
        {
            etiqueta: 'Inspecciones',
            valor: num(inspecciones.total),
            nota:
                inspecciones.lotes > 0
                    ? `${num(inspecciones.miradas)} piezas miradas · ${num(inspecciones.representadas)} representadas por muestreo`
                    : 'una inspección por pieza',
            tono: 'neutro',
        },
        { etiqueta: 'Obras con inspecciones', valor: num(resumen.obras), nota: 'en lo que se está mirando', tono: 'neutro' },
        {
            etiqueta: 'Inspecciones rechazadas',
            valor: pct(resumen.inspeccionesRechazadas),
            nota: 'incluye re-inspecciones',
            tono: 'malo',
        },
    ];

    for (const fase of FASES) {
        const fpy = resumen.fpy[fase];
        if (fpy) {
            kpis.push({
                etiqueta: `FPY ${fase} (${NOMBRE_FASE[fase]})`,
                valor: pct(fpy.pct),
                nota: `pasó a la primera sin retoque · n=${num(fpy.n)} piezas-etapa`,
                tono: 'bueno',
            });
        }
    }

    for (const fase of FASES) {
        const final = resumen.rechazoFinal[fase];
        if (final) {
            kpis.push({
                etiqueta: `Rechazo final ${fase}`,
                valor: pct(final.pct),
                nota: `quedó rechazada al cierre · n=${num(final.n)}`,
                tono: 'malo',
            });
        }
    }

    kpis.push({
        etiqueta: 'Piezas en ambas etapas',
        valor: num(resumen.enAmbas),
        nota: 'fabricadas Y pintadas · el resto sólo ha pasado por una',
        tono: 'neutro',
    });

    const tarjetas: [BaseTasa, string, number][] = [
        ['elem', 'Defectos/elemento', 3],
        ['ton', 'Defectos/tonelada', 2],
        ['m2', 'Defectos/m² pintado', 3],
    ];
    for (const [base, etiqueta, decimales] of tarjetas) {
        const tasa = tasas[base];
        if (tasaPublicable(tasa)) {
            kpis.push({
                etiqueta,
                valor: (tasa.tasa as number).toFixed(decimales),
                nota: `1ª inspección · cobertura ${pct(tasa.cobertura.pct, 0)} (${num(tasa.cobertura.con)} de ${num(tasa.cobertura.total)} piezas)`,
                tono: 'neutro',
            });
        }
    }

    const juntas = resumen.juntas;
    if (juntas && juntas.n >= JUNTAS_MINIMAS) {
        const nota = `mapeo de juntas · n=${num(juntas.n)}`;
        kpis.push(
            { etiqueta: 'FPY soldadura', valor: pct(juntas.fpy), nota, tono: 'bueno' },
            { etiqueta: 'Yield final juntas', valor: pct(juntas.final), nota, tono: 'bueno' },
            { etiqueta: 'Reproceso juntas', valor: pct(juntas.reproceso, 0), nota: `${num(juntas.n)} juntas mapeadas`, tono: 'malo' },
        );
    } else if (juntas) {
        kpis.push({
            etiqueta: 'Juntas mapeadas',
            valor: num(juntas.n),
            nota: `hacen falta ${JUNTAS_MINIMAS} para publicar sus porcentajes`,
            tono: 'neutro',
        });
    }

    return kpis;
}

/** Lo que se calla también se dice, una sola vez y en pequeño. */
export function tasasSinBase(tasas: Record<BaseTasa, TasaNormalizada>): { nombre: string; cobertura: number }[] {
    return (Object.keys(NOMBRE_BASE) as BaseTasa[])
        .filter((base) => tasas[base].cobertura.pct !== null && !tasaPublicable(tasas[base]))
        .map((base) => ({ nombre: NOMBRE_BASE[base], cobertura: Math.round(tasas[base].cobertura.pct as number) }));
}

/** Lo que pide acción: material en riesgo y piezas que se quedaron sin cerrar. */
export function alertas(resumen: ResumenTablero): string[] {
    const { lotesSinDisposicion, pendientesViejas } = resumen.alertas;
    const lista: string[] = [];

    if (lotesSinDisposicion.lotes > 0) {
        lista.push(
            `${num(lotesSinDisposicion.lotes)} lote(s) rechazado(s) sin anotar qué se hizo con ellos — ${num(lotesSinDisposicion.piezas)} piezas.`,
        );
    }
    if (pendientesViejas > 0) {
        lista.push(
            `${num(resumen.pendientes)} piezas siguen sin veredicto; ${num(pendientesViejas)} llevan más de dos semanas.`,
        );
    }

    return lista;
}

/**
 * Frases de una línea que no repiten las tarjetas: dicen qué mirar. Sólo salen
 * cuando el dato las sostiene; si no hay nada que decir, no se dice nada.
 */
export function hallazgos(resumen: ResumenTablero, operacion: OperacionTablero): string[] {
    const frases: string[] = [];

    for (const fase of FASES) {
        const delta = resumen.rechazo[fase]?.delta;
        if (delta !== null && delta !== undefined && Math.abs(delta) >= 0.5) {
            frases.push(
                `El rechazo de ${NOMBRE_FASE[fase]} ${delta < 0 ? 'bajó' : 'subió'} ${Math.abs(delta).toFixed(1)} pp contra la semana anterior.`,
            );
        }
    }

    const soldadura = operacion.pareto.p2_deftypes;
    const defectos = soldadura.reduce((a, d) => a + d.n, 0);
    if (soldadura.length >= 3 && defectos > 0) {
        const parte = ((soldadura[0].n + soldadura[1].n) * 100) / defectos;
        if (parte >= 50) {
            frases.push(
                `${soldadura[0].causa} y ${soldadura[1].causa} concentran el ${parte.toFixed(0)} % de los defectos de soldadura: dos causas, no ${soldadura.length}.`,
            );
        }
    }

    const [peor, ...resto] = operacion.rechazoPor.obra.filas;
    const piezasResto = resto.reduce((a, o) => a + o.n, 0);
    if (peor && piezasResto > 0) {
        const base = resto.reduce((a, o) => a + (o.pct * o.n) / 100, 0) * (100 / piezasResto);
        if (base > 0 && peor.pct >= 2 * base) {
            frases.push(
                `${peor.nombre} tiene el ${peor.pct.toFixed(1)} % de sus piezas con rechazo, más del doble que el resto de las obras (${base.toFixed(1)} %).`,
            );
        }
    }

    return frases;
}

const NOTA_DIMENSION: Partial<Record<DimensionRechazo, string>> = {
    p2_subetapa:
        'En armado, «pendiente» es un resultado correcto —la pieza avanza a soldado— y por eso cuenta como no rechazada.',
    p1_subtipo: 'Sólo las piezas de 1ª tienen perfil o placa.',
    soldador: 'Sólo las piezas que registraron soldador.',
    modulo: 'El módulo lleva su obra: «1.1» en dos obras son dos módulos distintos.',
    inspector:
        'Los inspectores no se reparten las etapas por igual: una diferencia puede ser la etapa que les toca, no su criterio.',
};

/** El pie de «Rechazo por»: la definición y lo que se dejó fuera, dicho. */
export function notaRechazoPor(dimension: DimensionRechazo, datos: RechazoPorDimension): string {
    const partes = ['% de piezas con al menos un rechazo. Una pieza rechazada tres veces es una pieza mala, no tres.'];

    if (NOTA_DIMENSION[dimension]) {
        partes.push(NOTA_DIMENSION[dimension]);
    }
    if (datos.bases.length > 1) {
        partes.push(
            `Esta lista mezcla transformaciones y cada una tiene su base de rechazo (${datos.bases
                .map((b) => `${b.fase} ${b.pct.toFixed(0)} %`)
                .join(' · ')}): ordenarlas juntas ordena sobre todo por etapa. Para comparar, filtra una transformación arriba.`,
        );
    }
    if (datos.cubiertas < datos.total) {
        partes.push(`${num(datos.cubiertas)} de ${num(datos.total)} piezas con veredicto entran aquí.`);
    }
    if (datos.fuera > 0) {
        partes.push(
            `${num(datos.fuera)} grupo(s) con menos de ${datos.minimo} piezas quedan fuera: un 100 % sobre dos piezas no es una tasa.`,
        );
    }
    if (datos.publicables > datos.filas.length) {
        partes.push(`Se muestran los ${datos.filas.length} de mayor rechazo, de ${num(datos.publicables)}.`);
    }

    return partes.join(' ');
}
