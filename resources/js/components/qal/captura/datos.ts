/**
 * Tablas de referencia de la captura.
 *
 * Los catálogos (obras, marcas, soldadores, defectos, tipos de pieza…) ya
 * llegan del servidor. Lo que queda aquí es norma de la empresa, no relleno: la
 * tabla de muestreo AQL, los puntos del mapeo y las disposiciones de un lote
 * rechazado. El servidor tiene su propia copia de la tabla AQL
 * (`CalculadorAql`) y es la que vale al guardar; ésta sólo adelanta el
 * veredicto en pantalla.
 */

/** Nivel de inspección del muestreo. */
export type NivelMuestreo = 'I' | 'II' | 'III';

/**
 * Tabla de muestreo AQL 10.
 *
 * Por cada tamaño de lote y nivel: [muestra, máximo aceptable, mínimo de
 * rechazo]. No es un dato de ejemplo — es la norma con la que se acepta o se
 * rechaza un lote entero, así que se porta tal cual.
 */
export const TABLA_MUESTREO: { max: number; I: number[]; II: number[]; III: number[] }[] = [
    { max: 8, I: [2, 0, 2], II: [2, 1, 2], III: [3, 1, 2] },
    { max: 15, I: [2, 0, 2], II: [3, 1, 2], III: [5, 1, 2] },
    { max: 25, I: [2, 0, 2], II: [5, 1, 2], III: [8, 1, 2] },
    { max: 50, I: [2, 0, 2], II: [8, 2, 3], III: [13, 2, 3] },
    { max: 90, I: [2, 0, 1], II: [13, 3, 4], III: [20, 3, 4] },
    { max: 150, I: [3, 1, 3], II: [20, 5, 6], III: [32, 5, 6] },
    { max: 280, I: [5, 1, 4], II: [32, 7, 8], III: [50, 8, 9] },
    { max: 500, I: [8, 2, 5], II: [50, 10, 11], III: [80, 12, 13] },
];

/**
 * Los puntos que se revisan junta por junta en el mapeo de soldaduras.
 * Su clave es la del formato oficial (columnas `m_*` de la app anterior).
 */
export const PUNTOS_MAPEO: [string, string][] = [
    ['m_material', 'Material correcto'],
    ['m_prepfilete', 'Prep. junta filete'],
    ['m_prepranura', 'Prep. junta ranura'],
    ['m_respaldo', 'Placa de respaldo'],
    ['m_acceso', 'Radios de acceso'],
    ['m_corte', 'Corte sin muescas'],
    ['m_precal', 'Precalentamiento'],
    ['m_limpieza', 'Limpieza entre pasadas'],
    ['m_grieta', 'Grieta'],
    ['m_fusion', 'Falta de fusión'],
    ['m_traslape', 'Traslape'],
    ['m_insuf', 'Soldadura insuficiente'],
    ['m_poros', 'Porosidad'],
    ['m_socav', 'Socavado'],
    ['m_perfil', 'Perfil de soldadura'],
    ['m_crater', 'Cráter'],
    ['m_retiro', 'Retiro de puntos'],
    ['m_matbase', 'Material base dañado'],
];

/**
 * Qué se hizo con el lote rechazado. El vacío es una opción real: significa
 * "pendiente de decidir", y así aparece marcado en los tableros. «Liberado bajo
 * concesión» cuenta como liberado: el servidor lo reconoce por esa palabra.
 */
export const DISPOSICIONES = [
    'Retrabajo completo del lote',
    'Se separaron solo las piezas malas',
    'Se revisó el lote pieza por pieza (100%)',
    'Devuelto al proceso anterior',
    'Liberado bajo concesión',
];
