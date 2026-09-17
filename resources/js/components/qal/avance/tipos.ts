/**
 * Lo que la pantalla de avance recibe del servidor.
 *
 * El cruce entre el plan y lo que vio calidad —arrastre, bajas, rechazos hasta
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
    /** La primera pieza de la marca, para las columnas de la tabla. */
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
    /** Lo escrito para esa semana, como se vuelve a mostrar en las cajas. Nulo si no hay plan. */
    plan: { marcas: string; bajas: string; notas: string | null } | null;
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
