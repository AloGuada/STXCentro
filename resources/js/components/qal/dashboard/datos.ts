/**
 * Los números del tablero. Son datos FALSOS, a propósito.
 *
 * Las tablas de inspección (`qal_inspecciones`, `qal_inspeccion_defectos`,
 * `qal_muestreos`, `qal_pintura`…) todavía no existen, así que no hay de dónde
 * calcular nada: la pantalla se está construyendo contra la FORMA del tablero,
 * no contra la base. Cuando el backend exista, esto se sustituye por props del
 * controlador y el archivo desaparece.
 *
 * Lo que NO es relleno y hay que conservar tal cual son las **definiciones**:
 * qué mide cada número, sobre qué denominador, y cuándo se calla. Salen del
 * tablero de la aplicación anterior, donde ya estaban discutidas:
 *
 *  - El rechazo de gerencia se cuenta por PIEZA (piezas con al menos un rechazo
 *    ÷ piezas con veredicto), no por inspección: una pieza rechazada tres veces
 *    es una pieza mala, no tres.
 *  - El FPY se calcula sólo sobre la primera inspección (`ninsp = 1`). Si se
 *    mezclan las reinspecciones deja de significar «salió bien a la primera».
 *  - Fabricación (2ª) y pintura (3ª) se reportan por separado y no se promedian:
 *    hoy ni son las mismas piezas ni tienen el mismo nivel.
 *  - Una tasa normalizada (por elemento, por tonelada, por m²) **se calla por
 *    debajo del 30 % de cobertura**. Publicar «0.017 defectos/m²» con el 8 % de
 *    las piezas medidas es dar por medido lo que no se midió.
 *  - Las métricas de junta piden n ≥ 20. Un porcentaje sobre n = 1 no es un
 *    porcentaje, es una anécdota en letra grande.
 *  - El RTY (2ª→3ª) NO se publica: multiplicaba rendimientos de poblaciones
 *    distintas —las piezas de una etapa no son las de la otra— y el producto no
 *    significaba nada. Vuelve cuando se pueda seguir la MISMA pieza por sus
 *    etapas.
 */

export const OBRAS = [
    'AMPLIACION T4 CANCUN',
    'CANCUN PARKS II NAVE A',
    'TRES GUERRAS VILLA MAGNA',
    'STEELEX 2',
    'TOTEM PRIME CENTER',
    'PARKS NAVE J',
];

export const SOLDADORES = ['APC', 'JRM', 'LGT', 'MHV', 'RSC', 'TDL'];
export const INSPECTORES = ['E. Rivas', 'M. Solís', 'J. Cabrera'];
export const TIPOS_PIEZA = ['Trabe principal', 'Columna metálica', 'Larguero de cubierta', 'Contraviento', 'Ancla'];
export const SUBETAPAS = ['Armado-Vestido', 'Soldadura'];
export const SUBTIPOS_PRIMERA = ['Perfil', 'Placa'];

/** Semanas ISO del periodo que se está mirando. */
export const SEMANAS = ['2026-S28', '2026-S29', '2026-S30', '2026-S31', '2026-S32', '2026-S33'];

// ---------------------------------------------------------------------------
// Resumen ejecutivo
// ---------------------------------------------------------------------------

export type Kpi = {
    etiqueta: string;
    valor: string;
    nota?: string;
    /** Punto de la diferencia contra el periodo previo. Negativo = bajó. */
    delta?: number | null;
    /** Por defecto bajar es bueno (rechazo). En liberadas o FPY es al revés. */
    mejorSiBaja?: boolean;
    tono?: 'neutro' | 'malo' | 'bueno' | 'pendiente';
    /** Sufijo del delta: casi siempre «pp» (puntos porcentuales). */
    sufijo?: string;
};

export const KPIS_EJECUTIVO: Kpi[] = [
    {
        etiqueta: 'Piezas liberadas',
        valor: '1,284',
        nota: '1,102 pieza(s) distintas · 6 obra(s)',
        tono: 'neutro',
    },
    {
        etiqueta: 'Rechazo en fabricación (2ª)',
        valor: '18.4%',
        nota: '57 de 310 piezas con rechazo · 74 reprocesos',
        delta: -2.3,
        sufijo: 'pp',
        tono: 'malo',
    },
    {
        etiqueta: 'Rechazo en pintura (3ª)',
        valor: '9.1%',
        nota: '14 de 154 piezas con rechazo · 17 reprocesos',
        delta: 1.4,
        sufijo: 'pp',
        tono: 'malo',
    },
    {
        etiqueta: 'Pendientes',
        valor: '96',
        nota: 'piezas sin veredicto todavía',
        tono: 'pendiente',
    },
];

export type OrigenCausa = 'todas' | 'p2_deftypes' | 'armado' | 'p3_deftypes' | 'p1';

export const CAUSAS_RECHAZO: Record<OrigenCausa, { causa: string; n: number }[]> = {
    todas: [
        { causa: 'Socavado', n: 41 },
        { causa: 'Porosidad', n: 33 },
        { causa: 'Falta de vestido', n: 28 },
        { causa: 'Espesor bajo', n: 19 },
        { causa: 'Falta de fusión', n: 14 },
        { causa: 'Escurrimiento', n: 9 },
    ],
    p2_deftypes: [
        { causa: 'Socavado', n: 41 },
        { causa: 'Porosidad', n: 33 },
        { causa: 'Falta de fusión', n: 14 },
        { causa: 'Salpicadura', n: 8 },
        { causa: 'Cráter', n: 5 },
    ],
    armado: [
        { causa: 'Falta de vestido', n: 28 },
        { causa: 'Fuera de escuadra', n: 12 },
        { causa: 'Placa mal ubicada', n: 7 },
        { causa: 'Perforación faltante', n: 4 },
    ],
    p3_deftypes: [
        { causa: 'Espesor bajo', n: 19 },
        { causa: 'Escurrimiento', n: 9 },
        { causa: 'Piel de naranja', n: 6 },
        { causa: 'Contaminación', n: 3 },
    ],
    p1: [
        { causa: 'Fuera de tolerancia', n: 11 },
        { causa: 'Corte irregular', n: 6 },
        { causa: 'Marca ilegible', n: 2 },
    ],
};

/** Frases de una línea. No repiten las tarjetas: dicen qué mirar. */
export const HALLAZGOS = [
    'El rechazo de fabricación lleva tres semanas bajando (24.5 % → 18.4 %); el de pintura subió 1.4 pp en la última.',
    'El socavado y la porosidad concentran el 60 % de los defectos de soldadura: dos causas, no diez.',
    'STEELEX 2 tiene el 31 % de sus piezas rechazadas, más del doble que el resto de las obras.',
    'La falta de vestido aparece en 28 piezas y todas son de armado: no es un problema de soldadura.',
];

export const ALERTAS = [
    { texto: '2 lotes de accesorios rechazados llevan más de 5 días sin disposición.', tono: 'pendiente' as const },
    { texto: '96 piezas siguen sin veredicto; 31 tienen más de 2 semanas capturadas.', tono: 'pendiente' as const },
];

// ---------------------------------------------------------------------------
// Resumen analítica
// ---------------------------------------------------------------------------

export const KPIS_ANALITICA: Kpi[] = [
    { etiqueta: 'Inspecciones', valor: '1,431', nota: '1,284 piezas miradas · 1,284 representadas por muestreo', tono: 'neutro' },
    { etiqueta: 'Obras con inspecciones', valor: '6', nota: 'todas activas en el catálogo', tono: 'neutro' },
    { etiqueta: 'Inspecciones rechazadas', valor: '14.7%', nota: 'incluye re-inspecciones', tono: 'malo' },
    { etiqueta: 'FPY 2ª (fabricación)', valor: '81.6%', nota: 'pasó a la primera sin retoque · n=310 piezas-etapa', tono: 'bueno' },
    { etiqueta: 'FPY 3ª (pintura)', valor: '90.9%', nota: 'pasó a la primera sin retoque · n=154 piezas-etapa', tono: 'bueno' },
    { etiqueta: 'Rechazo final 2ª', valor: '3.2%', nota: 'quedó rechazada al cierre · n=310', tono: 'malo' },
    { etiqueta: 'Rechazo final 3ª', valor: '1.3%', nota: 'quedó rechazada al cierre · n=154', tono: 'malo' },
    { etiqueta: 'Piezas en ambas etapas', valor: '148', nota: 'fabricadas Y pintadas · el resto solo ha pasado por una', tono: 'neutro' },
    { etiqueta: 'Defectos/elemento', valor: '0.184', nota: '1ª inspección · cobertura 71% (220 de 310 piezas)', tono: 'neutro' },
    { etiqueta: 'Defectos/tonelada', valor: '0.42', nota: '1ª inspección · cobertura 64% (198 de 310 piezas)', tono: 'neutro' },
    { etiqueta: 'FPY soldadura', valor: '87.3%', nota: 'mapeo de juntas · n=284', tono: 'bueno' },
];

/**
 * Lo que se calla también se dice, una sola vez y en pequeño: la tasa por m² se
 * omite arriba porque su cobertura no llega al 30 %.
 */
export const TASAS_SIN_BASE = [{ nombre: 'por m²', cobertura: 8 }];

// ---------------------------------------------------------------------------
// Operación
// ---------------------------------------------------------------------------

/** Resultado final por obra. Rescatado del resumen ejecutivo del tablero anterior. */
export const RESULTADO_POR_OBRA = [
    { obra: 'AMPLIACION T4 CANCUN', liberadas: 312, rechazadas: 24, pendientes: 18 },
    { obra: 'CANCUN PARKS II NAVE A', liberadas: 268, rechazadas: 31, pendientes: 22 },
    { obra: 'TRES GUERRAS VILLA MAGNA', liberadas: 214, rechazadas: 12, pendientes: 9 },
    { obra: 'STEELEX 2', liberadas: 141, rechazadas: 63, pendientes: 27 },
    { obra: 'TOTEM PRIME CENTER', liberadas: 198, rechazadas: 15, pendientes: 12 },
    { obra: 'PARKS NAVE J', liberadas: 151, rechazadas: 9, pendientes: 8 },
];

export const RECHAZO_POR_FASE = [
    { fase: '1ª · Corte', pct: 6.3, n: 142 },
    { fase: '2ª · Armado y soldado', pct: 18.4, n: 310 },
    { fase: '3ª · Pintura', pct: 9.1, n: 154 },
];

export const TENDENCIA_REPROCESO = {
    week: [
        { periodo: '2026-S28', pct: 14.2 },
        { periodo: '2026-S29', pct: 13.1 },
        { periodo: '2026-S30', pct: 16.8 },
        { periodo: '2026-S31', pct: 12.4 },
        { periodo: '2026-S32', pct: 11.9 },
        { periodo: '2026-S33', pct: 10.6 },
    ],
    month: [
        { periodo: '2026-05', pct: 17.4 },
        { periodo: '2026-06', pct: 15.1 },
        { periodo: '2026-07', pct: 13.8 },
        { periodo: '2026-08', pct: 11.2 },
    ],
};

export type DimensionRechazo = 'obra' | 'soldador' | 'p2_subetapa' | 'tipo' | 'modulo' | 'inspector' | 'p1_subtipo';

export const RECHAZO_POR_DIMENSION: Record<DimensionRechazo, { nombre: string; pct: number; n: number }[]> = {
    obra: [
        { nombre: 'STEELEX 2', pct: 30.9, n: 204 },
        { nombre: 'CANCUN PARKS II NAVE A', pct: 10.4, n: 299 },
        { nombre: 'AMPLIACION T4 CANCUN', pct: 7.1, n: 336 },
        { nombre: 'TOTEM PRIME CENTER', pct: 7.0, n: 213 },
        { nombre: 'TRES GUERRAS VILLA MAGNA', pct: 5.3, n: 226 },
        { nombre: 'PARKS NAVE J', pct: 5.6, n: 160 },
    ],
    soldador: [
        { nombre: 'MHV', pct: 24.8, n: 121 },
        { nombre: 'RSC', pct: 19.2, n: 98 },
        { nombre: 'APC', pct: 14.1, n: 156 },
        { nombre: 'JRM', pct: 11.7, n: 137 },
        { nombre: 'LGT', pct: 9.4, n: 106 },
        { nombre: 'TDL', pct: 8.1, n: 74 },
    ],
    p2_subetapa: [
        { nombre: 'Armado-Vestido', pct: 21.6, n: 162 },
        { nombre: 'Soldadura', pct: 15.0, n: 148 },
    ],
    tipo: [
        { nombre: 'Contraviento', pct: 22.4, n: 67 },
        { nombre: 'Trabe principal', pct: 17.9, n: 112 },
        { nombre: 'Columna metálica', pct: 12.6, n: 95 },
        { nombre: 'Larguero de cubierta', pct: 8.8, n: 148 },
        { nombre: 'Ancla', pct: 4.2, n: 71 },
    ],
    modulo: [
        { nombre: 'Módulo 4', pct: 26.1, n: 88 },
        { nombre: 'Módulo 2', pct: 15.4, n: 117 },
        { nombre: 'Módulo 1', pct: 12.2, n: 131 },
        { nombre: 'Módulo 3', pct: 9.7, n: 103 },
    ],
    inspector: [
        { nombre: 'J. Cabrera', pct: 17.8, n: 191 },
        { nombre: 'E. Rivas', pct: 14.2, n: 246 },
        { nombre: 'M. Solís', pct: 11.9, n: 168 },
    ],
    p1_subtipo: [
        { nombre: 'Placa', pct: 8.4, n: 83 },
        { nombre: 'Perfil', pct: 4.6, n: 59 },
    ],
};

export type OrigenPareto = 'p2_deftypes' | 'armado' | 'p3_deftypes';

/** El Pareto va SIEMPRE acotado: un ranking que promedia obras y fases no dice dónde actuar. */
export const PARETO: Record<OrigenPareto, { causa: string; n: number }[]> = {
    p2_deftypes: CAUSAS_RECHAZO.p2_deftypes,
    armado: CAUSAS_RECHAZO.armado,
    p3_deftypes: CAUSAS_RECHAZO.p3_deftypes,
};

export type BaseTasa = 'elem' | 'ton' | 'm2';
export type ModoTasa = 'first' | 'all';
export type DimensionTasa = 'obra' | 'soldador' | 'tipo' | 'modulo' | 'inspector';

export const ETIQUETA_BASE: Record<BaseTasa, string> = {
    elem: 'defectos por elemento (2ª)',
    ton: 'defectos por tonelada (2ª)',
    m2: 'defectos por m² (3ª)',
};

/** Cobertura de cada base. Por debajo de 30 % la tasa no se publica. */
export const COBERTURA_BASE: Record<BaseTasa, { pct: number; con: number; total: number }> = {
    elem: { pct: 71, con: 220, total: 310 },
    ton: { pct: 64, con: 198, total: 310 },
    m2: { pct: 8, con: 12, total: 154 },
};

export const TASA_DEFECTOS: Record<BaseTasa, Record<DimensionTasa, { nombre: string; tasa: number }[]>> = {
    elem: {
        obra: [
            { nombre: 'STEELEX 2', tasa: 0.412 },
            { nombre: 'CANCUN PARKS II NAVE A', tasa: 0.198 },
            { nombre: 'AMPLIACION T4 CANCUN', tasa: 0.141 },
            { nombre: 'TOTEM PRIME CENTER', tasa: 0.132 },
            { nombre: 'TRES GUERRAS VILLA MAGNA', tasa: 0.104 },
            { nombre: 'PARKS NAVE J', tasa: 0.098 },
        ],
        soldador: [
            { nombre: 'MHV', tasa: 0.386 },
            { nombre: 'RSC', tasa: 0.271 },
            { nombre: 'APC', tasa: 0.164 },
            { nombre: 'JRM', tasa: 0.142 },
            { nombre: 'LGT', tasa: 0.117 },
            { nombre: 'TDL', tasa: 0.091 },
        ],
        tipo: [
            { nombre: 'Contraviento', tasa: 0.344 },
            { nombre: 'Trabe principal', tasa: 0.229 },
            { nombre: 'Columna metálica', tasa: 0.158 },
            { nombre: 'Larguero de cubierta', tasa: 0.106 },
            { nombre: 'Ancla', tasa: 0.052 },
        ],
        modulo: [
            { nombre: 'Módulo 4', tasa: 0.398 },
            { nombre: 'Módulo 2', tasa: 0.187 },
            { nombre: 'Módulo 1', tasa: 0.149 },
            { nombre: 'Módulo 3', tasa: 0.121 },
        ],
        inspector: [
            { nombre: 'J. Cabrera', tasa: 0.221 },
            { nombre: 'E. Rivas', tasa: 0.176 },
            { nombre: 'M. Solís', tasa: 0.148 },
        ],
    },
    ton: {
        obra: [
            { nombre: 'STEELEX 2', tasa: 0.94 },
            { nombre: 'CANCUN PARKS II NAVE A', tasa: 0.47 },
            { nombre: 'AMPLIACION T4 CANCUN', tasa: 0.33 },
            { nombre: 'TOTEM PRIME CENTER', tasa: 0.31 },
            { nombre: 'TRES GUERRAS VILLA MAGNA', tasa: 0.24 },
            { nombre: 'PARKS NAVE J', tasa: 0.22 },
        ],
        soldador: [
            { nombre: 'MHV', tasa: 0.88 },
            { nombre: 'RSC', tasa: 0.62 },
            { nombre: 'APC', tasa: 0.38 },
            { nombre: 'JRM', tasa: 0.33 },
            { nombre: 'LGT', tasa: 0.27 },
            { nombre: 'TDL', tasa: 0.21 },
        ],
        tipo: [
            { nombre: 'Contraviento', tasa: 0.79 },
            { nombre: 'Trabe principal', tasa: 0.52 },
            { nombre: 'Columna metálica', tasa: 0.36 },
            { nombre: 'Larguero de cubierta', tasa: 0.24 },
            { nombre: 'Ancla', tasa: 0.12 },
        ],
        modulo: [
            { nombre: 'Módulo 4', tasa: 0.91 },
            { nombre: 'Módulo 2', tasa: 0.43 },
            { nombre: 'Módulo 1', tasa: 0.34 },
            { nombre: 'Módulo 3', tasa: 0.28 },
        ],
        inspector: [
            { nombre: 'J. Cabrera', tasa: 0.51 },
            { nombre: 'E. Rivas', tasa: 0.40 },
            { nombre: 'M. Solís', tasa: 0.34 },
        ],
    },
    // Sin cobertura suficiente: la pantalla no la pinta, avisa por qué.
    m2: { obra: [], soldador: [], tipo: [], modulo: [], inspector: [] },
};

// ---------------------------------------------------------------------------
// ESTADÍSTICA · ¿es real?
// ---------------------------------------------------------------------------

/**
 * Carta-p de control estadístico.
 *
 * Los límites se recalculan con los datos filtrados y **cambian de punto a
 * punto** porque el tamaño de subgrupo —las piezas inspeccionadas esa semana—
 * no es constante: una semana de 12 piezas tolera más variación que una de 90.
 * Un límite recto sobre subgrupos desiguales es lo que hace que una semana
 * floja parezca fuera de control.
 */
export const CARTA_P = {
    /** Menos de 20 subgrupos: los límites ya sirven para vigilar, pero se moverán. */
    preliminar: true,
    subgrupos: 6,
    pbar: 18.7,
    puntos: [
        { semana: 'S28', p: 22.8, ucl: 33.1, lcl: 4.3, n: 58 },
        { semana: 'S29', p: 21.1, ucl: 30.6, lcl: 6.8, n: 84 },
        { semana: 'S30', p: 24.5, ucl: 34.7, lcl: 2.7, n: 51 },
        { semana: 'S31', p: 19.7, ucl: 29.8, lcl: 7.6, n: 96 },
        { semana: 'S32', p: 20.7, ucl: 31.2, lcl: 6.2, n: 77 },
        { semana: 'S33', p: 18.4, ucl: 30.1, lcl: 7.3, n: 88 },
    ],
};

/**
 * Capacidad de proceso del espesor de pintura, **por espesor requerido**.
 *
 * No hay un Cpk global posible: cada proyecto pinta con un sistema distinto
 * —16 mils en T4, 3 en Tres Guerras— y mezclarlos da un número que no describe
 * a ninguno. Sólo existe límite inferior, el mínimo del proyecto, así que
 * Cpk = (media − mínimo) ÷ 3σ.
 */
export type GrupoCapacidad = {
    requerido: number;
    obras: string[];
    n: number;
    media: number;
    sigma: number;
    /** `null` = no hay muestra suficiente, o no hay variación entre piezas. */
    cpk: number | null;
    fuera: number;
};

export const CAPACIDAD: GrupoCapacidad[] = [
    { requerido: 16, obras: ['AMPLIACION T4 CANCUN'], n: 28, media: 19.4, sigma: 1.42, cpk: 0.8, fuera: 2 },
    { requerido: 12, obras: ['CANCUN PARKS II NAVE A'], n: 14, media: 15.1, sigma: 0.71, cpk: 1.46, fuera: 0 },
    { requerido: 3, obras: ['TRES GUERRAS VILLA MAGNA'], n: 4, media: 3.9, sigma: 0.33, cpk: null, fuera: 0 },
];

/** Cuántas piezas de pintura tienen el espesor capturado. */
export const COBERTURA_ESPESOR = { con: 46, total: 154 };

/** Piezas mínimas para publicar un Cpk. Por debajo, la σ es la de la anécdota. */
export const MIN_PIEZAS_CPK = 5;

/**
 * Margen sobre el espesor requerido, en % del mínimo de cada proyecto.
 *
 * El histograma del espesor en mils salía con dos montañas —una en 3 y otra en
 * 17— que parecían dos procesos rotos. No lo son: son dos sistemas de pintura
 * distintos. Lo comparable es cuánto se pasa cada pieza de **su** mínimo. El
 * cero es el mínimo del proyecto; a la izquierda del cero, pieza fuera de norma.
 */
export const MARGEN_ESPESOR = {
    piezas: 46,
    piezasPintura: 154,
    medianaPct: 21,
    medianaMils: 2.6,
    minPct: -11,
    maxPct: 58,
    bajoMinimo: 3,
    bins: [
        { desde: -11, n: 3 },
        { desde: -1, n: 4 },
        { desde: 9, n: 11 },
        { desde: 19, n: 14 },
        { desde: 29, n: 8 },
        { desde: 38, n: 4 },
        { desde: 48, n: 2 },
    ],
};

/**
 * Estadística descriptiva.
 *
 * No habla de calidad: describe **cómo es la pieza típica** y **cuánto se
 * parecen entre sí**. Sirve para saber si un número raro de otro panel es
 * normal o es un error de captura, y para poner en contexto las tasas
 * normalizadas, que salen de estas mismas variables.
 *
 * `conDato` cuenta **piezas distintas**, no registros: una pieza reinspeccionada
 * tres veces vale una, con su última medición.
 */
export type VariableDescriptiva = {
    variable: string;
    ayuda: string;
    unidad: string;
    decimales: number;
    conDato: number;
    universo: number;
    media: number;
    mediana: number;
    sigma: number;
    /** Coeficiente de variación, en %. Es la σ hecha comparable entre unidades. */
    cv: number;
    min: number;
    max: number;
    /** Valores imposibles descartados por los topes del tablero. */
    descartados?: number;
};

export const DESCRIPTIVA: VariableDescriptiva[] = [
    {
        variable: 'Peso de la pieza',
        ayuda: 'Lo que pesa cada pieza. Es la base de «defectos por tonelada» y de los kilos liberados.',
        unidad: 'kg',
        decimales: 0,
        conDato: 402,
        universo: 464,
        media: 1124,
        mediana: 820,
        sigma: 1776,
        cv: 158,
        min: 3,
        max: 9497,
        descartados: 1,
    },
    {
        variable: 'Elementos por pieza',
        ayuda: 'Cuántos elementos lleva armada una pieza: mide lo complicada que es de fabricar.',
        unidad: '',
        decimales: 1,
        conDato: 188,
        universo: 310,
        media: 6.4,
        mediana: 5,
        sigma: 4.1,
        cv: 64,
        min: 1,
        max: 24,
    },
    {
        variable: 'Defectos de soldadura por pieza',
        ayuda: 'Defectos encontrados en cada pieza soldada, contando también las que salieron con cero.',
        unidad: '',
        decimales: 1,
        conDato: 246,
        universo: 310,
        media: 0.6,
        mediana: 0,
        sigma: 1.1,
        cv: 183,
        min: 0,
        max: 7,
    },
    {
        variable: 'Espesor de pintura',
        ayuda: 'Película seca medida. Mezcla proyectos con mínimos distintos: para comparar, mira el margen.',
        unidad: 'mils',
        decimales: 1,
        conDato: 46,
        universo: 154,
        media: 16.8,
        mediana: 17.4,
        sigma: 4.5,
        cv: 27,
        min: 2.8,
        max: 24.1,
    },
    {
        variable: 'Margen sobre el mínimo',
        ayuda: 'Cuánto se pasa cada pieza del espesor que le pide su proyecto. Negativo = fuera de norma.',
        unidad: '%',
        decimales: 0,
        conDato: 46,
        universo: 154,
        media: 22,
        mediana: 21,
        sigma: 15,
        cv: 68,
        min: -11,
        max: 58,
    },
    {
        variable: 'Área pintada',
        ayuda: 'Superficie de cada pieza. Es el denominador de «defectos por m²», hoy bloqueado por falta de dato.',
        unidad: 'm²',
        decimales: 1,
        conDato: 12,
        universo: 154,
        media: 31.4,
        mediana: 26.8,
        sigma: 18.2,
        cv: 58,
        min: 4.1,
        max: 71.3,
    },
];

/** Los factores contra los que se puede probar el rechazo. */
export type FactorPrueba = 'tipo' | 'p2_subetapa' | 'soldador' | 'obra' | 'inspector';
export type EtapaPrueba = '2ª' | '3ª';

export const ETIQUETA_FACTOR: Record<FactorPrueba, string> = {
    tipo: 'tipo de pieza',
    p2_subetapa: 'sub-etapa',
    soldador: 'soldador',
    obra: 'obra',
    inspector: 'inspector',
};

/**
 * Prueba de chi-cuadrado: ¿el rechazo depende de este factor?
 *
 * Aquí una pieza cuenta como rechazada si su **veredicto final** lo es. En
 * «Factores de riesgo» cuenta si fue rechazada **alguna vez**, que sale más
 * alto porque incluye lo ya corregido. Son dos preguntas distintas: qué queda
 * mal al cierre, y cuánto trabajo hubo que rehacer.
 *
 * Se compara **dentro de una etapa**. Mezclarlas hace que cualquier factor
 * repartido de forma desigual entre 2ª y pintura salga «significativo» sin
 * serlo, porque las dos tienen tasas de rechazo muy distintas.
 */
export type PruebaChi = {
    chi2: number;
    gl: number;
    p: number;
    /** V de Cramér: la fuerza de la relación, no si existe. */
    v: number;
    piezas: number;
    /** Tasa de rechazo del conjunto, en %. */
    base: number;
    /** Categorías con menos de 5 piezas, dejadas fuera para no distorsionar. */
    fuera: number;
    /** Celdas que esperaban menos de 5 casos: la prueba queda como indicio. */
    celdasBajas: number;
    detalle: { categoria: string; n: number; rechazadas: number; pct: number; esperadas: number; z: number }[];
};

export const PRUEBAS_DEPENDENCIA: Record<EtapaPrueba, Partial<Record<FactorPrueba, PruebaChi>>> = {
    '2ª': {
        tipo: {
            chi2: 18.42,
            gl: 3,
            p: 0.0004,
            v: 0.31,
            piezas: 288,
            base: 12.5,
            fuera: 1,
            celdasBajas: 0,
            detalle: [
                { categoria: 'Contraviento', n: 41, rechazadas: 11, pct: 26.8, esperadas: 5.1, z: 2.8 },
                { categoria: 'Trabe principal', n: 118, rechazadas: 18, pct: 15.3, esperadas: 14.8, z: 0.9 },
                { categoria: 'Columna metálica', n: 87, rechazadas: 7, pct: 8, esperadas: 10.9, z: -1.3 },
                { categoria: 'Larguero de cubierta', n: 42, rechazadas: 0, pct: 0, esperadas: 5.3, z: -2.4 },
            ],
        },
        p2_subetapa: {
            chi2: 44.1,
            gl: 1,
            p: 0.00001,
            v: 0.52,
            piezas: 288,
            base: 12.5,
            fuera: 0,
            celdasBajas: 0,
            detalle: [
                { categoria: 'Armado-Vestido', n: 96, rechazadas: 26, pct: 27.1, esperadas: 12, z: 4.5 },
                { categoria: 'Soldado', n: 192, rechazadas: 10, pct: 5.2, esperadas: 24, z: -3.6 },
            ],
        },
        soldador: {
            chi2: 7.94,
            gl: 4,
            p: 0.094,
            v: 0.17,
            piezas: 214,
            base: 11.2,
            fuera: 1,
            celdasBajas: 2,
            detalle: [
                { categoria: 'MHV', n: 38, rechazadas: 8, pct: 21.1, esperadas: 4.3, z: 1.9 },
                { categoria: 'RSC', n: 44, rechazadas: 6, pct: 13.6, esperadas: 4.9, z: 0.5 },
                { categoria: 'APC', n: 51, rechazadas: 5, pct: 9.8, esperadas: 5.7, z: -0.3 },
                { categoria: 'JRM', n: 47, rechazadas: 4, pct: 8.5, esperadas: 5.3, z: -0.6 },
                { categoria: 'LGT', n: 34, rechazadas: 1, pct: 2.9, esperadas: 3.8, z: -1.5 },
            ],
        },
        obra: {
            chi2: 11.06,
            gl: 3,
            p: 0.011,
            v: 0.2,
            piezas: 288,
            base: 12.5,
            fuera: 2,
            celdasBajas: 1,
            detalle: [
                { categoria: 'TRES GUERRAS VILLA MAGNA', n: 46, rechazadas: 11, pct: 23.9, esperadas: 5.8, z: 2.3 },
                { categoria: 'CANCUN PARKS II NAVE A', n: 78, rechazadas: 12, pct: 15.4, esperadas: 9.8, z: 0.8 },
                { categoria: 'AMPLIACION T4 CANCUN', n: 131, rechazadas: 12, pct: 9.2, esperadas: 16.4, z: -1.3 },
                { categoria: 'TOTEM PRIME CENTER', n: 33, rechazadas: 1, pct: 3, esperadas: 4.1, z: -1.6 },
            ],
        },
        inspector: {
            chi2: 2.31,
            gl: 2,
            p: 0.315,
            v: 0.09,
            piezas: 288,
            base: 12.5,
            fuera: 0,
            celdasBajas: 0,
            detalle: [
                { categoria: 'J. Cabrera', n: 94, rechazadas: 15, pct: 16, esperadas: 11.8, z: 1.1 },
                { categoria: 'E. Rivas', n: 121, rechazadas: 14, pct: 11.6, esperadas: 15.1, z: -0.3 },
                { categoria: 'M. Solís', n: 73, rechazadas: 7, pct: 9.6, esperadas: 9.1, z: -0.8 },
            ],
        },
    },
    '3ª': {
        obra: {
            chi2: 1.44,
            gl: 2,
            p: 0.487,
            v: 0.1,
            piezas: 142,
            base: 4.9,
            fuera: 1,
            celdasBajas: 2,
            detalle: [
                { categoria: 'CANCUN PARKS II NAVE A', n: 44, rechazadas: 3, pct: 6.8, esperadas: 2.2, z: 0.6 },
                { categoria: 'AMPLIACION T4 CANCUN', n: 76, rechazadas: 4, pct: 5.3, esperadas: 3.7, z: 0.2 },
                { categoria: 'TRES GUERRAS VILLA MAGNA', n: 22, rechazadas: 0, pct: 0, esperadas: 1.1, z: -1.1 },
            ],
        },
        tipo: {
            chi2: 0.92,
            gl: 2,
            p: 0.631,
            v: 0.08,
            piezas: 142,
            base: 4.9,
            fuera: 2,
            celdasBajas: 3,
            detalle: [
                { categoria: 'Trabe principal', n: 68, rechazadas: 4, pct: 5.9, esperadas: 3.3, z: 0.4 },
                { categoria: 'Columna metálica', n: 52, rechazadas: 3, pct: 5.8, esperadas: 2.5, z: 0.3 },
                { categoria: 'Contraviento', n: 22, rechazadas: 0, pct: 0, esperadas: 1.1, z: -1.1 },
            ],
        },
        inspector: {
            chi2: 0.41,
            gl: 1,
            p: 0.522,
            v: 0.05,
            piezas: 142,
            base: 4.9,
            fuera: 1,
            celdasBajas: 2,
            detalle: [
                { categoria: 'M. Solís', n: 88, rechazadas: 5, pct: 5.7, esperadas: 4.3, z: 0.4 },
                { categoria: 'E. Rivas', n: 54, rechazadas: 2, pct: 3.7, esperadas: 2.7, z: -0.5 },
            ],
        },
    },
};

/**
 * Prueba de tendencia: ¿el rechazo sube o baja de forma sostenida?
 *
 * Mira la serie entera semana a semana en vez de comparar dos semanas sueltas,
 * que es como se fabrican las buenas noticias.
 */
export type Tendencia = {
    z: number;
    p: number;
    semanas: number;
    puntos: { semana: string; pct: number; rechazadas: number; n: number }[];
};

export const TENDENCIA_RECHAZO: Record<EtapaPrueba, Tendencia> = {
    '2ª': {
        z: -2.31,
        p: 0.021,
        semanas: 6,
        puntos: [
            { semana: 'S28', pct: 22.8, rechazadas: 13, n: 57 },
            { semana: 'S29', pct: 21.1, rechazadas: 18, n: 85 },
            { semana: 'S30', pct: 24.5, rechazadas: 12, n: 49 },
            { semana: 'S31', pct: 19.7, rechazadas: 19, n: 96 },
            { semana: 'S32', pct: 20.7, rechazadas: 16, n: 77 },
            { semana: 'S33', pct: 18.4, rechazadas: 16, n: 87 },
        ],
    },
    '3ª': {
        z: 1.12,
        p: 0.263,
        semanas: 6,
        puntos: [
            { semana: 'S28', pct: 6.2, rechazadas: 2, n: 32 },
            { semana: 'S29', pct: 7.8, rechazadas: 3, n: 38 },
            { semana: 'S30', pct: 7.1, rechazadas: 2, n: 28 },
            { semana: 'S31', pct: 8.4, rechazadas: 4, n: 48 },
            { semana: 'S32', pct: 7.7, rechazadas: 3, n: 39 },
            { semana: 'S33', pct: 9.1, rechazadas: 4, n: 44 },
        ],
    },
};

// ---------------------------------------------------------------------------
// DIAGNÓSTICO · ¿el dato sirve?
// ---------------------------------------------------------------------------

/**
 * Uso de los campos del formulario.
 *
 * Si se le pide al inspector responder algo en cada pieza, o sirve o sobra.
 * Dos denominadores distintos y **no intercambiables**:
 *
 *  - `% respondido` = contestadas ÷ piezas de la etapa. Un «no aplica» ES una
 *    respuesta: el inspector miró y dijo que ahí no tocaba.
 *  - `% con defecto` = con defecto ÷ piezas donde el campo SÍ aplicaba. Meter
 *    los «no aplica» en el denominador diluye la señal.
 *
 * Y una regla que se comprobó en la base: **una casilla en blanco no cuenta
 * como OK**. Las piezas rechazadas dejan más casillas vacías que las liberadas;
 * el blanco es «no se llenó», no «lo vi y estaba bien».
 */
export type CampoFormulario = {
    fase: '1ª' | '2ª' | '3ª';
    bloque: string;
    campo: string;
    /** Piezas de esa etapa. */
    n: number;
    ok: number;
    defecto: number;
    noAplica: number;
    vacio: number;
};

export const USO_CAMPOS: CampoFormulario[] = [
    { fase: '2ª', bloque: 'Preparación de junta', campo: 'Ángulo de bisel', n: 310, ok: 214, defecto: 9, noAplica: 41, vacio: 46 },
    { fase: '2ª', bloque: 'Preparación de junta', campo: 'Separación de raíz', n: 310, ok: 198, defecto: 14, noAplica: 38, vacio: 60 },
    { fase: '2ª', bloque: 'Preparación de junta', campo: 'Acceso de soldadura', n: 310, ok: 13, defecto: 1, noAplica: 232, vacio: 64 },
    { fase: '2ª', bloque: 'Preparación de junta', campo: 'Placa de respaldo', n: 310, ok: 0, defecto: 0, noAplica: 0, vacio: 310 },
    { fase: '2ª', bloque: 'Armado y vestido', campo: 'Longitud total', n: 310, ok: 241, defecto: 22, noAplica: 12, vacio: 35 },
    { fase: '2ª', bloque: 'Armado y vestido', campo: 'Distancia entre placas', n: 310, ok: 187, defecto: 31, noAplica: 24, vacio: 68 },
    { fase: '2ª', bloque: 'Armado y vestido', campo: 'Giro o caída de placas', n: 310, ok: 203, defecto: 6, noAplica: 19, vacio: 82 },
    { fase: '2ª', bloque: 'Proceso de soldadura', campo: 'Precalentamiento', n: 310, ok: 96, defecto: 2, noAplica: 51, vacio: 161 },
    { fase: '2ª', bloque: 'Proceso de soldadura', campo: 'Limpieza entre pasadas', n: 310, ok: 172, defecto: 18, noAplica: 14, vacio: 106 },
    { fase: '2ª', bloque: 'Acabado e identificación', campo: 'Etiqueta', n: 310, ok: 268, defecto: 3, noAplica: 6, vacio: 33 },
    { fase: '2ª', bloque: 'Acabado e identificación', campo: 'Limpieza mecánica', n: 310, ok: 244, defecto: 11, noAplica: 8, vacio: 47 },
    { fase: '3ª', bloque: 'Mediciones', campo: 'Prueba de espesor', n: 154, ok: 41, defecto: 5, noAplica: 3, vacio: 105 },
    { fase: '3ª', bloque: 'Mediciones', campo: 'Método', n: 154, ok: 46, defecto: 0, noAplica: 2, vacio: 106 },
    { fase: '3ª', bloque: 'Inspección visual', campo: 'Revisión', n: 154, ok: 129, defecto: 9, noAplica: 4, vacio: 12 },
    { fase: '3ª', bloque: 'Inspección visual', campo: 'Acción', n: 154, ok: 22, defecto: 0, noAplica: 96, vacio: 36 },
    { fase: '3ª', bloque: 'Adherencia', campo: 'Adherencia', n: 154, ok: 18, defecto: 1, noAplica: 8, vacio: 127 },
    { fase: '1ª', bloque: 'Dimensional', campo: 'Longitud', n: 6, ok: 5, defecto: 1, noAplica: 0, vacio: 0 },
    { fase: '1ª', bloque: 'Corte y bisel', campo: 'Defectos de corte', n: 6, ok: 4, defecto: 2, noAplica: 0, vacio: 0 },
];

/** Por debajo de estas piezas no se puede decir nada de un campo. */
export const MIN_PIEZAS_CAMPO = 10;

/**
 * Mapa de riesgo: frecuencia contra impacto, defecto por defecto.
 *
 * Dos decisiones que se conservan del rediseño:
 *
 *  - **Los cortes son absolutos**, no relativos al defecto más frecuente. Con
 *    frecuencias parecidas —60, 50, 50, 43— los cinco salían «Alta» porque el
 *    primero definía la escala, y el eje cambiaba de significado cada semana.
 *    Ahora: baja por debajo del 10 % del total, media hasta el 25 %, alta arriba.
 *  - **Si la magnitud no llega al 50 % de cobertura, el eje de impacto se cae a
 *    piezas.** El área de pintura está capturada en 12 de 154 piezas; dibujar
 *    «impacto 0 m²» se lee como «no impacta» cuando lo que pasa es que no hay dato.
 */
export type ItemRiesgo = {
    defecto: string;
    defectos: number;
    /** % del total de defectos de la etapa. */
    frecuencia: number;
    piezas: number;
    magnitud: number;
    /** % de la magnitud (o de las piezas) con defecto que lleva este defecto. */
    impacto: number;
};

export type MapaRiesgo = {
    etapa: string;
    nota: string;
    totalDefectos: number;
    tiposDefecto: number;
    piezasEtapa: number;
    /** `false` = el eje de impacto se mide en piezas por falta de dato. */
    usaMagnitud: boolean;
    unidadMagnitud: string;
    magnitudLarga: string;
    coberturaMagnitud: number;
    items: ItemRiesgo[];
};

export const MAPAS_RIESGO: MapaRiesgo[] = [
    {
        etapa: '2ª transformación',
        nota: 'soldadura, armado y vestido',
        totalDefectos: 148,
        tiposDefecto: 9,
        piezasEtapa: 310,
        usaMagnitud: true,
        unidadMagnitud: 'kg',
        magnitudLarga: 'kilos de pieza afectados',
        coberturaMagnitud: 87,
        items: [
            { defecto: 'Socavado', defectos: 41, frecuencia: 27.7, piezas: 34, magnitud: 48200, impacto: 31 },
            { defecto: 'Porosidad', defectos: 33, frecuencia: 22.3, piezas: 29, magnitud: 31600, impacto: 20 },
            { defecto: 'Falta de vestido', defectos: 28, frecuencia: 18.9, piezas: 26, magnitud: 52900, impacto: 34 },
            { defecto: 'Falta de fusión', defectos: 14, frecuencia: 9.5, piezas: 12, magnitud: 18400, impacto: 12 },
            { defecto: 'Fuera de escuadra', defectos: 12, frecuencia: 8.1, piezas: 11, magnitud: 9100, impacto: 6 },
        ],
    },
    {
        etapa: 'pintura',
        nota: '3ª · histórico',
        totalDefectos: 37,
        tiposDefecto: 4,
        piezasEtapa: 154,
        usaMagnitud: false,
        unidadMagnitud: 'm²',
        magnitudLarga: 'm² pintados',
        coberturaMagnitud: 8,
        items: [
            { defecto: 'Espesor bajo', defectos: 19, frecuencia: 51.4, piezas: 17, magnitud: 0, impacto: 11 },
            { defecto: 'Escurrimiento', defectos: 9, frecuencia: 24.3, piezas: 8, magnitud: 0, impacto: 5 },
            { defecto: 'Piel de naranja', defectos: 6, frecuencia: 16.2, piezas: 6, magnitud: 0, impacto: 4 },
            { defecto: 'Contaminación', defectos: 3, frecuencia: 8.1, piezas: 3, magnitud: 0, impacto: 2 },
        ],
    },
];

/**
 * Factores de riesgo: qué categoría concreta se desvía del promedio **de su
 * propia transformación**.
 *
 * El chi² dice si un factor influye; esto dice cuál es el caso y por dónde
 * empezar. Aquí una pieza cuenta como rechazada si lo fue **alguna vez** —la
 * misma definición que la portada—, por eso los porcentajes salen más altos.
 *
 * `evidencia` no es el p a secas: como se miran muchas categorías a la vez,
 * alguna saldría alta por casualidad. **Confirmado** exige el listón corregido
 * (0,05 ÷ número de comparaciones) e **indicio** es el p < 0,05 de siempre.
 * «Puede ser azar» no significa que esté bien: significa que todavía no se puede
 * afirmar.
 *
 * Y lo más importante: **asociación, no causa**. Nadie ha controlado qué piezas
 * le tocaron a cada quien, y las difíciles no se reparten al azar.
 */
export type FactorRiesgo = {
    fase: string;
    factor: string;
    valor: string;
    piezas: number;
    rechazadas: number;
    /** % de rechazo de la categoría. */
    tasa: number;
    /** % de rechazo de su etapa. */
    base: number;
    /** Riesgo relativo: cuántas veces la tasa de su etapa. */
    rr: number;
    p: number;
    evidencia: 'confirmado' | 'indicio' | 'nada';
};

export const FACTORES_RIESGO: FactorRiesgo[] = [
    { fase: '2ª', factor: 'Sub-etapa', valor: 'Armado-Vestido', piezas: 96, rechazadas: 66, tasa: 68.8, base: 28.7, rr: 2.4, p: 0.0000001, evidencia: 'confirmado' },
    { fase: '2ª', factor: 'Tipo de pieza', valor: 'Contraviento', piezas: 41, rechazadas: 23, tasa: 56.1, base: 28.7, rr: 2, p: 0.0002, evidencia: 'confirmado' },
    { fase: '2ª', factor: 'Módulo', valor: 'Módulo 4', piezas: 28, rechazadas: 14, tasa: 50, base: 28.7, rr: 1.7, p: 0.014, evidencia: 'indicio' },
    { fase: '2ª', factor: 'Soldador', valor: 'MHV', piezas: 38, rechazadas: 17, tasa: 44.7, base: 28.7, rr: 1.6, p: 0.031, evidencia: 'indicio' },
    { fase: '3ª', factor: 'Obra', valor: 'CANCUN PARKS II NAVE A', piezas: 44, rechazadas: 9, tasa: 20.5, base: 11.7, rr: 1.8, p: 0.072, evidencia: 'nada' },
    { fase: '2ª', factor: 'Tipo de pieza', valor: 'Larguero de cubierta', piezas: 42, rechazadas: 6, tasa: 14.3, base: 28.7, rr: 0.5, p: 0.028, evidencia: 'indicio' },
    { fase: '2ª', factor: 'Soldador', valor: 'LGT', piezas: 34, rechazadas: 5, tasa: 14.7, base: 28.7, rr: 0.5, p: 0.061, evidencia: 'nada' },
];

/** Piezas mínimas para publicar una tasa con nombre de persona, y sin él. */
export const MIN_PIEZAS_PERSONA = 20;
export const MIN_PIEZAS_OTRO = 8;

/**
 * Perfil de defectos: **no** mide lo mismo que las dos tarjetas anteriores.
 *
 * El chi² y los factores de riesgo preguntan *quién rechaza más*; esto pregunta
 * *qué le sale mal a cada uno*. Dos soldadores con el mismo 30 % de rechazo
 * pueden tener problemas distintos —uno socavado, otro falta de remate— y eso
 * cambia por completo qué se hace al respecto.
 *
 * El **defecto característico** es aquel en el que esa categoría se desvía más
 * de la mezcla de **su etapa** (mínimo 3 casos; «Otro» no cuenta, es el cajón de
 * sastre). Sirve para dar formación específica, no para comparar personas.
 */
export type DimensionPerfil = 'soldador' | 'inspector' | 'obra' | 'modulo' | 'tipo';

export type FilaPerfil = {
    fase: string;
    nombre: string;
    piezas: number;
    inspecciones: number;
    defectos: number;
    defPorPieza: number;
    /** `null` = no llega al mínimo de piezas para publicar una tasa. */
    rechazo: number | null;
    top: { defecto: string; n: number; share: number }[];
    caracteristico: { defecto: string; indice: number } | null;
};

export const PERFIL_DEFECTOS: Record<DimensionPerfil, FilaPerfil[]> = {
    soldador: [
        {
            fase: '2ª', nombre: 'MHV', piezas: 38, inspecciones: 44, defectos: 31, defPorPieza: 0.82, rechazo: 44.7,
            top: [
                { defecto: 'Socavado', n: 16, share: 51.6 },
                { defecto: 'Porosidad', n: 9, share: 29 },
                { defecto: 'Falta de fusión', n: 4, share: 12.9 },
            ],
            caracteristico: { defecto: 'Socavado', indice: 1.9 },
        },
        {
            fase: '2ª', nombre: 'RSC', piezas: 44, inspecciones: 48, defectos: 24, defPorPieza: 0.55, rechazo: 34.1,
            top: [
                { defecto: 'Porosidad', n: 13, share: 54.2 },
                { defecto: 'Socavado', n: 6, share: 25 },
                { defecto: 'Salpicadura', n: 3, share: 12.5 },
            ],
            caracteristico: { defecto: 'Porosidad', indice: 2.4 },
        },
        {
            fase: '2ª', nombre: 'APC', piezas: 51, inspecciones: 53, defectos: 18, defPorPieza: 0.35, rechazo: 23.5,
            top: [
                { defecto: 'Socavado', n: 8, share: 44.4 },
                { defecto: 'Falta de fusión', n: 5, share: 27.8 },
                { defecto: 'Cráter', n: 3, share: 16.7 },
            ],
            caracteristico: { defecto: 'Falta de fusión', indice: 2.9 },
        },
        {
            fase: '2ª', nombre: 'LGT', piezas: 34, inspecciones: 35, defectos: 9, defPorPieza: 0.26, rechazo: 14.7,
            top: [
                { defecto: 'Socavado', n: 5, share: 55.6 },
                { defecto: 'Porosidad', n: 3, share: 33.3 },
            ],
            caracteristico: null,
        },
        {
            fase: '2ª', nombre: 'TDL', piezas: 12, inspecciones: 12, defectos: 3, defPorPieza: 0.25, rechazo: null,
            top: [{ defecto: 'Porosidad', n: 3, share: 100 }],
            caracteristico: null,
        },
    ],
    inspector: [
        {
            fase: '2ª', nombre: 'J. Cabrera', piezas: 94, inspecciones: 108, defectos: 62, defPorPieza: 0.66, rechazo: 38.3,
            top: [
                { defecto: 'Socavado', n: 22, share: 35.5 },
                { defecto: 'Falta de vestido', n: 19, share: 30.6 },
                { defecto: 'Porosidad', n: 12, share: 19.4 },
            ],
            caracteristico: { defecto: 'Falta de vestido', indice: 1.6 },
        },
        {
            fase: '2ª', nombre: 'E. Rivas', piezas: 121, inspecciones: 134, defectos: 58, defPorPieza: 0.48, rechazo: 27.3,
            top: [
                { defecto: 'Porosidad', n: 18, share: 31 },
                { defecto: 'Socavado', n: 15, share: 25.9 },
                { defecto: 'Fuera de escuadra', n: 9, share: 15.5 },
            ],
            caracteristico: { defecto: 'Fuera de escuadra', indice: 1.9 },
        },
        {
            fase: '3ª', nombre: 'M. Solís', piezas: 88, inspecciones: 91, defectos: 24, defPorPieza: 0.27, rechazo: 12.5,
            top: [
                { defecto: 'Espesor bajo', n: 14, share: 58.3 },
                { defecto: 'Escurrimiento', n: 6, share: 25 },
            ],
            caracteristico: null,
        },
    ],
    obra: [
        {
            fase: '2ª', nombre: 'AMPLIACION T4 CANCUN', piezas: 131, inspecciones: 149, defectos: 64, defPorPieza: 0.49, rechazo: 25.2,
            top: [
                { defecto: 'Socavado', n: 21, share: 32.8 },
                { defecto: 'Porosidad', n: 16, share: 25 },
                { defecto: 'Falta de vestido', n: 11, share: 17.2 },
            ],
            caracteristico: null,
        },
        {
            fase: '2ª', nombre: 'TRES GUERRAS VILLA MAGNA', piezas: 46, inspecciones: 58, defectos: 38, defPorPieza: 0.83, rechazo: 47.8,
            top: [
                { defecto: 'Falta de vestido', n: 17, share: 44.7 },
                { defecto: 'Fuera de escuadra', n: 8, share: 21.1 },
                { defecto: 'Socavado', n: 7, share: 18.4 },
            ],
            caracteristico: { defecto: 'Falta de vestido', indice: 2.4 },
        },
        {
            fase: '3ª', nombre: 'CANCUN PARKS II NAVE A', piezas: 44, inspecciones: 47, defectos: 16, defPorPieza: 0.36, rechazo: 20.5,
            top: [
                { defecto: 'Espesor bajo', n: 11, share: 68.8 },
                { defecto: 'Piel de naranja', n: 3, share: 18.8 },
            ],
            caracteristico: { defecto: 'Espesor bajo', indice: 1.3 },
        },
    ],
    modulo: [
        {
            fase: '2ª', nombre: 'Módulo 4', piezas: 28, inspecciones: 36, defectos: 26, defPorPieza: 0.93, rechazo: 50,
            top: [
                { defecto: 'Socavado', n: 12, share: 46.2 },
                { defecto: 'Falta de fusión', n: 6, share: 23.1 },
                { defecto: 'Porosidad', n: 5, share: 19.2 },
            ],
            caracteristico: { defecto: 'Falta de fusión', indice: 2.4 },
        },
        {
            fase: '2ª', nombre: 'Módulo 2', piezas: 62, inspecciones: 68, defectos: 29, defPorPieza: 0.47, rechazo: 25.8,
            top: [
                { defecto: 'Porosidad', n: 12, share: 41.4 },
                { defecto: 'Socavado', n: 9, share: 31 },
            ],
            caracteristico: null,
        },
        {
            fase: '2ª', nombre: 'Módulo 1', piezas: 54, inspecciones: 57, defectos: 18, defPorPieza: 0.33, rechazo: 20.4,
            top: [
                { defecto: 'Socavado', n: 8, share: 44.4 },
                { defecto: 'Falta de vestido', n: 5, share: 27.8 },
            ],
            caracteristico: null,
        },
    ],
    tipo: [
        {
            fase: '2ª', nombre: 'Contraviento', piezas: 41, inspecciones: 52, defectos: 34, defPorPieza: 0.83, rechazo: 56.1,
            top: [
                { defecto: 'Fuera de escuadra', n: 11, share: 32.4 },
                { defecto: 'Falta de vestido', n: 10, share: 29.4 },
                { defecto: 'Socavado', n: 7, share: 20.6 },
            ],
            caracteristico: { defecto: 'Fuera de escuadra', indice: 4 },
        },
        {
            fase: '2ª', nombre: 'Trabe principal', piezas: 118, inspecciones: 131, defectos: 61, defPorPieza: 0.52, rechazo: 30.5,
            top: [
                { defecto: 'Socavado', n: 24, share: 39.3 },
                { defecto: 'Porosidad', n: 17, share: 27.9 },
                { defecto: 'Falta de fusión', n: 8, share: 13.1 },
            ],
            caracteristico: null,
        },
        {
            fase: '2ª', nombre: 'Columna metálica', piezas: 87, inspecciones: 92, defectos: 28, defPorPieza: 0.32, rechazo: 19.5,
            top: [
                { defecto: 'Porosidad', n: 11, share: 39.3 },
                { defecto: 'Socavado', n: 9, share: 32.1 },
            ],
            caracteristico: null,
        },
    ],
};
