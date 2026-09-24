/**
 * Lo que la pantalla de avance recibe del servidor.
 *
 * El cruce entre el plan y lo que vio calidad —arrastre, rechazos hasta
 * la semana, corte por tipo— se calcula en `app/Services/Qal/AvanceProduccion.php`;
 * aquí sólo viven sus formas y las definiciones de cada transformación.
 */

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

/** Una obra de Calidad como opción de selector. */
export type ObraOpcion = { id: number; no: string | null; descripcion: string | null };

/**
 * Una pieza (su QR) reducida a su historia en una transformación: el resumen
 * de todas sus inspecciones, porque una pieza reinspeccionada tres veces sigue
 * siendo una pieza.
 */
export type PiezaVista = {
    marca: string;
    obra_id: number;
    obra: string | null;
    qr: string;
    /** Semana en que se presentó a inspección por primera vez. */
    semanaFabricada: string;
    /** Semana en que se liberó; vacía si no se ha liberado. */
    semanaLiberada: string;
    /** Nº de inspección en que se liberó: >1 significa que hubo retrabajo. */
    inspeccionLiberada: number | null;
    semanasRechazada: string[];
    estatus: 'Liberado' | 'Rechazado' | 'Pendiente';
    /** Fecha de la última inspección, para contar los días parada. */
    fechaUltima: string;
    inspecciones: number;
    inspector: string | null;
};

/** Un grupo de trabajo de Producción. */
export type GrupoOpcion = { id: number; descripcion: string };

/**
 * Una pieza del borrador: el plan de la semana mientras sigue abierto. Todavía
 * no se cruza con nada, porque no cuenta hasta que se cierra.
 */
export type PiezaDelBorrador = {
    id: number;
    marca: string;
    lote: string | null;
    qr: string;
    qs: string | null;
    grupo: string | null;
    modulo: string | null;
};

/**
 * En qué va el plan de la semana. Abierto es un borrador de Producción: no
 * cuenta ni lo ve Calidad. Cerrado es el compromiso, y ya no se toca.
 */
export type EstadoDelPlan = 'sin_plan' | 'abierto' | 'cerrado';

/**
 * Una línea del plan: una pieza programada contra lo que vio calidad. Las
 * cuentas valen cero o uno; se conservan porque los totales las suman.
 */
export type EstadoLinea = {
    id: number;
    marca: string;
    lote: string | null;
    qr: string;
    qs: string | null;
    /** El grupo de trabajo que la hace y el módulo donde la hace, como se escribió («1.2»). */
    grupo: string | null;
    modulo: string | null;
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
    /** Su historia en las inspecciones, para las columnas de la tabla. */
    primera: { semanaFabricada: string; semanaLiberada: string; inspecciones: number } | null;
    /** Viene de una semana anterior que no se cerró. */
    arrastrada: boolean;
    /** La semana en que se programó. */
    desde: string;
};

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

export type FilaTipo = {
    tipo: string;
    programadas: number;
    fabricadas: number;
    pendientes: number;
    liberadas: number;
    rechazadas: number;
    empezadas: number;
};

/** Una obra, una semana y una transformación, ya cruzadas. */
export type VistaAvance = {
    plan: { estado: EstadoDelPlan; notas: string | null; cerradoEl: string | null; cerradoPor: string | null };
    /** Las piezas del plan abierto; sólo llegan a quien lo captura. */
    borrador: PiezaDelBorrador[] | null;
    /** Sólo de planes cerrados: lo de esta semana más lo arrastrado. */
    lineas: EstadoLinea[];
    total: Totales;
    tipos: FilaTipo[];
    /** Sólo las piezas que cuentan para la cola de reparación. */
    reparaciones: PiezaVista[];
};

export type ResumenFase = {
    programadas: number;
    fabricadas: number;
    pendientes: number;
    empezadas: number;
    enReparacion: number;
    arrastre: number;
    fabricadasSemana: number;
    cumplimiento: number | null;
    salieron: number | null;
};

export type ResumenObra = {
    obra_id: number;
    obra: string;
    fases: Record<Fase, ResumenFase>;
    /** ¿Pasó algo esta semana? Programado, fabricado, arrastrado o en reparación. */
    viva: boolean;
};

/** La portada: cada obra por separado, y la cola de reparación de todas. */
export type ComparativaAvance = {
    resumenes: ResumenObra[];
    reparaciones: PiezaVista[];
};
