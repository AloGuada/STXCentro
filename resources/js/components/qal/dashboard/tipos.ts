/**
 * Lo que manda el servidor al tablero (`DashboardController`).
 *
 * Las cuentas se hacen allá, en `TableroCalidad`, con las definiciones que
 * venían discutidas del tablero anterior: la unidad es la pieza, el FPY mira
 * sólo la primera inspección, fabricación y pintura no se promedian, las tasas
 * se callan por debajo del 30 % de cobertura y las juntas piden n ≥ 20. Aquí
 * sólo se pinta, y se decide qué se calla con los mismos umbrales.
 */

export type Kpi = {
    etiqueta: string;
    valor: string;
    nota?: string;
    /** Puntos de diferencia contra el periodo previo. Negativo = bajó. */
    delta?: number | null;
    /** Por defecto bajar es bueno (rechazo). En liberadas o FPY es al revés. */
    mejorSiBaja?: boolean;
    tono?: 'neutro' | 'malo' | 'bueno' | 'pendiente';
    /** Sufijo del delta: casi siempre «pp» (puntos porcentuales). */
    sufijo?: string;
};

export type Fase = '1ª' | '2ª' | '3ª';
export type FaseReportada = '2ª' | '3ª';

export type Opcion = { valor: string; texto: string };

/** Sólo lo que tiene inspecciones; los valores son ids, salvo la semana. */
export type OpcionesFiltros = {
    obras: Opcion[];
    soldadores: Opcion[];
    inspectores: Opcion[];
    tipos: Opcion[];
    semanas: string[];
};

type RechazoDeFase = {
    pct: number | null;
    conRechazo: number;
    piezas: number;
    reprocesos: number;
    /** Contra la semana anterior, en puntos. */
    delta: number | null;
};

type Rendimiento = { pct: number | null; n: number };

export type ResumenTablero = {
    liberadas: { piezas: number; unidades: number; obras: number };
    rechazo: Record<FaseReportada, RechazoDeFase | null>;
    pendientes: number;
    inspecciones: { total: number; lotes: number; miradas: number; representadas: number };
    obras: number;
    inspeccionesRechazadas: number | null;
    fpy: Record<FaseReportada, Rendimiento | null>;
    rechazoFinal: Record<FaseReportada, Rendimiento | null>;
    enAmbas: number;
    juntas: { n: number; fpy: number | null; final: number | null; reproceso: number | null } | null;
    alertas: { lotesSinDisposicion: { lotes: number; piezas: number }; pendientesViejas: number };
};

export type DimensionRechazo = 'obra' | 'soldador' | 'p2_subetapa' | 'tipo' | 'modulo' | 'inspector' | 'p1_subtipo';
export type OrigenPareto = 'p2_deftypes' | 'armado' | 'p3_deftypes';
export type BaseTasa = 'elem' | 'ton' | 'm2';
export type DimensionTasa = 'obra' | 'soldador' | 'tipo' | 'modulo' | 'inspector';

export type RechazoPorDimension = {
    filas: { nombre: string; pct: number; n: number; fase: Fase | 'mixta' }[];
    /** Piezas por debajo de las cuales un grupo no se publica. */
    minimo: number;
    fuera: number;
    publicables: number;
    cubiertas: number;
    total: number;
    /** La base de rechazo de cada transformación, sólo si la lista las mezcla. */
    bases: { fase: Fase; pct: number }[];
};

export type OperacionTablero = {
    resultadoPorObra: { obra: string; liberadas: number; rechazadas: number; pendientes: number }[];
    rechazoPorFase: { fase: Fase; pct: number; n: number }[];
    tendencia: Record<'week' | 'month', { periodo: string; pct: number; n: number }[]>;
    rechazoPor: Record<DimensionRechazo, RechazoPorDimension>;
    pareto: Record<OrigenPareto, { causa: string; n: number }[]>;
};

export type TasaNormalizada = {
    tasa: number | null;
    defectos: number;
    exposicion: number;
    cobertura: { pct: number | null; con: number; total: number; fuera: number };
    porDimension: Record<DimensionTasa, { nombre: string; tasa: number; exposicion: number }[]>;
};

export type FactorPrueba = 'p2_subetapa' | 'tipo' | 'soldador' | 'obra' | 'inspector';
export type EtapaPrueba = FaseReportada;

export const ETIQUETA_FACTOR: Record<FactorPrueba, string> = {
    tipo: 'tipo de pieza',
    p2_subetapa: 'sub-etapa',
    soldador: 'soldador',
    obra: 'obra',
    inspector: 'inspector',
};

/** Piezas mínimas para publicar un Cpk. Por debajo, la σ es la de la anécdota. */
export const MIN_PIEZAS_CPK = 5;

/**
 * Chi-cuadrado sobre el veredicto final de cada pieza-etapa, dentro de una
 * etapa. La V de Cramér mide la fuerza de la relación, no si existe.
 */
export type PruebaChi = {
    estado: 'ok';
    chi2: number;
    gl: number;
    p: number;
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

type SinPrueba = { piezas: number; fuera: number };

/** Cada estado sin prueba va aparte para que el tipo se estreche al descartarlos. */
export type ResultadoPrueba =
    | PruebaChi
    | ({ estado: 'sin_muestra' } & SinPrueba)
    | ({ estado: 'sin_rechazos' } & SinPrueba);

type SemanaDeTendencia = { semana: string; n: number; rechazadas: number; pct: number };

/** Cochran-Armitage: si la proporción sube o baja de forma sostenida. */
export type TendenciaRechazo =
    | { estado: 'ok'; z: number; p: number; semanas: number; puntos: SemanaDeTendencia[] }
    | { estado: 'pocas_semanas' | 'plano'; semanas: number; puntos: SemanaDeTendencia[] };

export type ClaveDescriptiva = 'peso' | 'elementos' | 'defectosSoldadura' | 'espesor' | 'margen' | 'area';

export type EstadisticaTablero = {
    /** Nula con menos de dos semanas: no hay carta que trazar. */
    cartaP: {
        preliminar: boolean;
        subgrupos: number;
        pbar: number;
        puntos: { semana: string; p: number; ucl: number; lcl: number; n: number }[];
    } | null;
    margen: {
        piezas: number;
        piezasPintura: number;
        resumen: { medianaPct: number; medianaMils: number; minPct: number; maxPct: number; bajoMinimo: number } | null;
        bins: { desde: number; n: number }[];
    };
    capacidad: {
        requerido: number;
        obras: string[];
        n: number;
        media: number;
        sigma: number;
        /** Nulo sin variación entre piezas. */
        cpk: number | null;
        fuera: number;
    }[];
    coberturaEspesor: { con: number; total: number };
    descriptiva: {
        clave: ClaveDescriptiva;
        conDato: number;
        universo: number;
        descartados: number;
        estadistica: { media: number; mediana: number; sigma: number; cv: number | null; min: number; max: number } | null;
    }[];
    pruebas: Record<EtapaPrueba, Record<FactorPrueba, ResultadoPrueba>>;
    tendencias: Record<EtapaPrueba, TendenciaRechazo>;
};

export type DatosTablero = {
    resumen: ResumenTablero;
    operacion: OperacionTablero;
    tasas: Record<BaseTasa, TasaNormalizada>;
    estadistica: EstadisticaTablero;
};

/** Por debajo de esta cobertura una tasa normalizada no se publica. */
export const COBERTURA_MINIMA = 30;

/** Con menos juntas mapeadas sus porcentajes son anécdotas. */
export const JUNTAS_MINIMAS = 20;

export const ETIQUETA_BASE: Record<BaseTasa, string> = {
    elem: 'defectos por elemento (2ª)',
    ton: 'defectos por tonelada (2ª)',
    m2: 'defectos por m² (3ª)',
};
