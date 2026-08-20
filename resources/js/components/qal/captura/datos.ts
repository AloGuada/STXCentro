/**
 * Catálogos y tablas de referencia de la pantalla de captura.
 *
 * Son datos FALSOS a propósito: la pantalla se está construyendo contra la
 * forma del formulario, no contra la base. Las tablas de inspección del módulo
 * (`qal_inspecciones`, `qal_puntos_inspeccion`, `qal_juntas`…) todavía no
 * existen, así que aquí se reproduce lo que la aplicación anterior traía
 * escrito en el HTML. Cuando el backend exista, esto se sustituye por props
 * del controlador y el archivo desaparece.
 *
 * Lo que NO es dato de ejemplo y hay que conservar tal cual: la tabla de
 * muestreo AQL, los prefijos de tipo de pieza y los puntos del mapeo. Eso es
 * norma de la empresa, no relleno.
 */

/** Nivel de inspección del muestreo. */
export type NivelMuestreo = 'I' | 'II' | 'III';

export const OBRAS = [
    'AMPLIACION T4 CANCUN',
    'CANCUN PARKS II NAVE A',
    'TRES GUERRAS VILLA MAGNA',
    'STEELEX 2',
    'TOTEM PRIME CENTER',
    'RANCHO COCOYOL',
    'PARKS NAVE J',
    'PLAZA COMERCIAL CHOLUL',
];

/**
 * Tipos de pieza. Las claves son los prefijos oficiales de ingeniería, que es
 * lo que permite deducir el tipo a partir de la marca.
 */
export const TIPOS_PIEZA: [string, string][] = [
    // estructura principal
    ['TP', 'Trabe principal'],
    ['TS', 'Trabe secundaria'],
    ['TA', 'Trabe de amarre'],
    ['TG', 'Trabe grúa'],
    ['VR', 'Viga riel'],
    ['AR', 'Armadura'],
    ['PT', 'Puntal'],
    ['CM', 'Columna metálica'],
    ['CME', 'Columna OR'],
    ['CMV', 'Columna de viento'],
    // largueros y cubierta
    ['LC', 'Larguero de cubierta / polín'],
    ['LCJ', 'Larguero de cubierta encajonado'],
    ['LM', 'Larguero de muro'],
    ['LMJ', 'Larguero de muro encajonado'],
    ['FR', 'Frontera para losacero'],
    ['FAL', 'Faldón'],
    ['LVR', 'Louver'],
    // arriostramiento
    ['CFC', 'Contraflambeo de cubierta'],
    ['CFM', 'Contraflambeo de muro'],
    ['CVC', 'Contraviento de cubierta'],
    ['CVM', 'Contraviento de muro'],
    ['RCV', 'Roldana de contraviento'],
    ['R', 'Riostra'],
    ['BR', 'Bracer'],
    ['TEN', 'Tensor'],
    // anclaje y conexiones
    ['AN', 'Ancla'],
    ['CAST', 'Castillo de anclaje'],
    ['PBE', 'Placa base embebida'],
    ['CXE', 'Conexión embebida'],
    ['CXM', 'Conexión a muro'],
    ['CXST', 'Conexión suelta'],
    ['ANGT', 'Ángulo terminal'],
    // escaleras y barandales
    ['ESC', 'Escalera'],
    ['ALF', 'Alfarda de escalera'],
    ['DESC', 'Descanso de escalera'],
    ['BAR', 'Barandal'],
    // accesorios y soportes
    ['BA', 'Bastidor'],
    ['RL', 'Riel'],
    ['SCAN', 'Soporte de canalón'],
    ['TIC', 'Tirante de canalón'],
    ['SE', 'Soporte de extractor'],
    ['SLAM', 'Soporte de lámina'],
    ['OTRO', 'Otro'],
];

/** Del prefijo más largo al más corto: si no, CMV se confunde con CM y RCV con R. */
const PREFIJOS = TIPOS_PIEZA.map(([clave]) => clave)
    .filter((clave) => clave !== 'OTRO')
    .sort((a, b) => b.length - a.length);

/**
 * Deduce el tipo a partir de la marca.
 *
 * Las marcas vienen como OBRA-PREFIJO[n]-CONSECUTIVO (PJ-CFC-8, TV-CAST2-2),
 * así que el segundo tramo empieza por el prefijo oficial. Es una sugerencia:
 * sólo rellena si el campo está vacío y el inspector puede cambiarla.
 */
export function tipoDeMarca(marca: string): string {
    const partes = String(marca || '')
        .toUpperCase()
        .split('-');
    if (partes.length < 2) {
        return '';
    }
    const segmento = partes[1].replace(/[^A-Z0-9]/g, '');
    return PREFIJOS.find((prefijo) => segmento.indexOf(prefijo) === 0) ?? '';
}

export function descripcionTipo(clave: string): string {
    return TIPOS_PIEZA.find(([k]) => k === clave)?.[1] ?? clave;
}

export const DEFECTOS_SOLDADURA = [
    'Grieta',
    'Falta de fusión',
    'Falta de penetración',
    'Traslape',
    'Cráter sin llenar',
    'Perfil inaceptable',
    'Soldadura convexa',
    'Soldadura cóncava',
    'Tamaño bajo lo nominal',
    'Pierna baja',
    'Garganta baja',
    'Piernas desiguales',
    'Socavación',
    'Golpe de arco',
    'Porosidad / poros',
    'Falta de soldadura',
    'Falta de relleno',
    'Falta de remate',
    'Puntos de soldadura sin retirar',
    'Daño de material',
    'Otro',
];

export const DEFECTOS_PINTURA = [
    'Falta pintura (FP)',
    'Espesor bajo (EB)',
    'Falta adherencia (FA)',
    'Falta limpieza (LIM)',
    'Otro',
];

/** Familias de defecto del lote de accesorios. */
export const DEFECTOS_ACC_DIMENSIONAL = [
    'Deflexión',
    'Torsión',
    'Flecha',
    'Contraflecha',
    'Hi-Low',
    'Alabeo en patín',
    'Pandeo de alma',
    'Longitud fuera de tolerancia',
    'Escuadre',
    'Otro',
];

export const DEFECTOS_ACC_BARRENOS = [
    'Diámetro incorrecto',
    'Posición incorrecta',
    'Barreno faltante',
    'Barreno sin habilitar',
    'Otro',
];

export const EQUIPOS = [
    'FICEP Gemini (placa)',
    'FICEP Kronos (placa)',
    'Lincoln (placa)',
    'Somey (placa)',
    'Oxicorte manual (placa)',
    'Tiralíneas (placa)',
    'FICEP Valiant (perfil)',
    'Robot RAZ (perfil)',
    'Sierra (perfil)',
    'Habilitado',
];

export const OPERADORES = [
    'Alejandro Maldonado',
    'Brandon Echeverría',
    'David Moo',
    'Francisco Huchin',
    'Jorge Arjona',
    'Luis Miguel Tuz',
    'Miguel Ake',
    'Yahir Alvarado',
];

export const RESPONSABLES = [
    'CRESPO',
    'JESÚS CHÁVEZ',
    'CARLOS ROMERO',
    'VICTOR ESTRELLES',
    'DIONISIO ARAUJO',
    'ALEXIS TUT',
    'VENADO',
    'DANIEL CHAN',
    'CARLOS TONEL',
    'PABLO OJEDA',
    'MANUEL ESTRELLA',
    'IRVING DÍAZ',
    'DAMIÁN CACH MOO',
];

export const SUPERVISORES_PINTURA = ['David Pinto', 'Oscar Chim'];

/** Muestra del padrón real: el desplegable se llena igual con 20 que con 86. */
export const SOLDADORES: [string, string][] = [
    ['ALEXIS DE LA CRUZ TUT CANCHE', 'ACTC'],
    ['AMILCAR DE JESUS MATU NAHUAT', 'AJMN'],
    ['ANDRES MANUEL SOBERANIS CANUL', 'AMSC'],
    ['ANGEL EDUARDO MAY TUT', 'AEMT'],
    ['ANGEL JARED VALENCIA ANGULO', 'AJAV'],
    ['ANGEL PEREZ CAÑETE', 'APC'],
    ['ARIEL ALBERTO RODRIGUEZ GARCIA', 'AARG'],
    ['BRAIAN CONCEPCION CABRERA LEO', 'BCCL'],
    ['CARLOS ANTONIO GONZALES TONEL', 'CAGT'],
    ['CRISTIAN ALBERTO UC SANTANA', 'CAUS'],
    ['DAMIAN ENRIQUE CACH MOO', 'DECM'],
    ['DAVID NUÑES HERNANDEZ', 'DNH'],
    ['DIDIER ENRIQUE CUITUN UC', 'DECU'],
    ['DIONISIO ARAUJO CHUC', 'DAC'],
    ['EDGAR ROBERTO SANCHEZ ELIZALDE', 'ERSE'],
    ['GERARDO AGUSTIN EK PECH', 'GAEP'],
    ['JOSE DANIEL CHAB MARTIN', 'JDCM'],
    ['JUAN CARLOS PEREZ ROMERO', 'JCPR'],
    ['MIGUEL ANGEL TUN PUC', 'MATP'],
    ['VICTOR DAVID ESTRELLA CHAN', 'VDEC'],
];

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
 * "pendiente de decidir", y así aparece marcado en los tableros.
 */
export const DISPOSICIONES = [
    'Retrabajo completo del lote',
    'Se separaron solo las piezas malas',
    'Se revisó el lote pieza por pieza (100%)',
    'Devuelto al proceso anterior',
    'Liberado bajo concesión',
];
