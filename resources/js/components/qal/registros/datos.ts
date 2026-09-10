/**
 * Datos de ejemplo y etiquetas de la pantalla de Registros.
 *
 * Son datos FALSOS a propósito, igual que en la captura: `qal_inspecciones`,
 * `qal_puntos_inspeccion`, `qal_lotes_accesorios` y `qal_sublotes_accesorios`
 * todavía no existen, así que la pantalla se construye contra la forma de la
 * tabla y no contra la base. Cuando el backend exista, esto se sustituye por
 * props del controlador y el archivo desaparece.
 *
 * Lo que NO es relleno y hay que conservar tal cual son las **etiquetas**:
 * `CAMPOS` y `CAMPOS_ACC` son el diccionario que traduce el nombre de columna
 * de la aplicación anterior al nombre que usa calidad al hablar. La ficha de
 * detalle es lo único que enseña un registro entero, y sin ese diccionario
 * saldría `p2_faltavest` en vez de «Falta de vestido».
 */

import { OBRAS, OPERADORES, RESPONSABLES, SOLDADORES } from '@/components/qal/captura/datos';

export type Fase = '1ª' | '2ª' | '3ª';
export type Estatus = 'Liberado' | 'Rechazado' | 'Pendiente';

/**
 * Un registro de inspección de pieza.
 *
 * `ts` es la identidad: la aplicación anterior no tenía id y direccionaba todo
 * por el sello de tiempo. Se conserva porque el histórico que se va a migrar
 * viene referenciado así.
 */
export type RegistroPieza = {
    id: string;
    ts: string;
    fecha: string;
    semana: number;
    fase: Fase;
    obra: string;
    marca: string;
    folio: string;
    tipo: string;
    consec: number;
    /** Nº de inspección de ESTA pieza: 2 significa que ya se revisó y se rechazó. */
    ninsp: number;
    cant: number;
    kg: number;
    modulo: string;
    linea: string;
    inspector: string;
    estatus: Estatus;
    /** Sólo en 1ª: qué tipo de pieza se revisó. */
    p1_subtipo?: string;
    /** Sólo en 2ª: armado-vestido o soldadura. */
    p2_subetapa?: string;
    campos: Record<string, string | number>;
};

/** Una inspección de sublote de accesorios. */
export type RegistroSublote = {
    id: string;
    ts: string;
    fecha: string;
    obra: string;
    /** Marca del lote completo, no del sublote. */
    marca: string;
    grupo: string;
    /** Unidades del plano, del lote entero. */
    totalLote: number;
    unidades: number;
    nivel: 'I' | 'II' | 'III';
    muestra: number;
    rechazadas: number;
    ninsp: number;
    veredicto: 'ACEPTADO' | 'RECHAZADO';
    disposicion: string;
    inspector: string;
    campos: Record<string, string | number>;
};

/**
 * El diccionario de campos de la ficha, en el orden en que se lee.
 *
 * El orden importa y no es alfabético: primero quién y cuándo, luego qué pieza,
 * y al final los puntos de la fase. Es el orden del formulario, para que quien
 * capturó reconozca lo que ve.
 */
export const CAMPOS: Record<string, string> = {
    fecha: 'Fecha',
    semana: 'Semana',
    fase: 'Transformación',
    p1_subtipo: 'Tipo pieza (1ª)',
    p2_subetapa: 'Sub-etapa (2ª)',
    inspector: 'Inspector',
    linea: 'Línea',
    modulo: 'Módulo',
    responsable: 'Responsable módulo',
    equipo: 'Equipo',
    operador: 'Operador',
    obra: 'Obra',
    marca: 'Marca',
    folio: 'Folio',
    tipo: 'Tipo',
    consec: '# de pieza',
    cant: 'Piezas en lote',
    kg: 'Peso (kg)',
    ninsp: '# Inspección',
    soldador: 'Soldador',
    estatus: 'Estatus',
    // 1ª transformación · corte y habilitado
    p1_lote: 'Tamaño lote (muestreo)',
    p1_nivel: 'Nivel muestreo',
    p1_empates: 'Empates/juntas',
    p1_dim: 'Dimensión pza',
    p1_long: 'Longitud',
    p1_defl: 'Deflexión',
    p1_tors: 'Torsión',
    p1_patin: 'Descuadre patín',
    p1_corte: 'Defectos corte',
    p1_posbar: 'Posición barrenos',
    p1_bisel: 'Bisel',
    p1_diam: 'Diámetro barrenos',
    p1_bar: 'Barrenos',
    p1_limpieza: 'Limpieza',
    p1_edoinsp: 'Edo. inspección',
    // 2ª transformación · armado y soldadura
    p2_long: 'Longitud',
    p2_placas: 'Distancia placas',
    p2_dimok: 'Dimensiones OK',
    p2_bisel: 'Ángulo de bisel',
    p2_pulido: 'Pulido',
    p2_raiz: 'Separación de raíz',
    p2_respaldo: 'Placa respaldo',
    p2_hombro: 'Hombro',
    p2_acceso: 'Acceso soldadura',
    p2_corte: 'Corte (destajo)',
    p2_faltavest: 'Falta de vestido',
    p2_diaf: 'Giro/caída placas',
    p2_desv: 'Desviación',
    p2_desvsize: 'Tamaño desviación',
    p2_precal: 'Precalentamiento',
    p2_limppasadas: 'Limpieza entre pasadas',
    p2_elem: 'Nº elementos',
    p2_soldef: 'Total defectos sold.',
    p2_deftypes: 'Tipos de defecto',
    p2_bartot: 'Barrenos total',
    p2_bardef: 'Barrenos con defecto',
    p2_limpieza: 'Limpieza mecánica',
    p2_etiqueta: 'Etiqueta',
    // 3ª transformación · pintura
    p3_req: 'Espesor requerido (mils)',
    p3_area: 'Área pintura (m²)',
    p3_metodo: 'Método',
    p3_prom: 'Espesor promedio',
    p3_cumple: '¿Cumple?',
    p3_esp: 'Prueba espesor',
    p3_vis: 'Inspección visual',
    p3_adh: 'Adherencia',
    p3_adhmet: 'Método adherencia',
    p3_adhres: 'Resultado adherencia',
    p3_rev: 'Revisión',
    p3_accion: 'Acción',
    p3_deftypes: 'Defectos pintura',
    iv: 'Insp. visual',
    is: 'Insp. soldadura',
    obs: 'Observaciones',
    ts: 'Registrado',
};

export const CAMPOS_ACC: Record<string, string> = {
    ts: 'Registrado',
    obra: 'Obra',
    marca: 'Marca del lote',
    totalLote: 'Unidades del plano',
    grupo: 'Sublote',
    unidades: 'Unidades del sublote',
    nivel: 'Nivel de muestreo',
    muestra: 'Muestra inspeccionada',
    rechazadas: 'Rechazadas en la muestra',
    veredicto: 'Veredicto del lote',
    disposicion: 'Disposición',
    ninsp: 'Nº de inspección',
    fallas: 'Detalle de las rechazadas',
    inspector: 'Inspector',
};

export const FASES: Fase[] = ['1ª', '2ª', '3ª'];
export const ESTATUS: Estatus[] = ['Liberado', 'Rechazado', 'Pendiente'];

export const INSPECTORES = ['Ana Puc', 'Jorge Balam', 'Luis Canché', 'Rosa Uc'];

const MODULOS = ['M-01', 'M-02', 'M-03', 'M-04', 'M-05'];
const LINEAS = ['L1', 'L2', 'L3', 'L4', 'L5'];
const SUBETAPAS = ['Armado-Vestido', 'Soldadura'];
const SUBTIPOS = ['Perfil', 'Placa'];
const PREFIJOS: [string, string][] = [
    ['CM', 'Columna metálica'],
    ['TP', 'Trabe principal'],
    ['TS', 'Trabe secundaria'],
    ['AR', 'Armadura'],
    ['LC', 'Larguero de cubierta / polín'],
    ['CVC', 'Contraviento de cubierta'],
];

/**
 * Generador determinista.
 *
 * Los datos de ejemplo se arman con una semilla fija y no con `Math.random()`
 * para que la pantalla se vea igual en cada recarga: si los números bailan,
 * revisar la maqueta con alguien del área se vuelve imposible —nadie sabe si
 * cambió porque se tocó un filtro o porque se recargó—.
 */
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

function fechaDe(dias: number): string {
    const d = new Date(2026, 8, 9);
    d.setDate(d.getDate() - dias);
    return d.toISOString().slice(0, 10);
}

/** Semana ISO de una fecha `YYYY-MM-DD`. */
function semanaIso(fecha: string): number {
    const d = new Date(`${fecha}T00:00:00`);
    const jueves = new Date(d);
    jueves.setDate(d.getDate() + 3 - ((d.getDay() + 6) % 7));
    const primero = new Date(jueves.getFullYear(), 0, 4);
    return 1 + Math.round(((jueves.getTime() - primero.getTime()) / 86400000 - 3 + ((primero.getDay() + 6) % 7)) / 7);
}

/**
 * Puntos de la fase, para que la ficha tenga algo que enseñar.
 *
 * Se rellenan sólo los campos de la fase del registro: una pieza de 1ª no tiene
 * ángulo de bisel, y enseñarlo vacío haría creer que se dejó sin capturar.
 */
function puntosDeFase(azar: () => number, fase: Fase, estatus: Estatus): Record<string, string | number> {
    const ok = () => (estatus === 'Rechazado' && azar() < 0.35 ? 'No cumple' : 'Cumple');

    if (fase === '1ª') {
        return {
            p1_lote: Math.floor(azar() * 200) + 20,
            p1_nivel: elige(azar, ['I', 'II', 'III']),
            p1_dim: ok(),
            p1_long: ok(),
            p1_defl: ok(),
            p1_tors: ok(),
            p1_patin: ok(),
            p1_corte: ok(),
            p1_posbar: ok(),
            p1_bisel: ok(),
            p1_diam: ok(),
            p1_limpieza: ok(),
            p1_edoinsp: estatus === 'Pendiente' ? 'En proceso' : 'Terminada',
        };
    }

    if (fase === '2ª') {
        return {
            p2_long: ok(),
            p2_placas: ok(),
            p2_dimok: ok(),
            p2_bisel: ok(),
            p2_pulido: ok(),
            p2_raiz: ok(),
            p2_respaldo: elige(azar, ['Sí', 'No aplica']),
            p2_hombro: ok(),
            p2_acceso: ok(),
            p2_faltavest: estatus === 'Rechazado' ? 'Sí' : 'No',
            p2_precal: elige(azar, ['Sí', 'No aplica']),
            p2_limppasadas: ok(),
            p2_elem: Math.floor(azar() * 12) + 2,
            p2_soldef: estatus === 'Rechazado' ? Math.floor(azar() * 4) + 1 : 0,
            p2_bartot: Math.floor(azar() * 24) + 4,
            p2_bardef: estatus === 'Rechazado' ? Math.floor(azar() * 3) : 0,
            p2_limpieza: ok(),
            p2_etiqueta: elige(azar, ['Colocada', 'Pendiente']),
            soldador: elige(azar, SOLDADORES)[0],
        };
    }

    const requerido = elige(azar, [3, 4, 5, 6]);
    const promedio = +(requerido + (estatus === 'Rechazado' ? -0.8 : 0.6) * azar()).toFixed(2);
    return {
        p3_req: requerido,
        p3_area: +(azar() * 40 + 5).toFixed(2),
        p3_metodo: elige(azar, ['Airless', 'Brocha', 'Rodillo']),
        p3_prom: promedio,
        p3_cumple: promedio >= requerido ? 'Sí' : 'No',
        p3_esp: ok(),
        p3_vis: ok(),
        p3_adh: elige(azar, ['Sí', 'No aplica']),
        p3_adhmet: 'Cinta (ASTM D3359)',
        p3_adhres: ok(),
        p3_rev: elige(azar, ['1', '2']),
    };
}

/**
 * Las inspecciones de pieza de ejemplo.
 *
 * Una pieza rechazada genera un segundo registro: es el caso que la pantalla
 * tiene que saber enseñar —el mismo `marca + obra + consec` con dos `ninsp`—,
 * y es lo que alimenta el historial de la ficha.
 */
export function registrosDeEjemplo(): RegistroPieza[] {
    const azar = generador(20260909);
    const filas: RegistroPieza[] = [];

    for (let i = 0; i < 140; i++) {
        const fase = elige(azar, FASES);
        const obra = elige(azar, OBRAS);
        const [prefijo, tipo] = elige(azar, PREFIJOS);
        const consec = Math.floor(azar() * 60) + 1;
        const marca = `${prefijo}${Math.floor(azar() * 40) + 1}`;
        const dias = Math.floor(azar() * 45);
        const fecha = fechaDe(dias);
        const sorteo = azar();
        const estatus: Estatus = sorteo < 0.16 ? 'Rechazado' : sorteo < 0.25 ? 'Pendiente' : 'Liberado';
        const inspector = elige(azar, INSPECTORES);

        const base = {
            fecha,
            semana: semanaIso(fecha),
            fase,
            obra,
            marca,
            folio: `F-${String(1200 + i)}`,
            tipo,
            consec,
            cant: 1,
            kg: +(azar() * 900 + 60).toFixed(1),
            modulo: elige(azar, MODULOS),
            linea: elige(azar, LINEAS),
            inspector,
            p1_subtipo: fase === '1ª' ? elige(azar, SUBTIPOS) : undefined,
            p2_subetapa: fase === '2ª' ? elige(azar, SUBETAPAS) : undefined,
        };

        const comunes = {
            responsable: elige(azar, RESPONSABLES),
            equipo: fase === '1ª' ? 'FICEP Valiant (perfil)' : '',
            operador: fase === '1ª' ? elige(azar, OPERADORES) : '',
        };

        /**
         * Una pieza rechazada se vuelve a inspeccionar: el registro original se
         * queda como Rechazado y nace un segundo con `ninsp: 2`. La pieza
         * cuenta una vez, los registros dos.
         */
        const reinspecciona = estatus === 'Rechazado' && azar() < 0.55;
        const intentos: Estatus[] = reinspecciona ? ['Rechazado', 'Liberado'] : [estatus];

        intentos.forEach((suyo, indice) => {
            const ninsp = indice + 1;
            const fechaSuya = fechaDe(Math.max(0, dias - indice * 3));
            const ts = `${fechaSuya}T${String(7 + (i % 10)).padStart(2, '0')}:${String((i * 7 + indice * 11) % 60).padStart(2, '0')}:00`;

            filas.push({
                ...base,
                id: `${ts}-${i}-${ninsp}`,
                ts,
                fecha: fechaSuya,
                semana: semanaIso(fechaSuya),
                estatus: suyo,
                ninsp,
                campos: {
                    ...comunes,
                    ...puntosDeFase(azar, fase, suyo),
                    obs: suyo === 'Rechazado' ? 'Se devuelve a taller para corrección.' : '',
                },
            });
        });
    }

    return filas.sort((a, b) => b.ts.localeCompare(a.ts));
}

/** Las inspecciones de sublote de ejemplo. */
export function sublotesDeEjemplo(): RegistroSublote[] {
    const azar = generador(3141592);
    const filas: RegistroSublote[] = [];
    const marcas = ['ACC-PL12', 'ACC-ANG3', 'ACC-CART7', 'ACC-TENS2', 'ACC-RIG9'];

    for (let i = 0; i < 46; i++) {
        const obra = elige(azar, OBRAS);
        const marca = elige(azar, marcas);
        const totalLote = (Math.floor(azar() * 12) + 4) * 50;
        const unidades = Math.floor(azar() * 180) + 40;
        const nivel = elige(azar, ['I', 'II', 'III'] as const);
        const muestra = Math.max(5, Math.round(unidades * (nivel === 'I' ? 0.08 : nivel === 'II' ? 0.13 : 0.2)));
        const rechazadas = azar() < 0.28 ? Math.floor(azar() * 4) + 1 : 0;
        const veredicto = rechazadas > 1 ? 'RECHAZADO' : 'ACEPTADO';
        const fecha = fechaDe(Math.floor(azar() * 45));

        filas.push({
            id: `acc-${i}`,
            ts: `${fecha}T${String(8 + (i % 8)).padStart(2, '0')}:${String((i * 13) % 60).padStart(2, '0')}:00`,
            fecha,
            obra,
            marca,
            grupo: `${marca}-S${Math.floor(azar() * 6) + 1}`,
            totalLote,
            unidades,
            nivel,
            muestra,
            rechazadas,
            ninsp: azar() < 0.15 ? 2 : 1,
            veredicto,
            /**
             * Un lote rechazado sin disposición es el hueco que la pantalla
             * tiene que señalar: el material está detenido y nadie decidió qué
             * hacer con él. Por eso algunos salen en blanco a propósito.
             */
            disposicion:
                veredicto === 'RECHAZADO'
                    ? azar() < 0.35
                        ? ''
                        : elige(azar, [
                              'Retrabajo completo del lote',
                              'Se separaron solo las piezas malas',
                              'Se revisó el lote pieza por pieza (100%)',
                              'Liberado bajo concesión',
                          ])
                    : '',
            inspector: elige(azar, INSPECTORES),
            campos: {
                fallas: rechazadas ? `${rechazadas} pza con barreno fuera de posición` : '',
            },
        });
    }

    return filas.sort((a, b) => b.ts.localeCompare(a.ts));
}

/**
 * ¿Un sublote quedó liberado?
 *
 * «Liberado bajo concesión» cuenta como liberado aunque el veredicto diga
 * RECHAZADO: el material se fue a obra, y para el avance eso es lo que importa.
 */
export function subloteLiberado(x: RegistroSublote): boolean {
    return x.veredicto === 'ACEPTADO' || /concesi/i.test(x.disposicion);
}

/** Un lote rechazado que nadie decidió qué hacer con él. */
export function sinDisposicion(x: RegistroSublote): boolean {
    return x.veredicto === 'RECHAZADO' && !subloteLiberado(x) && !x.disposicion.trim();
}

/**
 * La identidad de una PIEZA, que no es la del registro.
 *
 * Una pieza reinspeccionada tres veces son tres registros y una sola pieza.
 * Confundirlos hacía creer que se había inspeccionado el triple de lo real.
 */
export function clavePieza(r: RegistroPieza): string {
    return `${r.marca || r.folio}|${r.obra}|${r.consec}`;
}
