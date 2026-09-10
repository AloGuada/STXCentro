/**
 * Datos de ejemplo del avance de producción.
 *
 * Son FALSOS a propósito, como en el resto del módulo: `qal_inspecciones` y
 * `qal_programaciones` no existen todavía. Aquí se reproduce la forma de lo que
 * la pantalla va a leer —piezas ya reducidas a su estado, y planes semanales—
 * para poder construirla y revisarla con el área.
 *
 * Lo que NO es de ejemplo y vive en `marcas.ts`, `semanas.ts` y `calculo.ts`:
 * el parseo de la lista pegada, las semanas ISO y el cruce contra lo que vio
 * calidad. Eso es criterio de la empresa y se porta tal cual.
 */

import { OBRAS } from '@/components/qal/captura/datos';
import { claveSemana, semanaMas } from './semanas';

/**
 * Las transformaciones que se programan.
 *
 * **1ª se queda fuera a propósito**: trabaja a otra velocidad y con otro
 * sistema. Y 2ª y 3ª llevan cada una su plan porque no van al mismo ritmo — una
 * pieza que se termina de fabricar el viernes no da tiempo a pintarse esa
 * semana, y contarla como «no terminada» sería injusto con pintura.
 */
export type Fase = '2' | '3';

export type DefinicionFase = {
    id: Fase;
    nombre: string;
    corto: string;
    /** El participio: «fabricadas», «pintadas». */
    verbo: string;
    /** El infinitivo, para los títulos: «Piezas a fabricar». */
    gerundio: string;
};

export const FASES: DefinicionFase[] = [
    { id: '2', nombre: '2ª · Fabricación', corto: '2ª', verbo: 'fabricadas', gerundio: 'fabricar' },
    { id: '3', nombre: '3ª · Pintura', corto: '3ª', verbo: 'pintadas', gerundio: 'pintar' },
];

export function faseDe(id: Fase): DefinicionFase {
    return FASES.find((f) => f.id === id) ?? FASES[0];
}

/**
 * Una pieza vista desde calidad, ya reducida a su historia.
 *
 * No es un registro de inspección: es el resumen de todas las inspecciones de
 * esa pieza en esa transformación. La pantalla razona con esto, no con los
 * registros sueltos, porque una pieza reinspeccionada tres veces sigue siendo
 * una pieza.
 */
export type PiezaVista = {
    marca: string;
    obra: string;
    consec: number;
    /** Semana en que se presentó a inspección por primera vez. */
    semanaFabricada: string;
    /** Semana en que se liberó, si se liberó. */
    semanaLiberada: string;
    /** Nº de inspección en que se liberó: >1 significa que hubo retrabajo. */
    inspeccionLiberada: number | null;
    semanasRechazada: string[];
    estatus: 'Liberado' | 'Rechazado' | 'Pendiente';
    /** Fecha de la última inspección, para contar los días parada. */
    fechaUltima: string;
    inspecciones: number;
    inspector: string;
};

/** Un plan semanal: una obra, una semana, una transformación. */
export type Plan = {
    semana: string;
    obra: string;
    fase: Fase;
    /** El texto pegado, tal cual. Se vuelve a leer con `leerMarcas`. */
    marcas: string;
    /** Piezas que ya no se van a fabricar: dejan de arrastrarse. */
    bajas: string;
    notas: string;
};

export const INSPECTORES = ['Ana Puc', 'Jorge Balam', 'Luis Canché', 'Rosa Uc'];

/** Las obras que este módulo sigue. Salen del mismo catálogo que la captura. */
export const OBRAS_AVANCE = OBRAS.slice(0, 5);

const TIPOS = ['CM', 'TP', 'TS', 'AR', 'LC', 'CVC'];

/** Generador determinista: la maqueta tiene que verse igual en cada recarga. */
function generador(semilla: number): () => number {
    let estado = semilla;
    return () => {
        estado = (estado * 1103515245 + 12345) % 2147483648;
        return estado / 2147483648;
    };
}

function elige<T>(azar: () => number, lista: readonly T[]): T {
    return lista[Math.floor(azar() * lista.length)];
}

/** La semana de hoy, que es la que abre la pantalla. */
export function semanaActual(): string {
    return claveSemana(new Date().toISOString().slice(0, 10));
}

/** Las semanas que ofrece el selector: ocho atrás y una adelante. */
export function semanasDisponibles(): string[] {
    const hoy = semanaActual();
    const lista: string[] = [];
    for (let i = 1; i >= -8; i--) {
        lista.push(semanaMas(hoy, i));
    }
    return lista;
}

/** El lunes de una semana, en `YYYY-MM-DD`, para fechar las inspecciones. */
function fechaEnSemana(semana: string, dia: number): string {
    const partes = semana.match(/^(\d{4})-S(\d{2})$/);
    if (!partes) {
        return new Date().toISOString().slice(0, 10);
    }
    const enero4 = new Date(+partes[1], 0, 4);
    const primerLunes = new Date(enero4);
    primerLunes.setDate(enero4.getDate() - ((enero4.getDay() || 7) - 1));
    const d = new Date(primerLunes);
    d.setDate(primerLunes.getDate() + (+partes[2] - 1) * 7 + dia);
    return d.toISOString().slice(0, 10);
}

type Maqueta = {
    /** Piezas por transformación. */
    piezas: Record<Fase, PiezaVista[]>;
    /** Marcas que están a medias: pasaron el paso anterior y no el de esta fase. */
    enProceso: Record<Fase, Map<string, number>>;
    planes: Plan[];
};

/**
 * Arma la maqueta entera de una vez.
 *
 * Se genera junto —planes y piezas— porque tienen que ser coherentes: un plan
 * cuyas marcas no existan en ninguna inspección daría 0% de cumplimiento en
 * todas las obras y la pantalla no enseñaría nada. Aquí se programa una lista y
 * se «fabrica» una parte de ella, que es lo que pasa en el taller.
 */
export function maquetaDeEjemplo(): Maqueta {
    const azar = generador(20260910);
    const semanas = semanasDisponibles().filter((s) => s <= semanaActual());

    const planes: Plan[] = [];
    const piezas: Record<Fase, PiezaVista[]> = { '2': [], '3': [] };
    const enProceso: Record<Fase, Map<string, number>> = { '2': new Map(), '3': new Map() };

    OBRAS_AVANCE.forEach((obra, indiceObra) => {
        // Una obra se deja sin plan escrito a propósito: la pantalla tiene que
        // saber enseñar «sin plan» sin romperse, y es el caso de la obra que
        // acaba de empezar.
        const programa = indiceObra < 4;

        FASES.forEach((F) => {
            semanas.forEach((semana, indiceSemana) => {
                if (!programa) {
                    return;
                }

                const cuantas = Math.floor(azar() * 6) + 4;
                const marcas: string[] = [];
                const bajas: string[] = [];

                for (let i = 0; i < cuantas; i++) {
                    const tipo = elige(azar, TIPOS);
                    const marca = `${obra.slice(0, 3).toUpperCase()}-${tipo}${Math.floor(azar() * 9) + 1}-${Math.floor(azar() * 30) + 1}`;
                    const cantidad = azar() < 0.25 ? Math.floor(azar() * 3) + 2 : 1;
                    marcas.push(cantidad > 1 ? `${marca} x${cantidad}` : marca);

                    // Cuántas de esas piezas llegaron de verdad a inspección.
                    const llegan = azar() < 0.15 ? 0 : Math.min(cantidad, Math.ceil(cantidad * (0.5 + azar() * 0.5)));

                    for (let n = 0; n < llegan; n++) {
                        const semFab = azar() < 0.8 ? semana : semanaMas(semana, 1);
                        const rechaza = azar() < 0.18;
                        const inspecciones = rechaza ? 2 : 1;
                        // Una rechazada se resuelve la mayoría de las veces, pero
                        // no siempre: las que quedan son la carga de reparación.
                        const resuelve = rechaza && azar() < 0.6;
                        const semLib = rechaza ? (resuelve ? semanaMas(semFab, 1) : '') : semFab;

                        piezas[F.id].push({
                            marca,
                            obra,
                            consec: n + 1,
                            semanaFabricada: semFab,
                            semanaLiberada: semLib,
                            inspeccionLiberada: semLib ? inspecciones : null,
                            semanasRechazada: rechaza ? [semFab] : [],
                            estatus: semLib ? 'Liberado' : rechaza ? 'Rechazado' : 'Pendiente',
                            fechaUltima: fechaEnSemana(semLib || semFab, Math.floor(azar() * 5)),
                            inspecciones,
                            inspector: elige(azar, INSPECTORES),
                        });
                    }

                    // Lo que no llegó a inspección puede estar empezado: en 2ª
                    // pasó armado y le falta soldadura; en 3ª ya está fabricada y
                    // espera turno de pintura. No lo declara nadie — en la pantalla
                    // real sale de los propios registros.
                    const faltan = cantidad - llegan;
                    if (faltan > 0 && azar() < 0.45) {
                        const clave = `${marca}|${obra}`;
                        enProceso[F.id].set(clave, (enProceso[F.id].get(clave) ?? 0) + faltan);
                    }

                    // Alguna marca se da de baja: ya no se va a fabricar y deja
                    // de arrastrarse. Es la única salida de una pieza del plan.
                    if (indiceSemana > 0 && azar() < 0.06) {
                        bajas.push(marca);
                    }
                }

                planes.push({
                    semana,
                    obra,
                    fase: F.id,
                    marcas: marcas.join('\n'),
                    bajas: bajas.join('\n'),
                    notas: azar() < 0.2 ? 'Falta material para las TS.' : '',
                });
            });
        });
    });

    return { piezas, enProceso, planes };
}
