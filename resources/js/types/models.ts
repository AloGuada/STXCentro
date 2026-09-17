export type Usuario = {
    id: string;
    empleado: number | null;
    departamento_id: number | null;
    name: string;
    email: string;
    email_verified_at: string | null;
    activo: boolean;
    fecha_baja: string | null;
    firma_path: string | null;
    roles?: Role[];
    departamento?: Departamento;
    created_at: string;
    updated_at: string;
};

export type Role = {
    id: number;
    name: string;
    guard_name: string;
    permissions?: Permission[];
    created_at: string;
    updated_at: string;
};

export type Permission = {
    id: number;
    name: string;
    guard_name: string;
    created_at: string;
    updated_at: string;
};

export type Departamento = {
    id: number;
    descripcion: string;
    manager: string;
    manager_usuario_id: string | null;
    manager_usuario?: Usuario;
    created_at: string;
    updated_at: string;
};

export type ObraEstatus = 'abierta' | 'cerrada';

export const OBRA_ESTATUS_LABELS: Record<ObraEstatus, string> = {
    abierta: 'Abierta',
    cerrada: 'Cerrada',
};

export type Proyecto = {
    id: number;
    no: string;
    descripcion: string;
    cliente_id?: number | null;
    fecha_inicio_plan?: string | null;
    estatus: ObraEstatus;
    activa?: boolean;
    cliente?: Cliente;
    obras?: Obra[];
    obra_base?: Obra | null;
    estimaciones?: CobEstimacion[];
    plan_cobro?: CobPlanCobro[];
    comparativos?: CobComparativo[];
    documento_carpetas?: CobDocumentoCarpeta[];
    documento_archivos?: CobDocumentoArchivo[];
    seccion_estatus?: CobDocumentoSeccionProyecto[];
    created_at: string;
    updated_at: string;
};

export type CobPlanCobro = {
    id: number;
    proyecto_id: number;
    orden: number;
    dias: number;
    fecha_inicio_plan: string;
    fecha_fin_plan: string;
    created_at: string;
    updated_at: string;
};

export type CobObraEtapa = {
    id: number;
    obra_id: number;
    descripcion: string;
    fecha_inicio_plan: string | null;
    fecha_fin_plan: string | null;
    created_at: string;
    updated_at: string;
};

export type CobReporteDetonacion = {
    obra_id: number;
    obra_no: string;
    descripcion: string | null;
    monto_sin_iva: number;
    monto_con_iva: number;
};

export type CobReporteCobro = {
    obra_id: number | null;
    obra_no: string;
    estimacion_id: number;
    numero_estimacion: number;
    monto_sin_iva: number;
    monto_con_iva: number;
};

/** Montos comunes a la fila anual y al reporte de la semana (calculados en vivo). */
type CobReporteMontos = {
    anio: number;
    semana: number;
    fecha_inicio: string;
    fecha_fin: string;
    saldo_anterior_sin_iva: number;
    saldo_anterior_con_iva: number;
    total_detonaciones_sin_iva: number;
    total_detonaciones_con_iva: number;
    total_cobrado_sin_iva: number;
    total_cobrado_con_iva: number;
    saldo_nuevo_sin_iva: number;
    saldo_nuevo_con_iva: number;
};

/** Fila de la tabla anual (una por semana). */
export type CobReporteFila = CobReporteMontos & {
    tiene_notas: boolean;
};

/** Reporte completo de una semana. */
export type CobReporteSemana = CobReporteMontos & {
    detonaciones: CobReporteDetonacion[];
    cobros: CobReporteCobro[];
    notas: string | null;
};

export type Obra = {
    id: number;
    proyecto_id?: number | null;
    tipo?: 'base' | 'adicional';
    proyecto?: Proyecto;
    no: string;
    descripcion: string;
    fecha_inicio: string | null;
    fecha_fin: string | null;
    presupuesto_total: number;
    ingreso_real: number | null;
    estatus: ObraEstatus;
    obra_rubros?: CostosObraRubro[];
    // Cobranza fields
    cliente_id?: number | null;
    tipo_contrato?: string | null;
    monto?: number | null;
    monto_iva?: number | null;
    anticipo?: number | null;
    garantia?: number | null;
    peso?: number | null;
    porcentaje_fabricacion?: number | null;
    porcentaje_montaje?: number | null;
    porcentaje_otros?: number | null;
    descripcion_otros?: string | null;
    activa?: boolean;
    es_planta?: boolean;
    porcentaje_obra?: number | null;
    cliente?: Cliente;
    partidas?: CobPartida[];
    lotes_pmo?: CobObraEtapa[];
    estimaciones?: CobEstimacion[];
    anticipos?: CobAnticipo[];
    adendas?: CobAdenda[];
    deducciones?: CobDeduccion[];
    eventos?: CobEvento[];
    disputas?: CobDisputa[];
    penalizaciones?: CobPenalizacion[];
    created_at: string;
    updated_at: string;
};

export type CobSeccionEstatus = 'pendiente' | 'completado';

export type CobDocumentoSeccion = {
    id: number;
    nombre: string;
    orden: number;
    activo?: boolean;
    carpetas_count?: number;
    archivos_count?: number;
    // Presentes solo en el contexto de un proyecto (overlay por proyecto).
    estatus?: CobSeccionEstatus;
    visible?: boolean;
    created_at?: string;
    updated_at?: string;
};

export type CobDocumentoSeccionProyecto = {
    id: number;
    proyecto_id: number;
    seccion_id: number;
    estatus: CobSeccionEstatus;
    visible: boolean;
    created_at: string;
    updated_at: string;
};

export type CobDocumentoCarpeta = {
    id: number;
    proyecto_id: number;
    seccion_id: number;
    parent_id: number | null;
    nombre: string;
    orden: number;
    created_at: string;
    updated_at: string;
};

export type CobDocumentoArchivo = {
    id: number;
    proyecto_id: number;
    seccion_id: number;
    carpeta_id: number | null;
    nombre_original: string;
    path: string;
    mime: string | null;
    size: number | null;
    subido_por_id: string | null;
    created_at: string;
    updated_at: string;
};

export type Media = {
    id: number;
    descripcion: string;
    nombre_original: string | null;
    path: string;
    mime: string;
    size: number;
    mediable_type: string;
    mediable_id: number;
    created_at: string;
    updated_at: string;
};

export type Tag = {
    id: number;
    name: string;
    slug: string;
    color: string | null;
    statusable_type: string | null;
    statusable_id: number | null;
    created_at: string;
    updated_at: string;
};

export type PaginatedData<T> = {
    current_page: number;
    data: T[];
    first_page_url: string | null;
    from: number | null;
    last_page: number;
    last_page_url: string | null;
    links: { url: string | null; label: string; active: boolean }[];
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

// Intranet types
export type TipoDocumento =
    | 'manual_operativo'
    | 'formato_proceso'
    | 'protocolo'
    | 'politica'
    | 'instructivo_trabajo'
    | 'procedimiento_especifico'
    | 'procedimiento_general'
    | 'plan_calidad'
    | 'manual_gestion';

export type SeccionEstatica = {
    id: number;
    slug: string;
    titulo: string;
    descripcion: string | null;
    boton: string;
    url_externa: string | null;
    order: number;
    activo: boolean;
    media?: Media;
    created_at: string;
    updated_at: string;
};

export type Area = {
    id: number;
    descripcion: string;
    parent_id: number | null;
    parent?: Area;
    children?: Area[];
    order: number;
    activo: boolean;
    documentos?: Documento[];
    created_at: string;
    updated_at: string;
};

export type Documento = {
    id: number;
    area_id: number;
    descripcion: string;
    codigo: string | null;
    tipo: TipoDocumento;
    order: number;
    activo: boolean;
    area?: Area;
    media?: Media;
    created_at: string;
    updated_at: string;
};

export type DocumentosPorTipo = Record<TipoDocumento, {
    label: string;
    documentos: Documento[];
}>;

// STI Types
export type StiCriticidad = 1 | 2 | 3 | 4;

export const CRITICIDAD_LABELS: Record<StiCriticidad, string> = {
    1: 'Bajo',
    2: 'Medio',
    3: 'Alto',
    4: 'Critico',
};

export const CRITICIDAD_COLORS: Record<StiCriticidad, string> = {
    1: 'badge-info',
    2: 'badge-warning',
    3: 'badge-error',
    4: 'badge-error bg-red-700',
};

export type StiEquipo = {
    id: number;
    descripcion: string;
    serie: string | null;
    marca: string | null;
    factor_criticidad: StiCriticidad;
    tickets?: StiTicket[];
    mantenimientos?: StiMantenimiento[];
    mantenimientos_count?: number;
    asignaciones?: StiAsignacionActivo[];
    grupos?: StiGrupo[];
    items?: StiItem[];
    created_at: string;
    updated_at: string;
};

export type StiTecnico = {
    id: number;
    descripcion: string;
    activo: boolean;
    created_at: string;
    updated_at: string;
};

export type StiStatus = {
    id: number;
    descripcion: string;
    orden: number;
    detiene_tiempo: boolean;
    color: string;
    created_at: string;
    updated_at: string;
};

export type StiTicketHistorial = {
    id: number;
    ticket_id: number;
    status_id: number;
    status?: StiStatus;
    created_at: string;
    updated_at: string;
};

export type StiTicketComentario = {
    id: number;
    ticket_id: number;
    comentario: string;
    autor: string;
    tipo: 'usuario' | 'tecnico';
    created_at: string;
    updated_at: string;
};

export type StiTicket = {
    id: number;
    nombre_solicitante: string;
    comentario: string;
    tecnico_id: number | null;
    equipo_id: number | null;
    departamento_id: number;
    firma_completado: string | null;
    calificacion: number | null;
    tecnico?: StiTecnico;
    equipo?: StiEquipo;
    departamento?: Departamento;
    historial?: StiTicketHistorial[];
    comentarios?: StiTicketComentario[];
    media?: Media[];
    tags?: Tag[];
    costos?: StiCostoMantenimiento[];
    created_at: string;
    updated_at: string;
};

export type StiCostoMantenimiento = {
    id: number;
    descripcion: string;
    cantidad: number;
    costeable_id: number;
    costeable_type: string;
    created_at: string;
    updated_at: string;
};

export type StiMantenimiento = {
    id: number;
    equipo_id: number;
    plan_id: number | null;
    fecha_programada: string;
    descripcion: string | null;
    tecnico_id: number | null;
    status: 'pendiente' | 'realizado';
    fecha_realizado: string | null;
    equipo?: StiEquipo;
    tecnico?: StiTecnico;
    plan?: StiPlan;
    media?: Media[];
    costos?: StiCostoMantenimiento[];
    check_ejecuciones?: StiCheckEjecucion[];
    created_at: string;
    updated_at: string;
};

export type StiPlan = {
    id: number;
    descripcion: string;
    periodicidad: number;
    fecha_inicial: string | null;
    activo: boolean;
    checks?: StiCheck[];
    mantenimientos?: StiMantenimiento[];
    checks_count?: number;
    mantenimientos_count?: number;
    created_at: string;
    updated_at: string;
};

export type StiCheck = {
    id: number;
    plan_id: number;
    descripcion: string;
    orden: number;
    plan?: StiPlan;
    created_at: string;
    updated_at: string;
};

export type StiCheckEjecucion = {
    id: number;
    mantenimiento_id: number;
    check_id: number;
    resultado: boolean;
    observaciones: string | null;
    tecnico_id: number | null;
    check?: StiCheck;
    tecnico?: StiTecnico;
    created_at: string;
    updated_at: string;
};

export type StiAsignacionActivo = {
    id: number;
    departamento_id: number;
    equipo_id: number;
    no_empleado: string;
    empleado: string;
    firma_empleado: string | null;
    no_ti: string;
    nombre_ti: string;
    firma_ti: string | null;
    fecha_inicial: string;
    fecha_termino: string | null;
    estado: 'activo' | 'devuelto' | 'transferido';
    departamento?: Departamento;
    equipo?: StiEquipo;
    media?: Media[];
    created_at: string;
    updated_at: string;
};

// STI Inventario Types
export type StiItemEstado = 'disponible' | 'instalado' | 'dañado' | 'baja';

export const ITEM_ESTADO_LABELS: Record<StiItemEstado, string> = {
    disponible: 'Disponible',
    instalado: 'Instalado',
    dañado: 'Dañado',
    baja: 'Baja',
};

export const ITEM_ESTADO_COLORS: Record<StiItemEstado, string> = {
    disponible: 'badge-success',
    instalado: 'badge-info',
    dañado: 'badge-warning',
    baja: 'badge-error',
};

export type StiItemAccion = 'recepcion' | 'instalacion' | 'retiro' | 'baja';

export const ITEM_ACCION_LABELS: Record<StiItemAccion, string> = {
    recepcion: 'Recepción',
    instalacion: 'Instalación',
    retiro: 'Retiro',
    baja: 'Baja',
};

export type StiItemTipo = {
    id: number;
    descripcion: string;
    created_at: string;
    updated_at: string;
};

export type StiItem = {
    id: number;
    descripcion: string;
    tipo_id: number;
    costo: number;
    no_serie: string | null;
    estado: StiItemEstado;
    principal: boolean;
    accesorio: boolean;
    tipo?: StiItemTipo;
    grupo?: StiGrupo;
    historial?: StiItemHistorial[];
    media?: Media[];
    created_at: string;
    updated_at: string;
};

export type StiGrupo = {
    id: number;
    equipo_id: number;
    item_id: number;
    equipo?: StiEquipo;
    item?: StiItem;
    created_at: string;
    updated_at: string;
};

// Produccion Types
/** Ubicación de trabajo; sustituye a los enteros linea y modulo del grupo. */
export type ProdUbicacion = {
    id: number;
    nombre: string;
    activo: boolean;
    grupos_trabajo_count?: number;
    created_at: string;
    updated_at: string;
};

/**
 * Categoría del trabajador. `valor` es un peso para repartir el excedente del
 * destajo, no un sueldo.
 */
export type ProdCategoriaEmpleado = {
    id: number;
    nombre: string;
    valor: number;
    orden: number;
    activo: boolean;
    empleados_count?: number;
    created_at: string;
    updated_at: string;
};

/**
 * La revisión previa del CSV de producción: lo que pasaría si se aplicara, ya
 * resuelto contra el catálogo pero sin haber escrito nada.
 */
export type ProdPlanEstado = 'aplicable' | 'omitida' | 'error';

export type ProdPlanRenglon = {
    referencia: string;
    linea: number | null;
    estado: ProdPlanEstado;
    codigo: string;
    motivo: string | null;
    pieza_id: number | null;
    /** El QR al que se asignó. Si `por_qs`, lo eligió el sistema. */
    qr: string | null;
    qs: string | null;
    marca: string | null;
    proceso: string | null;
    proceso_id: number | null;
    grupo: string | null;
    grupo_trabajo_id: number | null;
    porcentaje: number | null;
    /** El archivo no traía QR: la pieza la eligió el sistema, del QR más chico al más alto. */
    por_qs: boolean;
    /** Con qué precisión venía el renglón: la pieza (`qr`), sus hermanas (`qs`) o el modelo (`marca`). */
    asignado_por: 'qr' | 'qs' | 'marca';
    candidatas: number | null;
};

export type ProdPlanImportacion = {
    resumen: {
        formato: 'export' | 'simple';
        filas_leidas: number;
        aplicables: number;
        omitidas: number;
        errores: number;
        /** Renglones que no traían QR y cuya pieza eligió el sistema. */
        asignadas_por_sistema: number;
        ignorados_por_evento: number;
    };
    /** Eventos que no pagan destajo, agregados: son la mayoría del export. */
    ignorados: { evento: string; muestra: string; renglones: number }[];
    /** Qué le toca a cada cuadrilla. Sale del archivo completo, no del detalle truncado. */
    por_grupo: ProdPlanGrupo[];
    renglones: ProdPlanRenglon[];
    mostrados: number;
    truncado: boolean;
};

/** El corte por cuadrilla de la revisión previa. */
export type ProdPlanGrupo = {
    /** `null` cuando el renglón ni siquiera resolvió grupo: son los que hay que corregir. */
    grupo: string | null;
    grupo_trabajo_id: number | null;
    /** Movimientos que van a entrar. */
    movimientos: number;
    /** A cuántas piezas equivalen esos movimientos, sumando porcentajes. */
    piezas: number;
    /** Los que se quedan fuera, entre omitidos y con problema. */
    no_entran: number;
};

/** Pieza pagada a medias que todavía tiene saldo por liquidar. */
export type ProdPendienteLiquidar = {
    pieza_id: number;
    qr: string;
    qs: string | null;
    marca: string;
    lote: string | null;
    descripcion: string;
    proceso_id: number;
    proceso: string;
    /** Sólo en grupos que pagan por subproceso: el saldo es del paso, no del proceso. */
    subproceso_id: number | null;
    subproceso: string | null;
    obra: string;
    grupo_trabajo_id: number | null;
    grupo_trabajo: string | null;
    pagado: number;
    saldo: number;
    /** El avance que se pagaría en este destajo: 1º, 2º... cuenta semanas, no capturas. */
    numero_avance: number;
    porcentaje_sugerido: number;
};

/** Catálogo de piezas de una obra; sólo una versión está vigente a la vez. */
export type ProdCatalogo = {
    id: number;
    obra_id: number;
    catalogo_origen_id: number | null;
    nombre: string;
    version: number;
    vigente: boolean;
    notas: string | null;
    obra?: Obra;
    conceptos?: Concepto[];
    created_at: string;
    updated_at: string;
};

/**
 * La marca: el modelo del catálogo. `cantidad` dice cuántas piezas pide, y esas
 * unidades viven en `piezas`, una por QS. El destajo se paga contra la pieza,
 * no contra la marca.
 */
export type Concepto = {
    id: number;
    obra_id: number;
    catalogo_id: number | null;
    catalogo?: ProdCatalogo;
    marca: string;
    /** Lote de fabricacion. Junto con la marca identifica el modelo. */
    lote: string | null;
    descripcion: string;
    cantidad: number;
    peso_unitario: number;
    longitud: number | null;
    categoria_id: number | null;
    version: number;
    activo: boolean;
    obra?: Obra;
    categoria?: ProdCategoria;
    piezas?: ProdPieza[];
    grupo_precio_conceptos?: ProdGrupoPrecioConcepto[];
    created_at: string;
    updated_at: string;
};

/**
 * Una pieza física del catálogo, identificada por su QR. El QS acompaña como
 * dato de planta y puede repetirse entre lotes.
 */
export type ProdPieza = {
    id: number;
    catalogo_id: number;
    concepto_id: number;
    qr: string;
    qs: string | null;
    /** La numeración de planta según el QR, tal cual («1 de 92»); las piezas viejas no lo traen. */
    correlativo: string | null;
    pieza_origen_id: number | null;
    activo: boolean;
    marca?: Concepto;
    /** Avance por proceso, inyectado por AvanceDePiezas: procesoId => fracción. */
    avance?: Record<number, { capturado: number; disponible: number }>;
    created_at: string;
    updated_at: string;
};

/** Proceso que se paga como destajo: soldadura, pintura, etc. */
export type ProdProceso = {
    id: number;
    nombre: string;
    orden: number;
    activo: boolean;
    eventos?: ProdProcesoEvento[];
    registros_count?: number;
    created_at: string;
    updated_at: string;
};

/** Número de evento del export de planta que dispara el pago de un proceso. */
export type ProdProcesoEvento = {
    id: number;
    proceso_id: number;
    evento: string;
    descripcion: string | null;
    created_at: string;
    updated_at: string;
};

/** Tarifa por kilo de un proceso dentro de un grupo de precios. */
export type ProdGrupoPrecioProceso = {
    id: number;
    grupo_precio_id: number;
    proceso_id: number;
    precio_kilo: number;
    proceso?: ProdProceso;
    created_at: string;
    updated_at: string;
};

export type ProdCategoria = {
    id: number;
    nombre: string;
    conceptos_count?: number;
    created_at: string;
    updated_at: string;
};

/** Con qué regla paga un grupo de precios. Las dos son excluyentes. */
export type ProdTipoPago = 'kilo' | 'subproceso';

/**
 * Un paso de un proceso dentro de un grupo de precios, con precio fijo por
 * pieza: armar, puntear y soldar no valen lo mismo aunque los tres sean
 * soldadura.
 */
export type ProdGrupoPrecioSubproceso = {
    id: number;
    grupo_precio_id: number;
    proceso_id: number;
    nombre: string;
    orden: number;
    precio: number;
    activo: boolean;
    proceso?: ProdProceso;
    created_at: string;
    updated_at: string;
};

export type ProdGrupoPrecio = {
    id: number;
    obra_id: number;
    descripcion: string;
    tipo_pago: ProdTipoPago;
    /** Una tarifa por proceso: soldar y pintar la misma pieza no valen igual. */
    precios?: ProdGrupoPrecioProceso[];
    /** Sólo cuando `tipo_pago` es 'subproceso'. */
    subprocesos?: ProdGrupoPrecioSubproceso[];
    obra?: Obra;
    grupo_precio_conceptos_count?: number;
    conceptos?: Concepto[];
    created_at: string;
    updated_at: string;
};

export type ProdGrupoPrecioConcepto = {
    id: number;
    grupo_precio_id: number;
    concepto_id: number;
    grupo_precio?: ProdGrupoPrecio;
    concepto?: Concepto;
    created_at: string;
    updated_at: string;
};

export type ProdGrupoTrabajo = {
    id: number;
    descripcion: string;
    activo: boolean;
    ubicaciones?: ProdUbicacion[];
    empleados?: ProdGrupoEmpleado[];
    empleados_count?: number;
    created_at: string;
    updated_at: string;
};

export type ProdGrupoEmpleado = {
    id: number;
    grupo_trabajo_id: number;
    /** Persona de RH. Null solo en los renglones capturados antes del enlace. */
    persona_id: number | null;
    nombre: string;
    no_empleado: string | null;
    categoria_empleado_id: number | null;
    categoria?: ProdCategoriaEmpleado | null;
    persona?: (RhPersona & { periodo_vigente?: RhPeriodoLaboral | null }) | null;
    created_at: string;
    updated_at: string;
};

/** Una pieza, en un proceso, en una fecha. Sin cantidad: un renglón es un QS. */
export type ProdRegistro = {
    id: number;
    fecha: string;
    pieza_id: number;
    proceso_id: number;
    /** Sólo cuando el grupo de precios de la marca paga por pasos. */
    subproceso_id: number | null;
    grupo_trabajo_id: number;
    /** Avance pagado de esa pieza; menos de 100 deja saldo por liquidar después. */
    porcentaje: number;
    pieza?: ProdPieza;
    proceso?: ProdProceso;
    subproceso?: ProdGrupoPrecioSubproceso;
    grupo_trabajo?: ProdGrupoTrabajo;
    created_at: string;
    updated_at: string;
};

export type ProdDestajo = {
    id: number;
    anio: number;
    semana: number;
    fecha_inicio: string;
    fecha_fin: string;
    cerrado: boolean;
    fecha_cierre: string | null;
    liquidaciones?: ProdLiquidacion[];
    liquidaciones_count?: number;
    created_at: string;
    updated_at: string;
};

/** Marca con producción capturada que no tiene tarifa para ese proceso. */
export type ProdPiezaSinPrecio = {
    concepto_id: number;
    marca: string;
    lote: string | null;
    proceso: string;
    /** Sólo en grupos que pagan por subproceso: el paso al que le falta precio. */
    subproceso: string | null;
    piezas: number;
};

export type ProdLiquidacion = {
    id: number;
    destajo_id: number;
    grupo_trabajo_id: number;
    total_kilos: number;
    total_produccion: number;
    total_extras: number;
    total_final: number;
    generado_en: string;
    generado_por: string;
    destajo?: ProdDestajo;
    grupo_trabajo?: ProdGrupoTrabajo;
    generador?: Usuario;
    detalles?: ProdLiquidacionDetalle[];
    pagos_extra?: ProdPagoExtra[];
    empleados?: ProdLiquidacionEmpleado[];
    created_at: string;
    updated_at: string;
};

export type ProdLiquidacionDetalle = {
    id: number;
    liquidacion_id: number;
    concepto_id: number | null;
    /** Snapshot del renglón al cerrar: no se relee del catálogo. */
    pieza_id: number | null;
    qr: string | null;
    qs: string | null;
    obra_id: number | null;
    marca: string | null;
    lote: string | null;
    proceso_id: number | null;
    proceso_nombre: string | null;
    /** Snapshot del paso, sólo en los grupos que pagan por subproceso. */
    subproceso_id: number | null;
    subproceso_nombre: string | null;
    descripcion: string | null;
    peso_unitario: number | null;
    longitud: number | null;
    grupo_precio_id: number;
    /** Avance pagado de esa pieza; los kilos ya vienen prorrateados por este %. */
    porcentaje: number;
    /** Cero en los renglones por subproceso: el peso no se acumula ahí. */
    kilos: number;
    /** Nulo en los renglones por subproceso, que cobran precio fijo por pieza. */
    precio_kilo_aplicado: number | null;
    precio_subproceso_aplicado: number | null;
    total: number;
    created_at: string;
    updated_at: string;
};

export type ProdTipoPagoExtra = {
    id: number;
    descripcion: string;
    orden: number;
    desgloce: boolean;
    /** El importe de los pagos de este tipo resta en vez de sumar. */
    es_descuento: boolean;
    created_at: string;
    updated_at: string;
};

export type ProdPagoExtra = {
    id: number;
    descripcion: string;
    tipo_id: number;
    destajo_id: number;
    grupo_trabajo_id: number;
    precio: number;
    dias: number;
    personas: number;
    /** Ya viene con signo: negativo si el tipo es descuento. */
    monto?: number;
    tipo?: ProdTipoPagoExtra;
    destajo?: ProdDestajo;
    grupo_trabajo?: ProdGrupoTrabajo;
    created_at: string;
    updated_at: string;
};

export type ProdLiquidacionEmpleado = {
    id: number;
    liquidacion_id: number;
    nombre: string;
    no_empleado: string | null;
    /** Snapshot del reparto al cerrar la semana. */
    dias_pagados: number;
    categoria_nombre: string | null;
    categoria_valor: number;
    salario_diario: number;
    sueldo_base: number;
    monto_destajo: number;
    /** Proporción del excedente que le tocó, como referencia. */
    porcentaje: number;
    monto_asignado: number;
    created_at: string;
    updated_at: string;
};

// Infraestructura Types
export type InfraCompresor = {
    id: number;
    usuario_id: string | null;
    infra_turno_id: number | null;
    compresor_1_status: boolean;
    compresor_1_presion_aire: number | null;
    compresor_1_tiempo_trabajo: number | null;
    compresor_1_tiempo_marcha: number | null;
    compresor_1_kwhr: number | null;
    compresor_2_status: boolean;
    compresor_2_presion_aire: number | null;
    compresor_2_tiempo_trabajo: number | null;
    compresor_2_tiempo_marcha: number | null;
    compresor_2_kwhr: number | null;
    compresor_3_status: boolean;
    compresor_3_presion_aire: number | null;
    compresor_3_tiempo_trabajo: number | null;
    compresor_3_tiempo_marcha: number | null;
    compresor_3_kwhr: number | null;
    observaciones: string | null;
    usuario?: Usuario;
    created_at: string;
    updated_at: string;
};

export type InfraBomba = {
    id: number;
    usuario_id: string | null;
    infra_turno_id: number | null;
    bomba_posos_1: boolean;
    bomba_posos_2: boolean;
    bomba_planta_1: boolean;
    bomba_planta_2: boolean;
    bomba_planta_3: boolean;
    nivel_salmuera: number | null;
    nivel_tinaco: number | null;
    nivel_sisterna: number | null;
    presion_tuberia: number | null;
    nivel_hipoclorito: number | null;
    nivel_anticongelante: number | null;
    aceite_del_motor: number | null;
    tanque_diesel: number | null;
    voltaje_bateria: number | null;
    bomba_jockey: boolean;
    bomba_electrica: boolean;
    bomba_diesel: boolean;
    presion_tuberia_incendio: number | null;
    observaciones: string | null;
    usuario?: Usuario;
    created_at: string;
    updated_at: string;
};

export type InfraTransformador = {
    id: number;
    usuario_id: string | null;
    infra_turno_id: number | null;
    linea_A: number | null;
    linea_A_max: number | null;
    date_A: string | null;
    linea_B: number | null;
    linea_B_max: number | null;
    date_B: string | null;
    linea_C: number | null;
    linea_C_max: number | null;
    date_C: string | null;
    voltaje_a: number | null;
    voltaje_b: number | null;
    voltaje_c: number | null;
    total_1: number | null;
    total_5: number | null;
    lectura_5y5: number | null;
    registro_a: number | null;
    registro_b: number | null;
    registro_c: number | null;
    tarifa: number | null;
    observaciones: string | null;
    usuario?: Usuario;
    created_at: string;
    updated_at: string;
};

export type InfraTanque = {
    id: number;
    usuario_id: string | null;
    infra_turno_id: number | null;
    pa_sistema_oxigeno: number | null;
    presion_sistema_oxigeno: number | null;
    presion_tanque_oxigeno: number | null;
    lt_tanque_oxigeno: number | null;
    kg_tanque_oxigeno: number | null;
    pa_sistema_argon: number | null;
    presion_sistema_argon: number | null;
    presion_tanque_argon: number | null;
    lt_tanque_argon: number | null;
    kg_tanque_argon: number | null;
    pa_sistema_co2: number | null;
    presion_sistema_co2: number | null;
    presion_tanque_co2: number | null;
    lt_tanque_co2: number | null;
    kg_tanque_co2: number | null;
    pa_sistema_lp: number | null;
    presion_sistema_lp: number | null;
    nivel_tanque_lp: number | null;
    numero_tanque_lp: number | null;
    lt_tanque_lp: number | null;
    kg_tanque_lp: number | null;
    observaciones: string | null;
    usuario?: Usuario;
    created_at: string;
    updated_at: string;
};

export type InfraPtar = {
    id: number;
    usuario_id: string | null;
    infra_turno_id: number | null;
    soplador_activa: boolean;
    bomba_activa: boolean;
    nivel_cloro: number | null;
    trampa_solida: boolean;
    observaciones: string | null;
    usuario?: Usuario;
    created_at: string;
    updated_at: string;
};

export type InfraTurno = {
    id: number | null;
    nombre: string;
    hora_inicio: string | null;
    hora_fin: string | null;
    orden: number;
    activo?: boolean;
    dias_semana?: InfraTurnoDia[];
};

export type InfraTurnoDia = {
    id: number;
    infra_turno_id: number;
    dia_semana: number;
};

export type InfraTurnoData = {
    turno: InfraTurno;
    compresores: InfraCompresor | null;
    bombas: InfraBomba | null;
    transformador: InfraTransformador | null;
    tanques: InfraTanque | null;
    ptar: InfraPtar | null;
};

// Infra Dashboard Types
export type InfraEstadoIndicador = {
    label: string;
    estado: boolean | null;
    tooltip: string;
};

export type InfraEstados = {
    compresores: InfraEstadoIndicador[];
    bombas: InfraEstadoIndicador[];
    tanques: InfraEstadoIndicador[];
    ptar: InfraEstadoIndicador[];
    transformadores: InfraEstadoIndicador[];
};

export type InfraChartCompresor = {
    mes: string;
    c1: number;
    c2: number;
    c3: number;
};

export type InfraChartBomba = {
    mes: string;
    avg_presion: number;
    stddev: number;
    max_presion: number;
    min_presion: number;
};

export type InfraChartTransformador = {
    mes: string;
    total_1: number;
    total_5: number;
    lectura_5y5: number;
};

export type InfraChartTanque = {
    mes: string;
    oxigeno: number;
    argon: number;
    co2: number;
    lp: number;
    oxigeno_acum: number;
    argon_acum: number;
    co2_acum: number;
    lp_acum: number;
};

export type StiItemHistorial = {
    id: number;
    item_id: number;
    equipo_id: number;
    tecnico_id: number | null;
    relacionable_type: string | null;
    relacionable_id: number | null;
    accion: StiItemAccion;
    fecha: string;
    observaciones: string | null;
    equipo?: StiEquipo;
    tecnico?: StiTecnico;
    created_at: string;
    updated_at: string;
};

// Costos Types
export type ProveedorEstatus = 'pendiente_validacion' | 'activo' | 'rechazado';

export const PROVEEDOR_ESTATUS_LABELS: Record<ProveedorEstatus, string> = {
    pendiente_validacion: 'Pendiente de validación',
    activo: 'Activo',
    rechazado: 'Rechazado',
};

export const PROVEEDOR_ESTATUS_COLORS: Record<ProveedorEstatus, string> = {
    pendiente_validacion: 'badge-warning',
    activo: 'badge-success',
    rechazado: 'badge-error',
};

export type TipoProveedor = 'proveedor' | 'tercero' | 'servicio';

export const TIPO_PROVEEDOR_LABELS: Record<TipoProveedor, string> = {
    proveedor: 'Proveedor',
    tercero: 'Tercero',
    servicio: 'Servicio',
};

export type FormaPago = 'transferencia' | 'cheque_efectivo';

export const FORMA_PAGO_LABELS: Record<FormaPago, string> = {
    transferencia: 'Transferencia',
    cheque_efectivo: 'Cheque / Efectivo',
};

export type Banco = {
    id: number;
    nombre: string;
    digitos_cuenta: number | null;
    es_pagador: boolean;
    activo: boolean;
    created_at: string;
    updated_at: string;
};

export type RegimenFiscal = {
    id: number;
    clave: string;
    descripcion: string;
    aplica_persona_fisica: boolean;
    aplica_persona_moral: boolean;
    activo: boolean;
    created_at: string;
    updated_at: string;
};

export type Proveedor = {
    id: number;
    codigo: string;
    razon_social: string;
    nombre_comercial: string | null;
    rfc: string;
    tipo_persona: string | null;
    regimen_fiscal_id: number | null;
    codigo_postal: string | null;
    domicilio_fiscal: string | null;
    domicilio_compra: string | null;
    giro: string | null;
    direccion: string | null;
    telefono: string | null;
    email: string | null;
    contacto_nombre: string | null;
    contacto_correo: string | null;
    banco_nombre: string | null;
    banco_id: number | null;
    titular_cuenta: string | null;
    numero_cuenta: string | null;
    clabe: string | null;
    tarjeta: string | null;
    moneda_cuenta: string | null;
    forma_pago: FormaPago | null;
    numero_servicio: string | null;
    referencia_servicio: string | null;
    tiene_acceso_portal: boolean;
    maneja_credito: boolean;
    limite_credito: number;
    dias_credito_default: number;
    respetar_fecha_factura: boolean;
    tipo_proveedor: TipoProveedor | null;
    activo: boolean;
    estatus: ProveedorEstatus;
    validado_por: string | null;
    validado_at: string | null;
    observacion_validacion: string | null;
    creado_por: string | null;
    bloqueado_complemento?: boolean;
    regimen_fiscal?: RegimenFiscal;
    banco?: Banco | null;
    validador?: Usuario;
    media?: Media[];
    complementos_pago?: CostosComplementoPago[];
    created_at: string;
    updated_at: string;
};

export type CostosTipoRubro = {
    id: number;
    descripcion: string;
    rubros_count?: number;
    created_at: string;
    updated_at: string;
};

// ===== Cotización (cotiz_) =====

export type CotizUnidad = {
    id: number;
    descripcion: string;
    created_at: string;
    updated_at: string;
};

export type CotizCentroCosto = {
    id: number;
    cod_coste: string;
    concepto: string;
    created_at: string;
    updated_at: string;
};

export type CotizCategoriaTarjeta = {
    id: number;
    descripcion: string;
    orden: number;
    created_at: string;
    updated_at: string;
};

export type CotizInsumo = {
    id: number;
    descripcion: string;
    codigo_stumis: string | null;
    unidad_id: number;
    precio_unitario: string;
    peso_lineal: string | null;
    peso_default: string | null;
    centro_costo_id: number;
    categoria_tarjeta_id: number | null;
    unidad?: CotizUnidad;
    centro_costo?: CotizCentroCosto;
    categoria_tarjeta?: CotizCategoriaTarjeta;
    created_at: string;
    updated_at: string;
};

export type CotizMerma = {
    id: number;
    descripcion: string;
    formula: string;
    created_at: string;
    updated_at: string;
};

export type CotizFactor = {
    id: number;
    codigo: string;
    nombre: string;
    insumo_id: number;
    formula: string | null;
    descripcion: string | null;
    categoria_tarjeta_id: number | null;
    insumo?: CotizInsumo;
    categoria_tarjeta?: CotizCategoriaTarjeta;
    created_at: string;
    updated_at: string;
};

export type CotizPinturaFormula = {
    id: number;
    clave: string;
    nombre: string;
    formula: string;
    orden: number;
    created_at: string;
    updated_at: string;
};

export type CotizTipoCorte = 'TIRAS' | 'RAZ' | 'KG' | 'CNX';

export type CotizKilosRealesCategoria = {
    id: number;
    descripcion: string;
    tipo_corte: CotizTipoCorte;
    orden: number;
    created_at: string;
    updated_at: string;
};

export type CotizCuadrilla = {
    id: number;
    codigo: string;
    nombre: string;
    centro_costo_id: number;
    insumo_id: number | null;
    rendimiento: string | null;
    formula_costo: string | null;
    descripcion: string | null;
    centro_costo?: CotizCentroCosto;
    insumo?: CotizInsumo;
    created_at: string;
    updated_at: string;
};

export type CotizPersonalCategoria = {
    id: number;
    codigo: string;
    nombre: string;
    sueldo_semanal: string;
    orden: number;
    created_at: string;
    updated_at: string;
};

export type CotizFaseMontaje = {
    id: number;
    codigo: string;
    nombre: string;
    unidad: string;
    centro_costo_id: number | null;
    orden: number;
    centro_costo?: CotizCentroCosto;
    created_at: string;
    updated_at: string;
};

export type CotizGrupoFlete =
    | 'VIATICOS'
    | 'SUPERV_MONTAJE'
    | 'ENERGIA'
    | 'VARIOS'
    | 'FLETES'
    | 'GRUAS'
    | 'PLATAFORMAS'
    | 'LABORATORIO'
    | 'TOPOGRAFIA';

export type CotizMetodoFleteEstandar = 'por_kg' | 'por_piezas';

export type CotizFleteViaticoCatalogo = {
    id: number;
    grupo: CotizGrupoFlete;
    orden: number;
    concepto: string;
    unidad: string | null;
    p_unit_default: string;
    notas: string | null;
    clave: string | null;
    formula_cantidad: string | null;
    formula_p_unit: string | null;
    created_at: string;
    updated_at: string;
};

export type CotizResumenBloque = 'MO_FAB' | 'MO_MONTAJE' | 'EXTRAS' | 'TOTALES';

export type CotizResumenTipoFormula =
    | 'materiales'
    | 'por_kg'
    | 'por_m2_pintura'
    | 'mo_fab_subgrupo'
    | 'flete_kg_prorrateado'
    | 'viatico_m2_prorrateado'
    | 'subtotal'
    | 'margen'
    | 'total';

export type CotizResumenFila = {
    id: number;
    descripcion: string;
    bloque: CotizResumenBloque;
    tipo_formula: CotizResumenTipoFormula;
    coef_default: string | null;
    referencia_extra: string | null;
    orden: number;
    bloqueada: boolean;
    created_at: string;
    updated_at: string;
};

export type CotizObra = {
    id: number;
    nombre: string;
    op: string | null;
    factor_contratista: string;
    num_grupos: number;
    generadoras_count?: number;
    created_at: string;
    updated_at: string;
};

export type CotizLockUser = {
    id: string;
    name: string;
};

export type CotizGeneradora = {
    id: number;
    obra_id: number;
    titulo: string;
    orden: number;
    registros_count?: number;
    is_locked?: boolean;
    locked_by?: CotizLockUser | null;
    locked_at?: string | null;
    obra?: CotizObra;
    created_at: string;
    updated_at: string;
};

export type CotizGeneradoraRegistro = {
    id: number;
    generadora_id: number;
    material_origen_id: number | null;
    material: string | null;
    marca: string | null;
    ancho: string | null;
    largo: string | null;
    cantidad: string | null;
    cant_pzas: string | null;
    peso_porcentual: string | null;
    kilos_totales: string | null;
    merma_id: number;
    validado: boolean;
    t_ml_m2: number | null;
    kilos_reales: number | null;
    kilos_con_merma: number;
    material_origen?: CotizInsumo | null;
    merma?: CotizMerma;
};

export type CotizObraInsumoOverride = {
    id: number;
    obra_id: number;
    insumo_id: number;
    descripcion: string | null;
    codigo_stumis: string | null;
    unidad_id: number | null;
    precio_unitario: string | null;
    peso_lineal: string | null;
    peso_default: string | null;
    centro_costo_id: number | null;
    comentario: string | null;
};

export type CotizObraFactorOverride = {
    id: number;
    obra_id: number;
    factor_id: number;
    nombre: string | null;
    insumo_id: number | null;
    formula: string | null;
    descripcion: string | null;
    comentario: string | null;
};

export type CotizCatalogoInsumoRow = {
    insumo: CotizInsumo;
    override: CotizObraInsumoOverride | null;
};

export type CotizCatalogoFactorRow = {
    factor: CotizFactor;
    override: CotizObraFactorOverride | null;
};

export type CotizTarjeta = {
    id: number;
    obra_id: number;
    descripcion: string;
    orden: number;
    registros_count?: number;
    generadoras_count?: number;
    importe_materiales: string | null;
    kilos_reales: string | null;
    is_locked?: boolean;
    locked_by?: CotizLockUser | null;
    locked_at?: string | null;
    obra?: CotizObra;
    generadoras?: Pick<CotizGeneradora, 'id' | 'titulo' | 'orden'>[];
};

/** Registro de tarjeta ya resuelto por el TarjetaCalculator (no es un modelo crudo). */
export type CotizTarjetaRegistroResuelto = {
    id: number;
    es_manual: boolean;
    generadora_titulo: string | null;
    marca: string | null;
    insumo_id: number | null;
    descripcion: string;
    codigo_stumis: string | null;
    unidad: string | null;
    categoria: string | null;
    categoria_orden: number;
    peso_lineal: number | null;
    kilos_reales: number | null;
    tipo_pintura: string;
    cantidad: number;
    precio_unitario: number;
    precio_obra: number;
    precio_global: number;
    importe: number;
    importe_sugerido: number;
    area_pintura: number;
    validado: boolean;
};

export type CotizTarjetaFactorResuelto = {
    id: number;
    factor_id: number;
    codigo: string;
    nombre: string;
    formula: string | null;
    formula_global: string | null;
    categoria: string | null;
    categoria_orden: number;
    cantidad: number;
    precio_unitario: number;
    precio_obra: number;
    precio_global: number;
    importe: number;
    importe_sugerido: number;
    validado: boolean;
};

export type CotizTarjetaEstructura = {
    id: number;
    nombre: string;
    orden: number;
};

export type CotizTarjetaCategoriaKilos = {
    id: number;
    categoria_id: number;
    descripcion: string | null;
    tipo_corte: string | null;
    porcentual: string | null;
    orden: number;
};

export type CotizTarjetaKilosCelda = {
    id: number;
    categoria_id: number;
    estructura_id: number;
    kilos: string;
};

export type CostosRubro = {
    id: number;
    codigo: string;
    descripcion: string;
    ambito: 'obra' | 'planta';
    ocultar_en_reporte: boolean;
    tipo_rubro_id: number;
    departamento_id: number | null;
    tipo_rubro?: CostosTipoRubro;
    departamento?: Departamento;
    created_at: string;
    updated_at: string;
};

export type CostosTipoSolicitud = {
    id: number;
    titulo: string;
    descripcion: string | null;
    rubros: boolean;
    saltar_verificacion_costos: boolean;
    documentos?: CostosDocumento[];
    documentos_count?: number;
    created_at: string;
    updated_at: string;
};

export type CostosDocumento = {
    id: number;
    tipo_solicitud_id: number;
    titulo: string;
    multiple: boolean;
    opcional: boolean;
    texto: string | null;
    texto_adicional: boolean;
    created_at: string;
    updated_at: string;
};

/** Un cargo vivo a un centro de costo del presupuesto (HistorialDeCargos). */
export type CostosCargoHistorial = {
    id: number;
    fecha: string | null;
    centro: { codigo: string | null; descripcion: string | null };
    descripcion: string | null;
    afectacion: 'ejercido' | 'apartado';
    /** El documento se canceló y el ledger ya revirtió el cargo. */
    revertido: boolean;
    monto: number;
    moneda: string | null;
    monto_origen: number | null;
    documento: { tipo: 'requisicion' | 'afectacion'; id: number; folio: string | null } | null;
    orden_compra: { id: number; folio: string | null } | null;
    solicitudes_pago: { id: number; folio: string | null; estatus: string }[];
};

export type CostosObraRubro = {
    id: number;
    presupuesto_id: number;
    obra_id: number | null;
    rubro_id: number;
    presupuestado: number;
    acumulado: number;
    apartado: number;
    comprometido?: number;
    disponible?: number;
    rubro?: CostosRubro;
    obra?: Obra;
    presupuesto?: CostosPresupuesto;
    created_at: string;
    updated_at: string;
};

export type PresupuestableTipo = 'proyecto' | 'obra' | 'partida';

export type CostosPresupuestoEstatus = 'activo' | 'cerrado';

export type CostosPresupuesto = {
    id: number;
    presupuestable_type: string;
    presupuestable_id: number;
    nombre_interno: string | null;
    op_interno: string | null;
    estatus: CostosPresupuestoEstatus;
    nombre_mostrar?: string;
    op_mostrar?: string | null;
    descripcion_mostrar?: string | null;
    presupuestable?: { id: number; no?: string | null; descripcion?: string | null; estatus?: string };
    created_at: string;
    updated_at: string;
};

/** Opción de presupuesto para el selector de cabecera de requisición. */
export type PresupuestoOption = {
    id: number;
    label: string;
    cerrado: boolean;
};

/** Fila de presupuesto presentada por PresupuestoController (index/edit). */
export type PresupuestoRow = {
    id: number;
    tipo: PresupuestableTipo;
    presupuestable_id: number;
    nombre: string;
    nombre_interno: string | null;
    op: string | null;
    op_interno: string | null;
    no: string | null;
    descripcion: string | null;
    estatus: CostosPresupuestoEstatus;
    es_planta: boolean;
    rubros_count: number;
    sum_presupuestado: number;
    sum_acumulado: number;
    sum_apartado?: number;
    /**
     * El PDF autorizado del presupuesto, cuando ya se cargó. Es uno solo: al
     * subir otro reemplaza al anterior.
     */
    documento: { nombre: string | null; path: string } | null;
};

export type CostosPermiso = {
    id: number;
    descripcion: string;
    nivel: number;
    tipo_aprobacion: 'solicitud_pago' | 'requisicion';
    omitir_si_presupuesto_reservado: boolean;
    es_costos: boolean;
    created_at: string;
    updated_at: string;
};

export type CostosProductoPrecio = {
    id: number;
    producto_id: number;
    proveedor_id: number | null;
    precio: number | string;
    moneda: CostosTipoMoneda;
    fecha: string;
    requisicion_id: number | null;
    proveedor?: Pick<Proveedor, 'id' | 'razon_social'>;
    created_at: string;
};

export type CostosProducto = {
    id: number;
    codigo: string | null;
    descripcion: string;
    unidad: string;
    activo: boolean;
    creado_por: string | null;
    precios?: CostosProductoPrecio[];
    precios_count?: number;
    creador?: Pick<Usuario, 'id' | 'name'>;
    /**
     * El artículo con el que Almacén lo guarda. `null` es que no lleva kardex:
     * un servicio, un flete, o algo que nadie ha clasificado todavía.
     */
    articulo?: { id: number; codigo: string | null; descripcion: string } | null;
    created_at: string;
    updated_at: string;
};

export type CostosAprobacionDepartamento = {
    id: number;
    departamento_id: number;
    permiso_id: number;
    aprobador_id: string | null;
    permiso?: CostosPermiso;
    departamento?: Departamento;
    aprobador?: Usuario;
    created_at: string;
    updated_at: string;
};

export type CostosSolicitudPagoEstatus = 'borrador' | 'pendiente_firma' | 'aprobada' | 'pagada' | 'cancelada';

export const SOLICITUD_PAGO_ESTATUS_LABELS: Record<CostosSolicitudPagoEstatus, string> = {
    borrador: 'Borrador',
    pendiente_firma: 'Pendiente Firma',
    aprobada: 'Aprobada',
    pagada: 'Pagada',
    cancelada: 'Cancelada',
};

export const SOLICITUD_PAGO_ESTATUS_COLORS: Record<CostosSolicitudPagoEstatus, string> = {
    borrador: 'badge-ghost',
    pendiente_firma: 'badge-warning',
    aprobada: 'badge-success',
    pagada: 'badge-info',
    cancelada: 'badge-error',
};

export type CostosTipoMoneda = 'mxn' | 'usd' | 'eur';

export const TIPO_MONEDA_LABELS: Record<CostosTipoMoneda, string> = {
    mxn: 'MXN',
    usd: 'USD',
    eur: 'EUR',
};

// Devoluciones a proveedor (Fase 13)
export type CostosDevolucionEstatus = 'vigente' | 'cancelada';

export const DEVOLUCION_ESTATUS_LABELS: Record<CostosDevolucionEstatus, string> = {
    vigente: 'Vigente',
    cancelada: 'Cancelada',
};

export const DEVOLUCION_ESTATUS_COLORS: Record<CostosDevolucionEstatus, string> = {
    vigente: 'badge-success',
    cancelada: 'badge-error',
};

export type CostosDevolucion = {
    id: number;
    folio: string;
    entrega_detalle_id: number;
    cantidad: number;
    motivo: string;
    fecha: string;
    estatus: CostosDevolucionEstatus;
    motivo_cancelacion: string | null;
    creado_por: string | null;
    entrega_detalle?: {
        id: number;
        cantidad_recibida: number;
        entrega?: { id: number; orden_compra_id: number; fecha_entrega: string; orden_compra?: Pick<CostosOrdenCompra, 'id' | 'folio'> & { proveedor?: Pick<Proveedor, 'id' | 'razon_social'> } };
        orden_compra_detalle?: Pick<CostosOrdenCompraDetalle, 'id' | 'descripcion' | 'unidad'>;
    };
    creador?: Pick<Usuario, 'id' | 'name'>;
    media?: Media[];
    evidencia?: Media | null;
    activities?: CostosActivity[];
    created_at: string;
    updated_at: string;
};

// Notas de crédito (Fase 12)
export type CostosNotaCreditoEstatus = 'vigente' | 'cancelada';

export const NOTA_CREDITO_ESTATUS_LABELS: Record<CostosNotaCreditoEstatus, string> = {
    vigente: 'Vigente',
    cancelada: 'Cancelada',
};

export const NOTA_CREDITO_ESTATUS_COLORS: Record<CostosNotaCreditoEstatus, string> = {
    vigente: 'badge-success',
    cancelada: 'badge-error',
};

export type CostosNotaCredito = {
    id: number;
    folio: string;
    factura_id: number;
    uuid_fiscal: string | null;
    folio_fiscal: string | null;
    subtotal: number;
    iva_trasladado: number;
    monto: number;
    impuestos_detalle: CostosImpuestosDetalle | null;
    concepto: string;
    fecha_emision: string;
    estatus: CostosNotaCreditoEstatus;
    motivo_cancelacion: string | null;
    creado_por: string | null;
    factura?: Pick<CostosFactura, 'id' | 'folio' | 'total' | 'proveedor_id'> & { proveedor?: Pick<Proveedor, 'id' | 'razon_social'> };
    creador?: Pick<Usuario, 'id' | 'name'>;
    media?: Media[];
    media_xml?: Media | null;
    media_pdf?: Media | null;
    activities?: CostosActivity[];
    created_at: string;
    updated_at: string;
};

// Anticipos (Fase 11)
export type CostosAnticipoEstatus = 'vigente' | 'agotado' | 'cancelado';

export const ANTICIPO_ESTATUS_LABELS: Record<CostosAnticipoEstatus, string> = {
    vigente: 'Vigente',
    agotado: 'Agotado',
    cancelado: 'Cancelado',
};

export const ANTICIPO_ESTATUS_COLORS: Record<CostosAnticipoEstatus, string> = {
    vigente: 'badge-success',
    agotado: 'badge-neutral',
    cancelado: 'badge-error',
};

export type CostosAnticipo = {
    id: number;
    folio: string;
    proveedor_id: number;
    obra_id: number | null;
    monto: number;
    saldo_disponible: number;
    moneda: string;
    estatus: CostosAnticipoEstatus;
    referencia: string | null;
    fecha: string;
    notas: string | null;
    creado_por: string | null;
    locked_by: string | null;
    locked_at: string | null;
    proveedor?: Pick<Proveedor, 'id' | 'razon_social' | 'nombre_comercial'>;
    obra?: { id: number; descripcion: string };
    creador?: Pick<Usuario, 'id' | 'name'>;
    aplicaciones?: CostosAnticipoAplicacion[];
    pago?: CostosPago;
    activities?: CostosActivity[];
    created_at: string;
    updated_at: string;
};

export type CostosAnticipoAplicacion = {
    id: number;
    anticipo_id: number;
    factura_id: number;
    monto: number;
    fecha: string;
    usuario_id: string | null;
    notas: string | null;
    factura?: Pick<CostosFactura, 'id' | 'folio' | 'total'>;
    anticipo?: Pick<CostosAnticipo, 'id' | 'folio' | 'monto'>;
    usuario?: Pick<Usuario, 'id' | 'name'>;
    created_at: string;
    updated_at: string;
};

// Requisiciones (Fase 10.2)
export type CostosRequisicionEstatus =
    | 'borrador'
    | 'pendiente_aprobacion_interno'
    | 'aprobada_interna'
    | 'pendiente_aprobacion'
    | 'aprobada'
    | 'rechazada'
    | 'liberada'
    | 'cancelada';

export const REQUISICION_ESTATUS_LABELS: Record<CostosRequisicionEstatus, string> = {
    borrador: 'Borrador',
    pendiente_aprobacion_interno: 'Pendiente de aprobación interna',
    aprobada_interna: 'Aprobada interna',
    pendiente_aprobacion: 'Pendiente de aprobación',
    aprobada: 'Aprobada',
    rechazada: 'Rechazada',
    liberada: 'Liberada',
    cancelada: 'Cancelada',
};

export const REQUISICION_ESTATUS_COLORS: Record<CostosRequisicionEstatus, string> = {
    borrador: 'badge-ghost',
    pendiente_aprobacion_interno: 'badge-secondary',
    aprobada_interna: 'badge-info',
    pendiente_aprobacion: 'badge-warning',
    aprobada: 'badge-success',
    rechazada: 'badge-error',
    liberada: 'badge-primary',
    cancelada: 'badge-neutral',
};

export type ModoPago = 'contado' | 'credito';

export const MODO_PAGO_LABELS: Record<ModoPago, string> = {
    contado: 'Contado',
    credito: 'Crédito',
};

export type ObraRubroOption = {
    id: number;
    presupuesto_id: number;
    presupuesto_label: string;
    rubro_label: string;
    label: string;
    presupuestado: number;
    acumulado: number;
    apartado: number;
    comprometido: number;
    disponible: number;
    sobregiro: boolean;
    cerrado: boolean;
};

export type CostosUsoCfdi = {
    id: number;
    clave: string;
    descripcion: string;
    activo: boolean;
    requisicion_detalles_count?: number;
    created_at: string;
    updated_at: string;
};

export type CostosRequisicion = {
    id: number;
    folio: string;
    solicitante_id: string;
    firma_adicional_aprobador_id: string | null;
    departamento_id: number;
    obra_id: number | null;
    presupuesto_id: number | null;
    /** "Sin obra": no carga a ningún centro de costos ni afecta presupuesto. */
    sin_centro_costos: boolean;
    justificacion: string | null;
    tipo_cambio: number;
    fecha_requerida: string | null;
    estatus: CostosRequisicionEstatus;
    control_por: string | null;
    control_at: string | null;
    modo_dedazo: boolean;
    sobre_obra_cerrada: boolean;
    motivo_rechazo: string | null;
    locked_by: string | null;
    locked_at: string | null;
    solicitante?: Pick<Usuario, 'id' | 'name'>;
    firma_adicional_aprobador?: Pick<Usuario, 'id' | 'name'> | null;
    controlador?: Pick<Usuario, 'id' | 'name'> | null;
    departamento?: Pick<Departamento, 'id' | 'descripcion'>;
    obra?: { id: number; no: number | null; descripcion: string };
    presupuesto?: CostosPresupuesto;
    detalles?: CostosRequisicionDetalle[];
    cotizacion_opciones?: CostosRequisicionCotizacionOpcion[];
    aprobaciones?: CostosAprobacionSolicitud[];
    ocs?: CostosRequisicionOc[];
    ordenes_generadas?: Array<Pick<CostosOrdenCompra, 'id' | 'folio' | 'proveedor_id' | 'total' | 'estatus'> & { proveedor?: Pick<Proveedor, 'id' | 'razon_social'>; solicitudes_pago?: Array<Pick<CostosSolicitudPago, 'id' | 'folio' | 'estatus' | 'monto_total'>> }>;
    mejor_proveedor?: {
        id: number;
        razon_social: string;
        nombre_comercial: string | null;
        /** Siempre 'mxn': el mejor precio se compara y se suma convertido a pesos. */
        moneda: string;
        /** Mejor precio en MXN (las cotizaciones en divisa ya van al TC del documento). */
        total: number;
        /** Hay cotizaciones en divisa y la requisición no tiene tipo de cambio: `total` quedó sin convertir. */
        falta_tc: boolean;
    } | null;
    proveedores_cotizadores_count?: number;
    // Un renglón por OC adjudicada (grupo proveedor+numero_oc); vacío mientras no haya selecciones.
    ocs_resumen?: CostosRequisicionOcResumen[];
    // Neto a pagar (subtotal + IVA - retenciones) en MXN, con las OCs en divisa
    // ya convertidas al TC del documento; 0 mientras no haya OC definida.
    total_neto?: number;
    tiene_sobregiro?: boolean;
    media?: Media[];
    activities?: CostosActivity[];
    created_at: string;
    updated_at: string;
};

export type CostosRequisicionOcResumen = {
    numero_oc: number;
    /** Folio de la OC generada; null mientras la requisición no se libera. */
    folio: string | null;
    proveedor_id: number;
    razon_social: string;
    nombre_comercial: string | null;
    /** Divisa de esa OC; una OC no mezcla monedas. */
    moneda: string;
    /** Neto a pagar de esa OC (subtotal + IVA - retenciones), en `moneda`. */
    total: number;
};

export type CostosRequisicionDetalle = {
    id: number;
    requisicion_id: number;
    producto_id: number | null;
    obra_rubro_id: number | null;
    uso_cfdi_id: number | null;
    tipo_fiscal: CostosTipoFiscalPartida;
    descripcion: string;
    codigo_producto: string | null;
    unidad: string;
    cantidad: number;
    /** Partida de referencia (ej. flete variable): se cotiza pero no se adjudica, no entra al comparativo/PDF ni al neto. */
    solo_cotizacion: boolean;
    /** Partida exenta: suma al subtotal pero no causa IVA ni entra a la base de retenciones. */
    sin_impuestos: boolean;
    notas: string | null;
    uso_cfdi?: Pick<CostosUsoCfdi, 'id' | 'clave' | 'descripcion'>;
    obra_rubro?: {
        id: number;
        presupuestado: number | string;
        acumulado: number | string;
        obra?: { id: number; descripcion: string };
        presupuesto?: CostosPresupuesto;
        rubro?: { id: number; codigo: string; descripcion: string };
    };
    cotizaciones?: CostosRequisicionCotizacionPrecio[];
    selecciones?: CostosRequisicionSeleccion[];
    created_at: string;
    updated_at: string;
};

export type CostosRequisicionCotizacionPrecio = {
    id: number;
    requisicion_detalle_id: number;
    proveedor_id: number;
    opcion_id: number | null;
    precio_unitario: number;
    descripcion: string | null;
    codigo_producto: string | null;
    moneda: CostosTipoMoneda;
    tiempo_entrega_dias: number | null;
    observaciones: string | null;
    media_id: number | null;
    proveedor?: Pick<Proveedor, 'id' | 'razon_social' | 'nombre_comercial'>;
    created_at: string;
    updated_at: string;
};

export type CostosRequisicionCotizacionOpcion = {
    id: number;
    requisicion_id: number;
    proveedor_id: number;
    etiqueta: string | null;
    orden: number;
    proveedor?: Pick<Proveedor, 'id' | 'razon_social' | 'nombre_comercial'>;
    created_at: string;
    updated_at: string;
};

export type CostosRequisicionOcPago = {
    porcentaje: number;
    concepto: string | null;
};

export type CostosRequisicionOc = {
    id: number;
    requisicion_id: number;
    proveedor_id: number;
    numero_oc: number;
    modo_pago: ModoPago;
    metodo_pago: 'transferencia' | 'cheque' | 'efectivo';
    fecha_entrega: string | null;
    fecha_pago: string | null;
    notas: string | null;
    pagos: CostosRequisicionOcPago[] | null;
    created_at: string;
    updated_at: string;
};

export type CostosRequisicionSeleccion = {
    id: number;
    requisicion_detalle_id: number;
    cotizacion_precio_id: number;
    numero_oc: number;
    proveedor_id: number;
    cantidad: number;
    obra_rubro_id: number | null;
    orden_compra_detalle_id: number | null;
    cotizacion_precio?: CostosRequisicionCotizacionPrecio;
    proveedor?: Pick<Proveedor, 'id' | 'razon_social'>;
    created_at: string;
    updated_at: string;
};

export type CostosSolicitudPago = {
    id: number;
    folio: string;
    solicitante_id: string;
    firma_adicional_aprobador_id: string | null;
    departamento_id: number;
    proveedor_id: number | null;
    orden_compra_id: number | null;
    tipo_solicitud_id: number;
    concepto: string;
    comentarios: string | null;
    monto_total: number;
    tipo_pago: string;
    tipo_moneda: CostosTipoMoneda;
    fecha_pago_solicitada: string | null;
    fecha_pago_realizada: string | null;
    referencia_pago: string | null;
    estatus: CostosSolicitudPagoEstatus;
    confirmada_costos: boolean;
    confirmada_costos_por: string | null;
    confirmada_costos_at: string | null;
    confirmada_contabilidad: boolean;
    confirmada_contabilidad_por: string | null;
    confirmada_contabilidad_at: string | null;
    solicitante?: Usuario;
    firma_adicional_aprobador?: Pick<Usuario, 'id' | 'name'> | null;
    departamento?: Departamento;
    proveedor?: Proveedor;
    tipo_solicitud?: CostosTipoSolicitud;
    orden_compra?: Pick<CostosOrdenCompra, 'id' | 'folio'>;
    detalles?: CostosSolicitudPagoDetalle[];
    archivos?: CostosSolicitudArchivo[];
    aprobaciones?: CostosAprobacionSolicitud[];
    pago?: CostosPago;
    media?: Media;
    confirmador_costos?: Usuario;
    confirmador_contabilidad?: Usuario;
    tiene_sobregiro?: boolean;
    puede_reasignar?: boolean;
    activities?: CostosActivity[];
    locked_by: string | null;
    locked_at: string | null;
    locked_by_user?: Pick<Usuario, 'id' | 'name'> | null;
    created_at: string;
    updated_at: string;
};

/**
 * Fila de la bandeja "Por confirmar" (puntos de control Costos/Contabilidad).
 * Forma normalizada que sirve tanto para Solicitudes de Pago como Facturas.
 */
export type CostosPuntoControl = {
    tipo: 'solicitud_pago' | 'factura';
    paso: 'costos' | 'contabilidad';
    id: number;
    folio: string;
    /** Razón social del proveedor. */
    proveedor: string | null;
    /** Nombre comercial; puede faltar y entonces sólo se muestra la razón social. */
    proveedor_comercial: string | null;
    concepto: string | null;
    monto: number;
    moneda: string;
    fecha: string | null;
    accion_url: string;
    detalle_href: string;
};

export type CostosSolicitudPagoDetalle = {
    id: number;
    solicitud_id: number;
    obra_rubro_id: number;
    sobre_obra_cerrada: boolean;
    concepto: string;
    cantidad: number;
    precio_unitario: number;
    subtotal: number;
    obra_rubro?: CostosObraRubro;
    created_at: string;
    updated_at: string;
};

export type CostosSolicitudArchivo = {
    id: number;
    solicitud_id: number;
    media_id: number;
    archivo_id: number;
    texto_adicional: string | null;
    tags: Record<string, string> | null;
    documento?: CostosDocumento;
    media?: Media;
    created_at: string;
    updated_at: string;
};

export type CostosAprobacionSolicitud = {
    id: number;
    solicitud_id: number;
    nivel: number;
    es_adicional?: boolean;
    aprobador_id: string | null;
    estatus: string;
    fecha_respuesta: string | null;
    observaciones: string | null;
    ip: string | null;
    hostname: string | null;
    aprobador?: Usuario;
    // Discriminador para la bandeja polimórfica
    tipo?: 'solicitud_pago' | 'requisicion';
    aprobable_type?: string;
    aprobable_id?: number;
    solicitud?: CostosSolicitudPago;
    requisicion?: CostosRequisicion;
    // Total neto a pagar de la requisición (subtotal + IVA - retenciones)
    requisicion_total?: number;
    created_at: string;
    updated_at: string;
};

// Afectaciones Presupuestales Types
export type CostosAfectacionEstatus = 'borrador' | 'pendiente_firma' | 'aprobada' | 'cancelada';

export const AFECTACION_ESTATUS_LABELS: Record<CostosAfectacionEstatus, string> = {
    borrador: 'Borrador',
    pendiente_firma: 'Pendiente Firma',
    aprobada: 'Aprobada',
    cancelada: 'Cancelada',
};

export const AFECTACION_ESTATUS_COLORS: Record<CostosAfectacionEstatus, string> = {
    borrador: 'badge-ghost',
    pendiente_firma: 'badge-warning',
    aprobada: 'badge-success',
    cancelada: 'badge-error',
};

export type CostosAfectacionPresupuestal = {
    id: number;
    folio: string;
    fecha: string;
    tipo_origen: string;
    descripcion: string;
    monto_total: number;
    estatus: CostosAfectacionEstatus;
    proveedor_id: number | null;
    departamento_id: number | null;
    creado_por: string | Usuario;
    aprobado_por: string | Usuario | null;
    fecha_aprobacion: string | null;
    pdf_formato_path: string | null;
    pdf_firmado_path: string | null;
    departamento?: Departamento;
    proveedor?: Proveedor;
    media?: Media[];
    detalles?: CostosAfectacionDetalle[];
    historial?: CostosAfectacionHistorial[];
    rubros_afectados?: CostosRubroAfectado[];
    locked_by: string | null;
    locked_at: string | null;
    locked_by_user?: Pick<Usuario, 'id' | 'name'> | null;
    created_at: string;
    updated_at: string;
};

export type CostosAfectacionDetalle = {
    id: number;
    afectacion_id: number;
    obra_rubro_id: number;
    concepto: string | null;
    cantidad: number | null;
    precio_unitario: number | null;
    monto: number;
    obra_rubro?: CostosObraRubro;
    created_at: string;
    updated_at: string;
};

export type CostosAfectacionHistorial = {
    id: number;
    afectacion_id: number;
    estatus_anterior: string;
    estatus_nuevo: string;
    fecha: string;
    usuario_id: string | null;
    observaciones: string | null;
    usuario?: Usuario;
    created_at: string;
    updated_at: string;
};

export type CostosRubroAfectadoEstatus = 'apartado' | 'aplicado' | 'vencido' | 'cancelado';

export const RUBRO_AFECTADO_ESTATUS_LABELS: Record<CostosRubroAfectadoEstatus, string> = {
    apartado: 'Apartado',
    aplicado: 'Aplicado',
    vencido: 'Vencido',
    cancelado: 'Cancelado',
};

export const RUBRO_AFECTADO_ESTATUS_BADGE: Record<CostosRubroAfectadoEstatus, string> = {
    apartado: 'badge badge-info badge-outline',
    aplicado: 'badge badge-success badge-outline',
    vencido: 'badge badge-warning badge-outline',
    cancelado: 'badge badge-ghost',
};

export type CostosRubroAfectado = {
    id: number;
    entrada_type: string;
    entrada_id: number;
    obra_rubro_id: number;
    monto: number;
    sobre_giro: boolean;
    descripcion: string | null;
    tipo_movimiento: string;
    estatus: CostosRubroAfectadoEstatus;
    apartado_hasta: string | null;
    vencido_at: string | null;
    usuario_aplica_id: string | null;
    fecha_aplicacion: string | null;
    obra_rubro?: CostosObraRubro;
    created_at: string;
    updated_at: string;
};

// Ordenes de Compra Types
export type CostosOrdenCompraEstatus = 'pendiente_entrega' | 'pendiente_factura' | 'pendiente_aprobacion' | 'pendiente_pago' | 'pagada' | 'cancelada';

export type CostosOcEtapaProceso = 'recepcion' | 'espera_factura' | 'validacion_documentos' | 'pago_programado' | 'completada' | 'cancelada';

export const OC_ETAPA_LABELS: Record<CostosOcEtapaProceso, string> = {
    recepcion: 'Recepción',
    espera_factura: 'Espera de factura',
    validacion_documentos: 'Validación de documentos',
    pago_programado: 'Pago programado',
    completada: 'Completada',
    cancelada: 'Cancelada',
};

export const OC_ETAPA_BADGE: Record<CostosOcEtapaProceso, string> = {
    recepcion: 'badge badge-info badge-outline',
    espera_factura: 'badge badge-warning badge-outline',
    validacion_documentos: 'badge badge-accent badge-outline',
    pago_programado: 'badge badge-primary badge-outline',
    completada: 'badge badge-success badge-outline',
    cancelada: 'badge badge-ghost',
};

export const ORDEN_COMPRA_ESTATUS_LABELS: Record<CostosOrdenCompraEstatus, string> = {
    pendiente_entrega: 'Pend. Entrega',
    pendiente_factura: 'Pend. Factura',
    pendiente_aprobacion: 'Pend. Aprobación',
    pendiente_pago: 'Pend. Pago',
    pagada: 'Pagada',
    cancelada: 'Cancelada',
};

export const ORDEN_COMPRA_ESTATUS_COLORS: Record<CostosOrdenCompraEstatus, string> = {
    pendiente_entrega: 'badge-info',
    pendiente_factura: 'badge-warning',
    pendiente_aprobacion: 'badge-accent',
    pendiente_pago: 'badge-primary',
    pagada: 'badge-success',
    cancelada: 'badge-error',
};

export type CostosOrdenCompra = {
    id: number;
    folio: string;
    referencia: string | null;
    proveedor_id: number;
    obra_id: number | null;
    departamento_id: number;
    creado_por: string;
    moneda: CostosTipoMoneda;
    tipo_pago: ModoPago | null;
    dias_credito: number;
    forma_pago: string;
    total: number;
    fecha_entrega_esperada: string;
    notas: string | null;
    estatus: CostosOrdenCompraEstatus;
    retrasada?: boolean;
    etapa_proceso?: CostosOcEtapaProceso;
    monto_recibido?: number;
    porcentaje_recepcion?: number;
    porcentaje_facturacion?: number;
    porcentaje_pago?: number;
    pago_vencido?: boolean;
    tiene_devolucion?: boolean;
    pagada_anticipo_contado?: boolean;
    /** Recibida al total, facturada al total y pagada al total: terminó su vida. */
    completada?: boolean;
    presupuesto_label?: string;
    detalles_count?: number;
    requisicion_id: number | null;
    requisicion?: { id: number; obra?: Pick<Obra, 'id' | 'no' | 'descripcion'> };
    proveedor?: Proveedor;
    obra?: Obra;
    departamento?: Departamento;
    creador?: Usuario;
    detalles?: CostosOrdenCompraDetalle[];
    facturas?: CostosFactura[];
    entregas?: CostosEntrega[];
    solicitudes_pago?: Array<Pick<CostosSolicitudPago, 'id' | 'folio' | 'estatus' | 'pago'>>;
    media?: Media[];
    rubros_afectados?: CostosRubroAfectado[];
    facturas_count?: number;
    entregas_count?: number;
    pagos_count?: number;
    /** Cancelaciones de unidades esperando la firma del jefe de compras. */
    cancelaciones_pendientes_count?: number;
    total_facturado?: number;
    total_pagado?: number;
    saldo_pendiente?: number;
    activities?: CostosActivity[];
    created_at: string;
    updated_at: string;
};

export type CostosOrdenCompraDetalle = {
    id: number;
    orden_compra_id: number;
    requisicion_detalle_id: number | null;
    obra_rubro_id: number;
    tipo_fiscal: CostosTipoFiscalPartida;
    /** Partida exenta: suma al subtotal pero no causa IVA ni entra a la base de retenciones. */
    sin_impuestos: boolean;
    descripcion: string;
    unidad: string;
    cantidad: number;
    /** Unidades que compras dio por canceladas y el jefe de compras autorizó. */
    cantidad_cancelada: number;
    precio_unitario: number;
    subtotal: number;
    uso_cfdi_id: number | null;
    obra_rubro?: CostosObraRubro;
    uso_cfdi?: Pick<CostosUsoCfdi, 'id' | 'clave' | 'descripcion'>;
    cancelaciones?: CostosOcCancelacionUnidades[];
    created_at: string;
    updated_at: string;
};

export type CostosOcCancelacionUnidadesEstatus = 'pendiente' | 'autorizada' | 'rechazada';

export const OC_CANCELACION_ESTATUS_LABELS: Record<CostosOcCancelacionUnidadesEstatus, string> = {
    pendiente: 'Pendiente de autorizar',
    autorizada: 'Autorizada',
    rechazada: 'Rechazada',
};

export const OC_CANCELACION_ESTATUS_COLORS: Record<CostosOcCancelacionUnidadesEstatus, string> = {
    pendiente: 'badge-warning',
    autorizada: 'badge-success',
    rechazada: 'badge-error',
};

/**
 * Unidades canceladas de una partida. No surten efecto hasta que el jefe de
 * compras las autoriza; mientras están pendientes, la orden se reporta como
 * pendiente de aprobación.
 */
export type CostosOcCancelacionUnidades = {
    id: number;
    orden_compra_detalle_id: number;
    cantidad: number;
    motivo: string;
    estatus: CostosOcCancelacionUnidadesEstatus;
    motivo_rechazo: string | null;
    autorizado_at: string | null;
    solicitante?: Pick<Usuario, 'id' | 'name'> | null;
    autorizador?: Pick<Usuario, 'id' | 'name'> | null;
    created_at: string;
    updated_at: string;
};

export type CostosTipoFiscalPartida = 'mercancia' | 'flete' | 'servicio_profesional' | 'renta';

export const TIPO_FISCAL_LABELS: Record<CostosTipoFiscalPartida, string> = {
    mercancia: 'Mercancía',
    flete: 'Flete',
    servicio_profesional: 'Servicio profesional',
    renta: 'Renta',
};

export type CostosRetencion = {
    clave: string;
    concepto: string;
    tasa: number;
    base: number;
    monto: number;
};

export type CostosRetencionDesglose = {
    subtotal: number;
    iva: number;
    total_neto: number;
    retenciones: CostosRetencion[];
};

// Facturas Types
export type CostosFacturaEstatus = 'pendiente_recepcion' | 'pendiente_aprobacion' | 'pendiente_pago' | 'pagada' | 'cancelada';

export type CostosBaseDiasCredito = 'factura' | 'recepcion' | 'aprobacion';

export const BASE_DIAS_CREDITO_LABELS: Record<CostosBaseDiasCredito, string> = {
    factura: 'Fecha de factura',
    recepcion: 'Fecha de recepción',
    aprobacion: 'Fecha de aprobación',
};

// Clasificación canónica de archivos adjuntos del módulo Costos.
// Espejo de App\Enums\Costos\DocumentoTipo.
export type CostosDocumentoTipo =
    | 'xml_factura'
    | 'pdf_factura'
    | 'oc_archivo'
    | 'oc_pdf_formato'
    | 'oc_pdf_firmado'
    | 'evidencia_recepcion'
    | 'comprobante_recepcion'
    | 'comprobante_pago'
    | 'solicitud_archivo'
    | 'solicitud_firmada';

export const DOCUMENTO_TIPO_LABELS: Record<CostosDocumentoTipo, string> = {
    xml_factura: 'XML de factura',
    pdf_factura: 'PDF de factura',
    oc_archivo: 'Archivo de OC',
    oc_pdf_formato: 'Formato de OC (PDF)',
    oc_pdf_firmado: 'OC firmada (PDF)',
    evidencia_recepcion: 'Evidencia de recepción',
    comprobante_recepcion: 'Comprobante de recepción',
    comprobante_pago: 'Comprobante de pago',
    solicitud_archivo: 'Anexo de solicitud',
    solicitud_firmada: 'Solicitud firmada',
};

export const FACTURA_ESTATUS_LABELS: Record<CostosFacturaEstatus, string> = {
    pendiente_recepcion: 'Pendiente Recepción',
    pendiente_aprobacion: 'Pendiente Aprobación',
    pendiente_pago: 'Pendiente Pago',
    pagada: 'Pagada',
    cancelada: 'Cancelada',
};

// Labels de estatus de factura como los ve el proveedor en el portal.
// "pendiente_aprobacion" se muestra como "Contrarecibo pendiente".
export const FACTURA_ESTATUS_LABELS_PORTAL: Record<CostosFacturaEstatus, string> = {
    ...FACTURA_ESTATUS_LABELS,
    pendiente_aprobacion: 'Contrarecibo pendiente',
};

export const FACTURA_ESTATUS_COLORS: Record<CostosFacturaEstatus, string> = {
    pendiente_recepcion: 'badge-warning',
    pendiente_aprobacion: 'badge-accent',
    pendiente_pago: 'badge-primary',
    pagada: 'badge-success',
    cancelada: 'badge-error',
};

// Auditoria (spatie/laravel-activitylog)
export type CostosActivity = {
    id: number;
    log_name: string | null;
    event: string | null;
    description: string;
    attribute_changes: {
        attributes?: Record<string, unknown>;
        old?: Record<string, unknown>;
    } | null;
    /** Propiedades de logs manuales (p. ej. el motivo de una reasignación). */
    properties?: {
        motivo?: string;
        [key: string]: unknown;
    } | null;
    created_at: string;
    causer?: Usuario | null;
};

export type CostosFactura = {
    id: number;
    folio: string;
    orden_compra_id: number;
    proveedor_id: number;
    uuid_fiscal: string | null;
    folio_fiscal: string | null;
    subtotal: number;
    iva: number;
    iva_trasladado: number;
    iva_retenido: number;
    isr_retenido: number;
    impuestos_detalle: CostosImpuestosDetalle | null;
    total: number;
    moneda: string;
    /** Lo que dice el CFDI cuando se timbró en otra moneda que la orden. */
    moneda_cfdi?: string | null;
    total_cfdi?: number | null;
    /** Pesos por unidad de la moneda de la orden, según la factura. */
    tipo_cambio_cfdi?: number | null;
    metodo_pago?: 'PUE' | 'PPD' | null;
    forma_pago?: string | null;
    fecha_factura: string | null;
    estatus: CostosFacturaEstatus;
    completamente_entregada: boolean;
    notas: string | null;
    motivo_rechazo: string | null;
    dias_credito: number | null;
    base_dias_credito: CostosBaseDiasCredito;
    fecha_pago_calculada: string | null;
    aprobada_costos: boolean;
    aprobada_costos_por: string | null;
    aprobada_costos_at: string | null;
    aceptada_contabilidad: boolean;
    aceptada_contabilidad_por: string | null;
    aceptada_contabilidad_at: string | null;
    orden_compra?: CostosOrdenCompra;
    proveedor?: Proveedor;
    entregas?: CostosEntrega[];
    entregas_ligadas?: CostosEntrega[];
    detalles?: CostosFacturaDetalle[];
    media?: Media[];
    media_pdf?: Media | null;
    pago?: CostosPago;
    aprobada_costos_por_usuario?: Usuario;
    aceptada_contabilidad_por_usuario?: Usuario;
    activities?: CostosActivity[];
    locked_by: string | null;
    locked_at: string | null;
    locked_by_user?: Pick<Usuario, 'id' | 'name'> | null;
    cobertura_completa?: boolean;
    cobertura_por_partida?: Record<number, { disponible: number; cubierta: boolean }>;
    anticipos_aplicados?: CostosAnticipoAplicacion[];
    monto_anticipos?: number;
    notas_credito?: CostosNotaCredito[];
    monto_notas_credito?: number;
    saldo_facturado?: number;
    complementos_pago?: CostosComplementoPago[];
    created_at: string;
    updated_at: string;
};

export type CostosComplementoPagoEstatus = 'pendiente' | 'cumplido' | 'vencido';

export const COMPLEMENTO_PAGO_ESTATUS_LABELS: Record<CostosComplementoPagoEstatus, string> = {
    pendiente: 'Pendiente',
    cumplido: 'Cumplido',
    vencido: 'Vencido',
};

export const COMPLEMENTO_PAGO_ESTATUS_COLORS: Record<CostosComplementoPagoEstatus, string> = {
    pendiente: 'badge-warning',
    cumplido: 'badge-success',
    vencido: 'badge-error',
};

export type CostosComplementoPago = {
    id: number;
    folio: string;
    factura_id: number;
    pago_id: number;
    proveedor_id: number;
    monto_pago: number;
    /** Suma de los REP recibidos: un pago se complementa por parcialidades. */
    monto_cubierto: number;
    fecha_pago: string;
    fecha_generacion: string;
    fecha_limite: string;
    estatus: CostosComplementoPagoEstatus;
    /** Último REP aplicado. Uno solo puede cubrir varias obligaciones. */
    complemento_uuid: string | null;
    recibido_at: string | null;
    factura?: Pick<CostosFactura, 'id' | 'folio' | 'uuid_fiscal' | 'total'>;
    proveedor?: Pick<Proveedor, 'id' | 'razon_social'>;
    created_at: string;
    updated_at: string;
};

export type CostosImpuestosDetalle = {
    traslados: Array<{
        impuesto: string;
        tipo_factor: string;
        tasa: string;
        base: number;
        importe: number;
    }>;
    retenciones: Array<{
        impuesto: string;
        importe: number;
    }>;
    total_trasladados: number;
    total_retenidos: number;
};

export type CostosFacturaDetalle = {
    id: number;
    factura_id: number;
    orden_compra_detalle_id: number;
    cantidad: number;
    precio_unitario: number;
    subtotal: number;
    orden_compra_detalle?: CostosOrdenCompraDetalle;
    created_at: string;
    updated_at: string;
};

// Entregas Types
export type CostosEntregaTipo = 'parcial' | 'completa';

export const ENTREGA_TIPO_LABELS: Record<CostosEntregaTipo, string> = {
    parcial: 'Parcial',
    completa: 'Completa',
};

export type CostosEntrega = {
    id: number;
    folio: string | null;
    orden_compra_id: number;
    factura_id: number | null;
    recibido_por: string;
    fecha_entrega: string;
    tipo: CostosEntregaTipo;
    observaciones: string | null;
    completa_factura: boolean;
    cancelada_at: string | null;
    cancelada_por: string | null;
    motivo_cancelacion: string | null;
    media?: Media | null;
    recibidor?: Usuario;
    cancelador?: Usuario;
    detalles?: CostosEntregaDetalle[];
    created_at: string;
    updated_at: string;
};

export type CostosEntregaDetalle = {
    id: number;
    entrega_id: number;
    orden_compra_detalle_id: number;
    cantidad_recibida: number;
    /** Precio recibido capturado en la recepción (para igualar factura); null = al precio de la OC. */
    precio_unitario: number | null;
    observaciones: string | null;
    orden_compra_detalle?: CostosOrdenCompraDetalle;
    devoluciones?: CostosDevolucion[];
    created_at: string;
    updated_at: string;
};

/**
 * Fila del listado global de recepciones (pantalla "Recepciones"). Forma
 * normalizada por el backend con la OC y las Solicitudes de Pago ligadas.
 */
export type CostosRecepcionRow = {
    id: number;
    folio: string | null;
    /** Cuándo se elaboró el documento en el sistema. No se captura ni se edita. */
    fecha_recepcion: string | null;
    /** Fecha operativa de la entrega en planta u obra; la captura quien recibe. */
    fecha_entrega: string | null;
    tipo: CostosEntregaTipo;
    recibido_por: string | null;
    recibido_por_id: string | null;
    observaciones: string | null;
    cancelada: boolean;
    /** Si admite corregir sus datos de captura: ni cancelada ni con factura pagada. */
    puede_editar: boolean;
    /** Importe recibido (sin IVA): cantidad × precio efectivo de cada renglón. */
    total: number;
    oc: { id: number; folio: string; tipo_pago: string | null; moneda: string | null; url: string } | null;
    proveedor: string | null;
    /** Obras a las que carga la recepción, vía OC, su solicitud de pago o su requisición. */
    obras: string[];
    solicitudes_pago: { id: number; folio: string; estatus: string | null; url: string }[];
    factura: { id: number; folio: string | null } | null;
    factura_id: number | null;
    /** Si esta recepción es la que marca la factura como completamente entregada. */
    completa_factura: boolean;
    /** Facturas de la OC a las que se puede re-ligar: las que aún no avanzan, más la actual. */
    facturas_disponibles: { id: number; folio: string | null; total: number; estatus: string | null }[];
    pdf_url: string;
};

// Pagos Types
export type CostosPagoEstatus = 'pendiente' | 'programado' | 'parcial' | 'pagado';

export const PAGO_ESTATUS_LABELS: Record<CostosPagoEstatus, string> = {
    pendiente: 'Pendiente',
    programado: 'Programado',
    parcial: 'Parcial',
    pagado: 'Pagado',
};

export const PAGO_ESTATUS_COLORS: Record<CostosPagoEstatus, string> = {
    pendiente: 'badge-warning',
    programado: 'badge-info',
    parcial: 'badge-accent',
    pagado: 'badge-success',
};

export type CostosPagoTipoPago = 'contado' | 'credito';

export const PAGO_TIPO_PAGO_LABELS: Record<CostosPagoTipoPago, string> = {
    contado: 'Contado',
    credito: 'Crédito',
};

export type CostosPago = {
    id: number;
    folio: string;
    pagable_type: string;
    pagable_id: number;
    monto_pago: number;
    moneda: string;
    tipo_cambio: number;
    tipo_pago: CostosPagoTipoPago;
    fecha_pago_programada: string | null;
    fecha_pago_maxima: string | null;
    fecha_pago_realizada: string | null;
    referencia_pago: string | null;
    estatus: CostosPagoEstatus;
    notas: string | null;
    pago_padre_id: number | null;
    numero_parcialidad: number | null;
    pagable?: CostosSolicitudPago | CostosFactura;
    pago_padre?: CostosPago;
    pagos_parciales?: CostosPago[];
    media?: Media | null;
    created_at: string;
    updated_at: string;
};

// Cobranza Types

export type Cliente = {
    id: number;
    nombre: string;
    rfc: string | null;
    direccion: string | null;
    telefono: string | null;
    email: string | null;
    activo: boolean;
    contacto_principal_id: number | null;
    contacto_principal?: CobContacto;
    contactos?: CobContacto[];
    contactos_count?: number;
    created_at: string;
    updated_at: string;
};

export type CobContacto = {
    id: number;
    cliente_id: number;
    nombre: string;
    email: string | null;
    telefono: string | null;
    cargo: string | null;
    activo: boolean;
    created_at: string;
    updated_at: string;
};

export type CobPartidaTipo = 'suministro' | 'montaje';

export type CobPartida = {
    id: number;
    obra_id: number;
    tipo: CobPartidaTipo;
    estatus: ObraEstatus;
    descripcion: string;
    monto: number;
    moneda: string;
    created_at: string;
    updated_at: string;
};

export type CobEstimacionEstado = 'ingresada' | 'autorizada' | 'facturada' | 'pago_parcial' | 'pagado';

export const COB_ESTIMACION_ESTADO_LABELS: Record<CobEstimacionEstado, string> = {
    ingresada: 'Ingresada',
    autorizada: 'Autorizada',
    facturada: 'Facturada',
    pago_parcial: 'Pago Parcial',
    pagado: 'Pagado',
};

export const COB_ESTIMACION_ESTADO_COLORS: Record<CobEstimacionEstado, string> = {
    ingresada: 'badge-warning',
    autorizada: 'badge-primary',
    facturada: 'badge-secondary',
    pago_parcial: 'badge-warning',
    pagado: 'badge-success',
};

export type CobEstimacionNivel = 'proyecto' | 'obra' | 'partida';

export type CobEstimacion = {
    id: number;
    proyecto_id: number | null;
    obra_id: number | null;
    nivel: CobEstimacionNivel;
    numero_estimacion: number;
    folio: string | null;
    tipo: string | null;
    fecha_emision: string | null;
    inicio: string | null;
    fin: string | null;
    monto_estimado: number;
    monto_total: number;
    monto_pagado: number;
    moneda: string;
    estado: CobEstimacionEstado;
    fecha_ultimo_cambio_estado: string | null;
    comentarios: string | null;
    pagos?: CobEstimacionPago[];
    historial?: CobEstimacionEstadoHistorial[];
    retenciones?: CobRetencion[];
    partidas?: CobPartida[];
    obra?: Obra;
    created_at: string;
    updated_at: string;
};

export type CobEstimacionPago = {
    id: number;
    estimacion_id: number;
    monto_pagado: number;
    fecha_pago: string;
    folio: string | null;
    comprobantes?: Media[];
    created_at: string;
    updated_at: string;
};

export type CobEstimacionEstadoHistorial = {
    id: number;
    estimacion_id: number;
    estado_anterior: string | null;
    estado_nuevo: string;
    folio: string | null;
    usuario_id: string;
    comentario: string | null;
    fecha_cambio: string;
    usuario?: Usuario;
    created_at: string;
    updated_at: string;
};

export type CobAnticipoEstado = 'pendiente' | 'aplicado' | 'devuelto';

export const COB_ANTICIPO_ESTADO_LABELS: Record<CobAnticipoEstado, string> = {
    pendiente: 'Pendiente',
    aplicado: 'Aplicado',
    devuelto: 'Devuelto',
};

export type CobAnticipo = {
    id: number;
    obra_id: number;
    folio: string | null;
    fecha_emision: string | null;
    monto: number;
    moneda: string;
    estado: CobAnticipoEstado;
    comentarios: string | null;
    fecha_pagado: string | null;
    media?: Media | null;
    created_at: string;
    updated_at: string;
};

export type CobAdendaTipo = 'aumento' | 'reduccion' | 'cambio_especificacion' | 'ampliacion_plazo';

export const COB_ADENDA_TIPO_LABELS: Record<CobAdendaTipo, string> = {
    aumento: 'Aumento',
    reduccion: 'Reduccion',
    cambio_especificacion: 'Cambio Especificacion',
    ampliacion_plazo: 'Ampliacion Plazo',
};

export type CobAdendaEstado = 'borrador' | 'en_revision' | 'aprobada' | 'rechazada';

export const COB_ADENDA_ESTADO_LABELS: Record<CobAdendaEstado, string> = {
    borrador: 'Borrador',
    en_revision: 'En Revision',
    aprobada: 'Aprobada',
    rechazada: 'Rechazada',
};

export type CobAdenda = {
    id: number;
    obra_id: number;
    tipo: CobAdendaTipo;
    descripcion: string;
    monto_modificacion: number;
    fecha: string | null;
    estado: CobAdendaEstado;
    created_at: string;
    updated_at: string;
};

export type CobComparativoEstado = 'analisis' | 'aprobado' | 'implementado' | 'descartado';

export const COB_COMPARATIVO_ESTADO_LABELS: Record<CobComparativoEstado, string> = {
    analisis: 'Analisis',
    aprobado: 'Aprobado',
    implementado: 'Implementado',
    descartado: 'Descartado',
};

export type CobComparativo = {
    id: number;
    proyecto_id: number;
    obra_id: number | null;
    descripcion: string;
    monto_impacto: number;
    fecha_identificacion: string | null;
    estado: CobComparativoEstado;
    created_at: string;
    updated_at: string;
};

export type CobDeduccion = {
    id: number;
    obra_id: number;
    descripcion: string;
    monto: number;
    moneda: string;
    fecha: string | null;
    created_at: string;
    updated_at: string;
};

// ICSOE / SIROC (IMSS). Los campos decimales llegan como string desde Laravel.

export type CobIcsoeMetodo = 'superficie' | 'porcentaje';

export const COB_ICSOE_METODO_LABELS: Record<CobIcsoeMetodo, string> = {
    superficie: 'Superficie (Art. 18)',
    porcentaje: 'Porcentaje del contrato',
};

export type CobIcsoeEstatus = 'vigente' | 'pendiente_verificacion' | 'cerrado';

export const COB_ICSOE_ESTATUS_LABELS: Record<CobIcsoeEstatus, string> = {
    vigente: 'Vigente',
    pendiente_verificacion: 'Pendiente de verificación',
    cerrado: 'Cerrado',
};

export type CobIcsoeSbcAnio = {
    id: number;
    anio: number;
    sbc: string;
    costo_m2: string;
    prima_riesgo: string;
    notas: string | null;
    created_at: string;
    updated_at: string;
};

export type CobIcsoeMes = {
    id: number;
    seguimiento_id: number;
    anio: number;
    mes: number;
    dias_proyecto: number;
    sbc: string;
    sbc_aplicado: string;
    mo_estimada: string;
    dias_cotizados: string;
    mo_real: string;
    fuera_de_rango: boolean;
};

export type CobIcsoeSeguimiento = {
    id: number;
    proyecto_id: number;
    metodo: CobIcsoeMetodo;
    estatus: CobIcsoeEstatus;
    fecha_inicio: string;
    fecha_fin: string;
    superficie_m2: string | null;
    costo_m2: string | null;
    porcentaje_mo: string;
    prima_riesgo: string;
    monto_base: string;
    monto_base_anterior: string | null;
    mo_estimada_total: string;
    mo_estimada_total_anterior: string | null;
    mo_estimada_diaria: string;
    total_dias: number;
    mo_real_total: string;
    diferencia_mo: string;
    monto_riesgo: string;
    motivo_cambio: string | null;
    recalculado_at: string | null;
    verificado_at: string | null;
    verificado_por: string | null;
    notas: string | null;
    proyecto?: Proyecto;
    meses?: CobIcsoeMes[];
    verificado_por_usuario?: { id: string; name: string } | null;
    created_at: string;
    updated_at: string;
};

export type CobTipoRetencion = {
    id: number;
    nombre: string;
    descripcion: string | null;
    retenciones_count?: number;
    created_at: string;
    updated_at: string;
};

export type CobRetencion = {
    id: number;
    estimacion_id: number;
    tipo_retencion_id: number;
    monto: number;
    moneda: string;
    tipo_retencion?: CobTipoRetencion;
    created_at: string;
    updated_at: string;
};

export type CobEvento = {
    id: number;
    obra_id: number;
    parent_id: number | null;
    nombre: string;
    monto: number | null;
    inicio: string | null;
    fin: string | null;
    marcado: boolean;
    children?: CobEvento[];
    created_at: string;
    updated_at: string;
};

export type CobDisputaEstado = 'en_proceso' | 'resuelto' | 'cancelado';

export const COB_DISPUTA_ESTADO_LABELS: Record<CobDisputaEstado, string> = {
    en_proceso: 'En Proceso',
    resuelto: 'Resuelto',
    cancelado: 'Cancelado',
};

export type CobDisputa = {
    id: number;
    obra_id: number;
    descripcion: string;
    fecha_inicio: string | null;
    fecha_resolucion: string | null;
    estado: CobDisputaEstado;
    resultado: string | null;
    created_at: string;
    updated_at: string;
};

export type CobPenalizacion = {
    id: number;
    obra_id: number;
    descripcion: string;
    monto: number;
    moneda: string;
    tipo: string | null;
    fecha: string | null;
    created_at: string;
    updated_at: string;
};


export const COB_TIPO_CONTRATO_LABELS: Record<string, string> = {
    precio_alzado: 'Precio Alzado',
    precio_unitario: 'Precio Unitario',
};

// ===================== RH (Recursos Humanos) =====================

export type RhSkill = {
    id: number;
    nombre: string;
    tipo: 'hard' | 'soft';
    pivot?: { nivel_requerido: string };
    created_at: string;
    updated_at: string;
};

export type RhRequerimiento = {
    id: number;
    descripcion: string;
    valor: string | null;
    created_at: string;
    updated_at: string;
};

export type RhPuesto = {
    id: number;
    departamento_id: number;
    nombre: string;
    descripcion: string | null;
    codigo: string | null;
    ubicacion: string | null;
    hora_entrada: string | null;
    hora_salida: string | null;
    puesto_jefe_id: number | null;
    departamento?: Departamento;
    puesto_jefe?: RhPuesto;
    skills?: RhSkill[];
    requerimientos?: RhRequerimiento[];
    actividades?: RhActividad[];
    documentos_puesto?: RhDocumentoPuesto[];
    plantillas_onboarding?: RhOnboardingTareaPlantilla[];
    created_at: string;
    updated_at: string;
};

export type RhOnboardingTareaPlantilla = {
    id: number;
    puesto_id: number;
    titulo: string;
    descripcion: string | null;
    dias_desde_inicio: number | null;
    orden: number;
    created_at: string;
    updated_at: string;
};

export type RhActividad = {
    id: number;
    puesto_id: number;
    descripcion: string;
    created_at: string;
    updated_at: string;
};

export type RhDocumentoPuesto = {
    id: number;
    puesto_id: number;
    nombre_reporte: string;
    frecuencia_entrega: string | null;
    cargo_entrega: string | null;
    created_at: string;
    updated_at: string;
};

export type RhPersona = {
    id: number;
    nombre: string;
    apellido: string;
    email: string | null;
    telefono: string | null;
    fecha_nacimiento: string | null;
    cv_estado: 'pendiente' | 'procesando' | 'procesado' | 'error' | null;
    nombre_completo?: string;
    media?: Media | null;
    foto?: Media | null;
    imss: string | null;
    curp: string | null;
    rfc: string | null;
    numero_ine: string | null;
    estado_civil: string | null;
    hijos: number | null;
    domicilio: string | null;
    cp: string | null;
    localidad: string | null;
    nombre_padre: string | null;
    nombre_madre: string | null;
    cuenta_banco: string | null;
    banco_op: string | null;
    c_infonavit: string | null;
    c_fonacot: string | null;
    tramite_banco: boolean;
    texto_cv: string | null;
    contacto_emergencia_1_nombre: string | null;
    contacto_emergencia_1_telefono: string | null;
    contacto_emergencia_2_nombre: string | null;
    contacto_emergencia_2_telefono: string | null;
    documentos?: RhPersonaDocumento[];
    periodos_laborales?: RhPeriodoLaboral[];
    candidaturas?: RhCandidatura[];
    created_at: string;
    updated_at: string;
};

export type RhPersonaDocumento = {
    id: number;
    persona_id: number;
    media_id: number;
    tipo_documento: string;
    fecha_emision: string | null;
    fecha_vigencia: string | null;
    notas: string | null;
    media?: Media | null;
    created_at: string;
    updated_at: string;
};

export type RhPeriodoLaboral = {
    id: number;
    persona_id: number;
    puesto_id: number | null;
    requisicion_id: number | null;
    fecha_inicio: string;
    fecha_fin: string | null;
    estado: 'activo' | 'baja';
    salario_diario: number | null;
    sueldo_mensual: string | null;
    sueldo_real: number | null;
    periodicidad_pago: 'semanal' | 'catorcenal' | 'quincenal' | 'mensual' | null;
    tipo_salario: 'fijo' | 'destajo' | null;
    tipo_contrato: string | null;
    numero_empleado: string | null;
    numero_locker: string | null;
    tipo_empleado: 'planta' | 'contratista' | 'becario' | 'foraneo' | null;
    motivo_baja: string | null;
    persona?: RhPersona;
    puesto?: RhPuesto;
    requisicion?: RhRequisicion;
    onboarding?: RhOnboarding;
    created_at: string;
    updated_at: string;
};

export type RhRequisicion = {
    id: number;
    folio: string;
    puesto_id: number;
    cantidad: number;
    estado: 'borrador' | 'abierta' | 'en_proceso' | 'cerrada' | 'cancelada';
    tipo_requisicion: 'nueva' | 'reemplazo' | 'temporal';
    tipo_contrato_generado: 'planta' | 'obra';
    procesar_ia: boolean;
    justificacion: string | null;
    nombre_solicitante: string | null;
    puesto_solicitante: string | null;
    responsable_entrevista: string | null;
    salario: number | null;
    fecha_creacion: string | null;
    fecha_cierre: string | null;
    puesto?: RhPuesto;
    extra?: RhRequisicionExtra;
    candidaturas?: RhCandidatura[];
    created_at: string;
    updated_at: string;
};

export type RhRequisicionExtra = {
    id: number;
    requisicion_id: number;
    salario_mensual: number | null;
    salario_diario: number | null;
    periodicidad_pago: string | null;
    prestaciones: string | null;
    bonos: string | null;
    horario: string | null;
    tipo_jornada: string | null;
    beneficios_adicionales: string | null;
    observaciones: string | null;
    created_at: string;
    updated_at: string;
};

export type RhCandidatura = {
    id: number;
    requisicion_id: number;
    persona_id: number;
    fecha_aplicacion: string | null;
    porcentaje_match: number | null;
    porcentaje_skills: number | null;
    porcentaje_requisitos: number | null;
    notas: string | null;
    requisicion?: RhRequisicion;
    persona?: RhPersona;
    created_at: string;
    updated_at: string;
};

export type RhOnboarding = {
    id: number;
    periodo_id: number;
    fecha_inicio: string | null;
    progreso: number;
    periodo?: RhPeriodoLaboral;
    tareas?: RhOnboardingTarea[];
    created_at: string;
    updated_at: string;
};

export type RhOnboardingTarea = {
    id: number;
    onboarding_id: number;
    responsable_periodo_id: number | null;
    titulo: string;
    descripcion: string | null;
    completada: boolean;
    fecha_vencimiento: string | null;
    fecha_completada: string | null;
    media?: Media | null;
    responsable?: RhPeriodoLaboral;
    created_at: string;
    updated_at: string;
};

export type RhPermisoAusencia = {
    id: number;
    folio: string | null;
    numero_empleado: string | null;
    nombres: string;
    apellidos: string;
    departamento: string | null;
    gerente: string | null;
    tipo: string | null;
    modalidad: string | null;
    condicion: string | null;
    razon: string | null;
    fecha_permiso: string | null;
    fecha_elaboracion: string | null;
    created_at: string;
    updated_at: string;
};

// =========================================
// Almacén
// =========================================

export type AlmAlmacenTipo = 'insumos' | 'montaje' | 'herramienta';

/**
 * Área del catálogo de almacén: a qué parte de la operación pertenece un
 * artículo. No es dónde está guardado —eso es la ubicación, que cuelga de un
 * almacén—: el área viaja con el artículo.
 */
export type AlmArea = {
    id: number;
    descripcion: string;
    activo: boolean;
};

/**
 * Almacén virtual. Con obra es un almacén de esa obra (montaje); sin obra es
 * central y surte a todas. La clave sólo es única dentro de su obra.
 */
export type AlmAlmacen = {
    id: number;
    clave: string;
    nombre: string;
    obra_id: number | null;
    obra?: Obra | null;
    tipo: AlmAlmacenTipo;
    responsable_id: string | null;
    responsable?: Usuario | null;
    observaciones: string | null;
    activo: boolean;
    created_at: string;
    updated_at: string;
};

/**
 * Formas de las pantallas de Almacén que todavía no tienen backend. Viven aquí
 * para que la maqueta (`lib/alm/demo.ts`) tenga tipos; cuando existan las tablas
 * se reemplazan por los modelos reales.
 *
 * Los movimientos que mueven saldo. La devolución no está: es de piezas con
 * número de serie y sólo cambia la custodia, nunca la existencia.
 */
export type AlmMovimientoTipo =
    | 'entrada'
    | 'salida'
    | 'transferencia_salida'
    | 'transferencia_entrada'
    | 'ajuste'
    /** Cambia de dueño, no de bodega: su pareja de asientos suma cero. */
    | 'reasignacion';

/**
 * Un artículo como lo ofrece el capturador de renglones: lo mínimo para
 * elegirlo, medirlo y saber si pide inspección al recibirlo.
 */
export type AlmProductoOpcion = {
    id: number;
    codigo: string | null;
    descripcion: string;
    unidad: string;
    /** Sin este palomeo en la entrada, el producto no se puede recepcionar. */
    requiere_verificacion: boolean;
};

export type AlmProductoDemo = {
    id: number;
    codigo: string;
    descripcion: string;
    unidad: string;
    /** Sin este palomeo en la entrada, el producto no se puede recepcionar. */
    requiere_verificacion: boolean;
};

/**
 * Con quién se compra. Vive en Costos —`proveedores`—; el almacén sólo lo lee
 * para saber contra qué orden está recibiendo.
 */
export type AlmProveedorDemo = {
    id: number;
    nombre: string;
    rfc: string;
};

/** Cómo va la recepción de una orden o de una factura. */
export type AlmRecepcionEstatus = 'pendiente' | 'parcial' | 'recibida';

/**
 * Un renglón de la orden de compra: lo que se pidió y lo que ya llegó.
 *
 * `cantidad_recibida` es el acumulado de todas las entradas anteriores contra
 * ese renglón, no lo de una sola. Es lo que impide recibir dos veces la misma
 * tonelada de tornillo cuando el material llega en tres viajes.
 */
export type AlmOcPartidaDemo = {
    producto_id: number;
    codigo: string;
    descripcion: string;
    unidad: string;
    cantidad_pedida: number;
    cantidad_recibida: number;
    costo_unitario: number;
};

/**
 * La orden de compra vista desde el almacén.
 *
 * No se captura aquí: nace en Costos y llega con lo que se le pidió al
 * proveedor. El almacén la usa como la lista contra la cual cotejar lo que
 * bajó del camión, que es lo que evita recibir de más o material que nadie
 * pidió.
 */
export type AlmOrdenCompraDemo = {
    id: number;
    folio: string;
    proveedor_id: number;
    fecha: string;
    /** A dónde va dirigida la compra: obra o planta. */
    destino: string;
    estatus: AlmRecepcionEstatus;
    partidas: AlmOcPartidaDemo[];
};

/**
 * La factura del proveedor, colgada de su orden.
 *
 * Nace `pendiente` de recepción y no se paga hasta que el almacén confirma que
 * lo facturado llegó: es el amarre entre el papel y el material. Una orden
 * puede tener varias —el proveedor surte en parcialidades— y el material puede
 * llegar antes que el CFDI, por eso también se puede recibir sin factura.
 */
export type AlmFacturaDemo = {
    id: number;
    /** Serie y folio del CFDI, tal como lo timbró el proveedor. */
    folio: string;
    uuid: string;
    orden_compra_id: number;
    fecha: string;
    importe: number;
    estatus: AlmRecepcionEstatus;
    /** Qué renglones de la orden ampara y por cuánto. */
    renglones: AlmFacturaRenglonDemo[];
};

export type AlmFacturaRenglonDemo = {
    producto_id: number;
    cantidad_facturada: number;
};

export type AlmExistenciaDemo = {
    almacen: string;
    producto: string;
    descripcion: string;
    unidad: string;
    cantidad: number;
    costo_promedio: number;
    /**
     * Dónde está dentro del almacén. Apunta al catálogo de ubicaciones en vez
     * de ser un texto libre: así se puede filtrar por zona y darle una ruta al
     * inventario cíclico. `null` es material que nadie ha acomodado —o, en un
     * renglón por pieza, que las piezas están repartidas en más de un lugar;
     * `piezas.ubicaciones` dice cuáles.
     */
    ubicacion_id: number | null;
    /**
     * Presente sólo cuando el renglón se armó contando piezas con serie en vez
     * de leer un saldo. Es la misma existencia —una pieza suma 1—, pero aquí sí
     * se sabe en qué anda cada una, y eso cambia lo que el almacenista puede
     * prometer: 5 pulidoras con 3 prestadas no son 5 pulidoras que entregar.
     */
    piezas?: AlmExistenciaPiezas;
};

/** El desglose de un renglón por pieza. Suma exactamente la existencia. */
export type AlmExistenciaPiezas = {
    disponibles: number;
    prestadas: number;
    en_reparacion: number;
    /**
     * Los lugares del almacén donde están repartidas, sin repetir; `null` es una
     * pieza que nadie acomodó. El renglón agrupa por almacén, no por lugar, así
     * que las 5 pulidoras de HER son una sola fila aunque estén en dos estantes:
     * esto es lo que permite que el filtro por ubicación siga alcanzándolas.
     */
    ubicaciones: (number | null)[];
};

export type AlmMovimientoDemo = {
    id: number;
    fecha: string;
    almacen: string;
    producto: string;
    tipo: AlmMovimientoTipo;
    /** Con signo: negativa cuando el material sale. */
    cantidad: number;
    saldo_nuevo: number;
    referencia: string;
    usuario: string;
    observaciones: string | null;
};

export type AlmEntradaDemo = {
    id: number;
    folio: string;
    fecha: string;
    almacen: string;
    proveedor: string | null;
    renglones: number;
    importe: number;
    recibio: string;
};

export type AlmSalidaDemo = {
    id: number;
    folio: string;
    fecha: string;
    almacen: string;
    obra_destino: string | null;
    solicitante: string;
    recibe: string;
    renglones: number;
    motivo: string;
    /** Requisición que surte, si la hay: la salida urgente no lleva. */
    pedido_folio: string | null;
};

/**
 * En qué tiempo va la transferencia. No es un adorno: mientras esté
 * `en_transito` el material no es existencia de nadie —salió del origen y el
 * destino todavía no lo confirma—, así que el saldo vive en un tercer lugar.
 */
export type AlmTransferenciaEstatus = 'en_transito' | 'recibida';

export type AlmTransferenciaRenglonDemo = {
    producto_id: number;
    codigo: string;
    descripcion: string;
    unidad: string;
    cantidad_enviada: number;
    /** Lo que el destino confirmó. `null` mientras va en el camión. */
    cantidad_recibida: number | null;
};

/**
 * Un documento en dos tiempos, no dos documentos: un folio `TRA` que se firma
 * al enviar y otra vez al recibir. El destino puede confirmar menos, y esa
 * diferencia se queda como faltante con dueño y fecha en vez de perdonarse.
 */
export type AlmTransferenciaDemo = {
    id: number;
    folio: string;
    /** Primer tiempo: cuándo salió del origen. */
    fecha_envio: string;
    /** Segundo tiempo: cuándo lo confirmó el destino. */
    fecha_recepcion: string | null;
    origen: string;
    destino: string;
    estatus: AlmTransferenciaEstatus;
    autorizo: string;
    envio: string;
    recibio: string | null;
    /** Pedido de obra que surte, si viene de uno. */
    pedido_folio: string | null;
    /** A quién se le cargó el faltante del tránsito. */
    faltante_responsable: string | null;
    observaciones: string | null;
    renglones: AlmTransferenciaRenglonDemo[];
};

/** Por qué se corrigió la existencia. Es lo que justifica el movimiento. */
export type AlmAjusteMotivo = 'conteo_fisico' | 'merma' | 'error_captura' | 'otro';

export type AlmAjusteDemo = {
    id: number;
    folio: string;
    fecha: string;
    almacen: string;
    motivo: AlmAjusteMotivo;
    renglones: number;
    /** Suma de las diferencias con signo: cuánto se movió el inventario. */
    diferencia_neta: number;
    autorizo: string;
};

/**
 * Piezas que vuelven y dejan de estar a nombre de alguien. No mueve existencia:
 * el material por cantidad que sobra en una obra regresa por transferencia, no
 * por aquí. No confundir con `costos_devoluciones`, que es devolución a
 * proveedor.
 */
export type AlmDevolucionDemo = {
    id: number;
    folio: string;
    fecha: string;
    devolvio: string;
    recibio: string;
    piezas: number;
    /** A qué almacenes volvieron; casi siempre uno. */
    almacenes: string[];
    /** Cuántas volvieron peor de como salieron. */
    con_dano: number;
};

/**
 * Qué es el producto para almacén. El `insumo` se gasta y sólo se cuenta; el
 * `activo` sale y regresa, así que puede llevar identidad individual (serie,
 * foto, resguardo) — la herramienta entra aquí, no era un caso aparte.
 * Espeja `App\Enums\Alm\ProductoTipo`.
 */
export type AlmProductoTipo = 'insumo' | 'activo';

/**
 * Cuánto pesa el artículo en el inventario, y por lo tanto cada cuánto se
 * cuenta. `A` es lo caro o de alta rotación (un faltante duele y se nota
 * tarde); `C` es lo barato que puede esperar al semestre.
 */
export type AlmClasificacionAbc = 'A' | 'B' | 'C';

/** Un punto del histórico de precios. Espeja `costos_producto_precios`. */
export type AlmPrecioDemo = {
    id: number;
    fecha: string;
    proveedor: string;
    precio: number;
    moneda: string;
    /** De dónde salió el precio: una cotización, una OC o captura manual. */
    origen: string;
};

export type AlmArticuloDemo = {
    id: number;
    codigo: string;
    descripcion: string;
    unidad: string;
    /**
     * Lo que se escanea. Nace igual al código y se puede sobrescribir con el
     * del fabricante cuando la caja ya trae uno impreso.
     */
    codigo_barras: string | null;
    /**
     * Cómo se llama este artículo en Steelex. Campo libre de 150 caracteres:
     * no se valida ni se cruza con nada, sólo deja anotado a qué corresponde
     * allá para poder conciliar mientras los dos sistemas convivan.
     */
    idsteelex: string | null;
    /** A qué parte de la operación pertenece. Sale del catálogo de áreas. */
    area: string | null;
    /** Cada cuánto lo alcanza el inventario cíclico. */
    clasificacion_abc: AlmClasificacionAbc;
    /** Último precio del histórico, para no tener que abrir la ficha. */
    precio_ultimo: number | null;
    /** Foto del artículo, para reconocerlo sin leer la descripción. */
    imagen_url: string | null;
    tipo: AlmProductoTipo;
    /**
     * Recepcionarlo exige verificar su mantenimiento. Va aparte del tipo
     * porque no todo activo lo necesita: una pulidora sí, un andamio no.
     */
    requiere_verificacion: boolean;
    /** Un servicio o un gasto se compra pero no se almacena: no lleva kardex. */
    controla_inventario: boolean;
    /**
     * Además del saldo por cantidad, cada pieza se registra con número de serie
     * y se presta bajo resguardo. Sin esto el kardex sabe cuántas pulidoras
     * salieron, pero no quién tiene cuál.
     */
    se_controla_por_pieza: boolean;
    stock_minimo: number | null;
    existencia_total: number;
};

/** Los documentos de almacén que pueden pedir firma. */
export type AlmDocumentoTipo =
    | 'pedido'
    | 'entrada'
    | 'salida'
    | 'transferencia'
    | 'devolucion'
    | 'ajuste'
    | 'prestamo';

export type AlmUsuarioDemo = {
    id: number;
    nombre: string;
    puesto: string;
};

/**
 * Cuadrilla de planta, tomada de `prod_grupos_trabajo`. El almacén no las
 * administra: sólo las nombra para saber a quién se le prestó la herramienta
 * cuando no se va a ninguna obra.
 */
export type AlmGrupoTrabajoDemo = {
    id: number;
    descripcion: string;
    /** Dónde trabaja el grupo; ahí es donde hay que ir a buscar la pieza. */
    ubicaciones: string[];
    empleados: number;
};

/** Quién puede firmar un tipo de documento en un almacén. */
export type AlmReglaAprobacion = {
    documento: AlmDocumentoTipo;
    requiere: boolean;
    /** Basta con que firme uno de ellos. */
    usuarios: number[];
};

/**
 * Se llama pedido y no requisición para no chocar con la requisición de compra
 * de Costos, que le pide material a un proveedor. Éste le pide a un almacén lo
 * que ya está en existencia.
 */
export type AlmPedidoEstatus =
    | 'borrador'
    | 'pendiente'
    | 'aprobado'
    | 'surtido'
    | 'cancelado'
    | 'rechazado';

export type AlmPedidoDetalleDemo = {
    producto_id: number;
    cantidad_solicitada: number;
    /**
     * Lo que ya se entregó, sumando todas las salidas y transferencias de este
     * pedido. Un pedido se surte en varias vueltas: sólo llega a `surtido`
     * cuando todos sus renglones alcanzan lo solicitado.
     */
    cantidad_surtida: number;
};

export type AlmPedidoDemo = {
    id: number;
    folio: string;
    fecha: string;
    solicitante: string;
    /** Quién pide. Siempre hay un área responsable, haya obra o no. */
    departamento: string;
    /**
     * Para dónde es. `null` es consumo interno de planta: la fabricación y las
     * áreas de la nave también piden material, y no cuelgan de ninguna obra.
     *
     * De aquí sale cómo se surte: con obra hay que llevarlo al almacén de esa
     * obra, así que lo surte una transferencia; sin obra el material se queda
     * en el mismo domicilio y lo surte una salida.
     */
    obra: string | null;
    /**
     * Sólo en los internos de planta: a nombre de quién se entrega. Opcional
     * —el área levanta el pedido y no siempre sabe de antemano quién va a
     * pasar por el material—, pero cuando viene, la salida trae puesto quién
     * firma el vale. En los de obra no aplica: ahí recibe el almacén destino.
     */
    recibe: string | null;
    /**
     * La cuadrilla que se lo lleva, del catálogo de producción
     * (`prod_grupos_trabajo`). También opcional, y por la misma razón: sirve
     * para saber a qué frente se fue el material sin tener que preguntar.
     */
    grupo_trabajo: string | null;
    almacen: string;
    fecha_requerida: string;
    detalle: AlmPedidoDetalleDemo[];
    estatus: AlmPedidoEstatus;
};

/**
 * Una pieza identificada de un artículo marcado `se_controla_por_pieza`. El
 * kardex sigue contando por cantidad; esto es lo que responde quién tiene cuál.
 */
export type AlmActivoEstatus = 'disponible' | 'prestado' | 'en_reparacion' | 'baja';

export type AlmActivoDemo = {
    id: number;
    producto_id: number;
    codigo: string;
    descripcion: string;
    no_serie: string;
    /**
     * El de la pieza, no el del artículo: dos pulidoras del mismo modelo
     * comparten código pero se escanean distinto, que es lo que permite saber
     * cuál volvió del préstamo.
     */
    codigo_barras: string | null;
    /**
     * De la pieza, no del artículo: el catálogo dice qué es («pulidora de 4
     * 1/2\" 850W») y la pieza con qué se cumplió, que es lo que se necesita
     * para pedir la refacción correcta. Dos altas del mismo artículo pueden
     * traer marcas distintas.
     */
    marca: string | null;
    modelo: string | null;
    /**
     * Con qué número identifica mantenimiento a esta pieza en su propio
     * control. Texto libre: no se valida ni se cruza con nada, sólo deja
     * anotado a qué corresponde allá para poder conciliar.
     */
    id_mantenimiento: string | null;
    almacen: string;
    /**
     * Dónde vive cuando está en el almacén. Es el id del árbol de ubicaciones y
     * no un texto: así la pieza cae en el mismo renglón de Existencias que el
     * resto de lo que hay en ese lugar, y el filtro por ubicación la alcanza.
     */
    ubicacion_id: number | null;
    /**
     * Lo que costó *esta* pieza. Va por pieza y no por artículo porque el costo
     * promedio del renglón sale de promediarlas: dos pulidoras del mismo modelo
     * compradas con dos años de diferencia no valen lo mismo.
     */
    costo: number;
    estatus: AlmActivoEstatus;
    condicion: string;
};

export type AlmPrestamoEstatus = 'abierto' | 'devuelto' | 'perdido';

/**
 * Resguardo de una pieza. No mueve el saldo del kardex: la herramienta sigue
 * siendo del almacén, lo que cambia es quién la trae. Por eso Existencias
 * puede decir "14 pulidoras · 11 disponibles · 3 prestadas".
 */
export type AlmPrestamoDemo = {
    id: number;
    folio: string;
    activo_id: number;
    no_serie: string;
    articulo: string;
    almacen: string;
    responsable: string;
    /** Obra o área a la que se la llevó. */
    destino: string;
    fecha_salida: string;
    fecha_retorno_esperada: string;
    fecha_retorno: string | null;
    condicion_salida: string;
    condicion_retorno: string | null;
    estatus: AlmPrestamoEstatus;
};

/**
 * Un lugar físico dentro de un almacén: pasillo, rack, nivel o contenedor.
 *
 * El almacén es virtual (AG, FAK) y puede vivir dentro de una obra; esto es el
 * tercer nivel que faltaba para poder decir "está en el Rack A-1, nivel 2" en
 * vez de anotarlo en un texto libre que nadie puede filtrar. Es lo que también
 * le da al inventario cíclico una ruta que recorrer.
 */
export type AlmUbicacionTipo = 'pasillo' | 'rack' | 'nivel' | 'contenedor' | 'zona';

/**
 * Un renglón del árbol de ubicaciones de un almacén, ya aplanado en el orden en
 * que se recorre físicamente. `nivel` es la profundidad, para sangrarlo sin
 * tener que rearmar la jerarquía en el navegador.
 */
export type AlmUbicacionFila = {
    id: number;
    padre_id: number | null;
    /** Único dentro del almacén: es lo que se rotula en el anaquel. */
    codigo: string;
    nombre: string;
    tipo: AlmUbicacionTipo;
    activa: boolean;
    nivel: number;
    /** Cuántos artículos con saldo viven ahí. Dar de baja a ciegas pierde material. */
    articulos: number;
};

/** El almacén como lo ofrece el selector de las pantallas de inventario. */
export type AlmAlmacenOpcion = {
    id: number;
    clave: string;
    nombre: string;
    obra_id: number | null;
    obra?: { id: number; no: string; descripcion?: string | null } | null;
    tipo: AlmAlmacenTipo;
};

/** Una opción de catálogo con su etiqueta ya resuelta en el servidor. */
export type AlmOpcion = {
    value: string;
    label: string;
};

/** La clase ABC con lo que decide: cada cuánto toca contar. */
export type AlmOpcionClase = AlmOpcion & {
    frecuencia_dias: number;
    descripcion: string;
};

/**
 * Un artículo del catálogo compartido con Compras, visto desde Almacén.
 *
 * `codigo` no se edita nunca: lo pone el sistema y con él se etiquetaron cajas y
 * se sellaron movimientos.
 */
export type AlmArticulo = {
    id: number;
    codigo: string;
    codigo_barras: string | null;
    descripcion: string;
    /** Cómo se llama en el sistema anterior. Sólo para conciliar; no se valida. */
    idsteelex: string | null;
    area_id: number | null;
    area: string | null;
    unidad: string;
    tipo: AlmProductoTipo;
    clasificacion_abc: AlmClasificacionAbc;
    /**
     * Con qué producto de Compras se cotiza y se compra. `null` es material que
     * la bodega guarda y que todavía no se empareja con nada: sale de la carga
     * inicial de un almacén y espera a que alguien lo ligue.
     *
     * Ya no hay bandera de «lleva kardex»: estar en este catálogo es llevarlo.
     */
    producto_id: number | null;
    /**
     * El renglón de Compras con el que es el mismo material, ya resuelto. `null`
     * es material sin identidad de compra: la ficha lo marca como pendiente de
     * ligar.
     */
    producto: { id: number; codigo: string | null; descripcion: string } | null;
    /** Además del saldo, cada pieza con su número de serie y su resguardo. */
    se_controla_por_pieza: boolean;
    requiere_verificacion: boolean;
    stock_minimo: number | null;
    imagen_url: string | null;
    /** Del histórico de Compras. Aquí sólo se consulta, nunca se captura. */
    precio_ultimo: number | null;
    /** Sumando todos los almacenes. */
    existencia_total: number;
    /** Apagado: sigue en el kardex, pero no se compra, se cuenta ni se presta. */
    activo: boolean;
};

/** Cuánto hay de un artículo en un almacén, para la ficha. */
export type AlmArticuloExistencia = {
    /** El id de la existencia, para poder acomodarla desde la ficha. */
    id: number;
    almacen_id: number;
    almacen: string;
    almacen_nombre: string;
    obra: string | null;
    cantidad: number;
    costo_promedio: number;
    ubicacion_id: number | null;
    /** La ruta completa: `Pasillo A / Rack A-1 / Nivel 2`. */
    ubicacion: string | null;
};

/** Un punto del histórico de precios. Espeja `costos_producto_precios`. */
export type AlmArticuloPrecio = {
    id: number;
    fecha: string | null;
    proveedor: string | null;
    precio: number;
    moneda: string;
    requisicion_id: number | null;
};

export type AlmUbicacionDemo = {
    id: number;
    almacen: string;
    /** Único dentro del almacén: es lo que se rotula en el anaquel. */
    codigo: string;
    nombre: string;
    tipo: AlmUbicacionTipo;
    /** Cuelga de otra ubicación: un nivel vive dentro de un rack. */
    padre_id: number | null;
    activa: boolean;
};

/** De dónde salió la hoja de conteo. */
export type AlmConteoOrigen = 'programado' | 'manual';

export type AlmConteoEstatus = 'pendiente' | 'contando' | 'cerrado' | 'cancelado';

/**
 * Renglón de una hoja de conteo. `cantidad_sistema` se congela al generar la
 * hoja: si se leyera al cerrar, un movimiento capturado a media mañana
 * convertiría un conteo correcto en una diferencia inventada.
 */
export type AlmConteoRenglonDemo = {
    producto_id: number;
    codigo: string;
    descripcion: string;
    unidad: string;
    ubicacion: string | null;
    cantidad_sistema: number;
    /** `null` mientras nadie lo haya contado. Cero es un dato, no un hueco. */
    cantidad_contada: number | null;
};

/**
 * Un inventario cíclico: se cuenta una parte del almacén sin parar la
 * operación, en vez de cerrar todo una vez al año. Al cerrarse genera un
 * ajuste con las diferencias — y ese ajuste es el único que las escribe.
 */
export type AlmConteoDemo = {
    id: number;
    folio: string;
    origen: AlmConteoOrigen;
    almacen: string;
    /** Zona que toca recorrer. `null` es el almacén completo. */
    ubicacion: string | null;
    /** Qué clase de artículo entró a la hoja. `null` cuando fue por zona. */
    clasificacion: AlmClasificacionAbc | null;
    fecha_programada: string;
    fecha_cierre: string | null;
    responsable: string;
    estatus: AlmConteoEstatus;
    renglones: AlmConteoRenglonDemo[];
    /** Folio del ajuste que se generó al cerrar, si hubo diferencias. */
    ajuste_folio: string | null;
};

/**
 * La regla de cada clase: cada cuántos días hay que volver a contarla. De aquí
 * salen solas las hojas de la semana.
 */
export type AlmReglaAbc = {
    clasificacion: AlmClasificacionAbc;
    frecuencia_dias: number;
    etiqueta: string;
    descripcion: string;
};

/** Un renglón del capturador de partidas, compartido por los tres documentos. */
export type AlmPartidaBorrador = {
    articulo_id: string;
    cantidad: string;
    costo_unitario: string;
    observaciones: string;
    /**
     * Sólo aplica a los activos y sólo en la entrada: sin este palomeo el
     * equipo no se puede recepcionar.
     */
    mantenimiento_verificado: boolean;
};

/**
 * Un renglón del capturador de préstamos. No lleva cantidad: lo que se presta
 * es la pieza, y cada renglón se convierte en su propio resguardo con folio.
 */
export type AlmPrestamoPiezaBorrador = {
    activo_id: string;
    /** Arranca con la que trae registrada la pieza; contra esto se compara al volver. */
    condicion_salida: string;
    observaciones: string;
};

// =========================================
// Drive
// =========================================

export type DriveExterno = {
    id: number;
    nombre: string;
    email: string;
    telefono: string | null;
    empresa: string | null;
    activo: boolean;
    ultimo_acceso: string | null;
    carpetas_count?: number;
    carpetas?: DriveCarpeta[];
    created_at: string;
    updated_at: string;
};

export type DriveCarpeta = {
    id: number;
    nombre: string;
    descripcion: string | null;
    usuario_id: string | null;
    usuario?: Usuario;
    archivos_count?: number;
    externos_count?: number;
    archivos_sum_size?: number | null;
    externos?: DriveExterno[];
    usuarios?: DriveCarpetaUsuario[];
    created_at: string;
    updated_at: string;
};

/** Usuario interno con acceso compartido a una carpeta. */
export type DriveCarpetaUsuario = Usuario & {
    pivot: {
        carpeta_id: number;
        usuario_id: string;
        puede_escribir: boolean;
    };
};

export type DriveArchivo = {
    id: number;
    carpeta_id: number;
    nombre_original: string;
    path: string;
    mime: string | null;
    size: number | null;
    descripcion: string | null;
    subido_por_type: string;
    subido_por_id: string;
    link_token: string | null;
    link_expira_en: string | null;
    auto_eliminar_en: string | null;
    link_publico: string | null;
    carpeta?: DriveCarpeta;
    created_at: string;
    updated_at: string;
};

// DG Notas (bloc de notas del Director General)

export type DgNota = {
    id: number;
    usuario_id: string;
    titulo: string;
    contenido: string | null;
    created_at: string;
    updated_at: string;
};

// DG Reportes Types

export type DgCarpeta = {
    id: number;
    nombre: string;
    descripcion: string | null;
    orden: number;
    usuarios?: (Usuario & { pivot: { puede_escribir: boolean } })[];
    created_at: string;
    updated_at: string;
};

export type DgReporte = {
    id: number;
    carpeta_id: number;
    anio: number;
    semana: number;
    creado_por_id: string | null;
    observaciones: string | null;
    etiqueta_semana: string;
    carpeta?: DgCarpeta;
    creado_por?: Usuario;
    archivos?: DgReporteArchivo[];
    archivos_count?: number;
    created_at: string;
    updated_at: string;
};

export type DgReporteArchivo = {
    id: number;
    reporte_id: number;
    nombre_original: string;
    path: string;
    mime: string | null;
    size: number | null;
    subido_por_id: string | null;
    subido_por?: Usuario;
    reporte?: DgReporte;
    notas: string | null;
    notas_editado_por_id: string | null;
    notas_actualizado_en: string | null;
    notas_editado_por?: Usuario;
    visto_por_dg_en: string | null;
    created_at: string;
    updated_at: string;
};

// Portal Tablero Types
// Payload plano del tablero del proveedor (App\Services\Portal\TableroProveedorBuilder).
// No son modelos serializados: cada campo lo arma el builder a propósito.

export type PortalTableroTab = 'activas' | 'completadas';

export type PortalTableroArchivo = {
    url: string | null;
    nombre: string;
    fecha: string | null;
};

export type PortalTableroPartida = {
    id: number;
    descripcion: string;
    unidad: string | null;
    cantidad: number;
    precio_unitario: number;
    subtotal: number;
};

/** Un comprobante por pago; varios cuando el pago se partió en parcialidades. */
export type PortalTableroComprobantePago = {
    id: number;
    folio: string | null;
    monto: number;
    fecha: string | null;
    numero_parcialidad: number | null;
    url: string | null;
};

export type PortalTableroNotaCredito = {
    id: number;
    folio: string | null;
    monto: number;
    estatus: string | null;
    fecha: string | null;
};

export type PortalTableroParcialidad = {
    id: number;
    folio: string | null;
    numero: number | null;
    monto: number;
    estatus: string | null;
    fecha_programada: string | null;
    fecha_realizada: string | null;
    comprobante_url: string | null;
};

export type PortalTableroPago = {
    id: number;
    folio: string | null;
    estatus: string | null;
    monto: number;
    fecha_programada: string | null;
    fecha_realizada: string | null;
    parcialidades: PortalTableroParcialidad[];
};

export type PortalTableroFactura = {
    id: number;
    folio: string | null;
    fecha: string | null;
    total: number;
    moneda: string | null;
    estatus: string | null;
    cancelada: boolean;
    uuid_fiscal: string | null;
    folio_fiscal: string | null;
    subtotal: number;
    monto_notas_credito: number;
    monto_anticipos: number;
    /** Total menos anticipos y notas de crédito vigentes. */
    saldo_facturado: number;
    pago: PortalTableroPago | null;
    pagada: boolean;
    pdf_url: string | null;
    xml_url: string | null;
    recepcion: PortalTableroArchivo | null;
    puede_subir_recepcion: boolean;
    /** null = aún no hay pago programado; la celda dice "Por programar". */
    contrarecibo_url: string | null;
    comprobantes_pago: PortalTableroComprobantePago[];
    notas_credito: PortalTableroNotaCredito[];
};

export type PortalTableroOrden = {
    id: number;
    folio: string;
    fecha: string | null;
    fecha_entrega_esperada: string | null;
    total: number;
    moneda: string;
    total_facturado: number;
    saldo_facturable: number;
    completada: boolean;
    puede_facturar: boolean;
    partidas: PortalTableroPartida[];
    facturas: PortalTableroFactura[];
};

/** Datos leídos del CFDI en el paso 1, a la espera de confirmación. */
export type PortalFacturaPreview = {
    orden_compra_id: number;
    orden_compra_folio: string;
    moneda: string;
    fiscal: {
        uuid_fiscal: string | null;
        folio_fiscal: string | null;
        fecha_factura: string | null;
        subtotal: number;
        total: number;
        iva_trasladado: number;
        iva_retenido: number;
        isr_retenido: number;
        rfc_emisor: string | null;
        rfc_receptor: string | null;
    };
    archivos: { xml_original: string; pdf_original: string | null };
    notas: string | null;
};

export type PortalTableroResumen = {
    facturado: number;
    pagado: number;
    pendiente: number;
    moneda: string;
    facturas: number;
    facturas_pagadas: number;
};

// Badge Config Types

export type BadgeConfig = {
    id: number;
    nombre: string;
    tabla: string;
    campo_estatus: string;
    operador: string;
    valor_estatus: string;
    condiciones_extra: Array<{ campo: string; operador: string; valor: unknown }> | null;
    rol: string;
    nav_href: string;
    filter_href: string | null;
    activo: boolean;
    created_at: string;
    updated_at: string;
};

// =========================================
// Calidad
// =========================================

/**
 * Los catálogos del módulo comparten forma: un nombre y si sigue en uso. Aquí
 * nada se borra, se desactiva — un valor inactivo sale de los desplegables pero
 * no toca los registros que ya lo mencionan.
 */
export type QalCatalogoSimple = {
    id: number;
    nombre: string;
    activo: boolean;
    created_at: string;
    updated_at: string;
};

/** De dónde sale quien firma un lugar de la hoja: quien la elaboró o una persona fija. */
export type QalOrigenFirmante = 'creador' | 'usuario';

/** Un lugar de firma de los formatos PDF de Calidad, en su orden. */
export type QalFirmante = {
    id: number;
    orden: number;
    /** Lo que va sobre la firma: Elaboró, Revisó, Aprobó… */
    etiqueta: string;
    cargo: string;
    origen: QalOrigenFirmante;
    usuario_id: string | null;
    /** Nombre de la persona fija; null si firma quien elaboró o todavía no se elige. */
    usuario: string | null;
    tiene_rubrica: boolean;
};

export type QalUsuarioFirmante = {
    id: string;
    nombre: string;
    tiene_rubrica: boolean;
};

export type QalOpcionOrigenFirmante = {
    valor: QalOrigenFirmante;
    etiqueta: string;
};

/** A qué lista del catálogo de defectos pertenece uno; cada lista es de una etapa. */
export type QalAmbitoDefecto =
    | 'soldadura'
    | 'pintura'
    | 'accesorio_dimensional'
    | 'accesorio_barrenos'
    | 'accesorio_limpieza';

export type QalDefecto = QalCatalogoSimple & {
    ambito: QalAmbitoDefecto;
    clave: string | null;
};

export type QalOpcionAmbitoDefecto = {
    valor: QalAmbitoDefecto;
    etiqueta: string;
    fase: QalFaseTransformacion;
};

/** Laboratorio que firma los informes de ensayos no destructivos. */
export type QalLaboratorio = QalCatalogoSimple & {
    /** Como se le nombra dentro del informe. */
    siglas: string | null;
};

/**
 * Tipo de pieza, por el prefijo oficial de ingeniería. Con él la captura deduce
 * sola el tipo: en `PIP-TP12-3`, el prefijo `TP` la resuelve como trabe
 * principal sin que el inspector elija nada.
 */
export type QalTipoPieza = {
    id: number;
    prefijo: string;
    descripcion: string;
    activo: boolean;
    created_at: string;
    updated_at: string;
};

/**
 * Soldador del padrón. La `clave` es la que se estampa en la pieza y la que
 * enlaza con su WPQR en el dosier: si no coincide, el dosier reporta que no
 * tiene certificado.
 */
export type QalSoldador = QalCatalogoSimple & {
    clave: string | null;
    certificacion: string | null;
    /** Sin fecha no se puede afirmar que esté vencida, así que no se asume. */
    certificacion_vence_at: string | null;
};

/** Métodos de prueba no destructiva. Los define la norma, no la empresa. */
export type QalMetodoPnd = 'UT' | 'MT' | 'PT' | 'RT' | 'VT';

/** Las tres transformaciones por las que pasa una pieza. */
export type QalFaseTransformacion = '1ª' | '2ª' | '3ª';

/**
 * El veredicto del laboratorio sobre un punto examinado. Son dos: el reexamen
 * posterior a una reparación entra como su propio renglón, no corrigiendo el
 * veredicto anterior.
 */
export type QalResultadoPnd = 'aceptada' | 'rechazada';

/** La obra vista desde Calidad. */
export type QalObra = {
    id: number;
    no: string;
    descripcion: string | null;
    activa?: boolean;
    /** De dónde sale el número de pruebas comprometidas, en palabras. */
    pnd_nota?: string | null;
};

/**
 * Un método dentro del plan de PND de la obra, ya cruzado con lo ensayado.
 *
 * `comprometidas` en **nulo** significa «este método no entra en el contrato»,
 * que no es lo mismo que un cero —«se pactaron cero»—. La pantalla los pinta
 * distinto, así que el nulo no debe colapsarse a 0 al leerlo.
 */
export type QalPndAvance = {
    metodo: QalMetodoPnd;
    nombre: string;
    detecta: string;
    /** Los parámetros que suele traer el informe de este método. */
    parametros: string[];
    comprometidas: number | null;
    /** Puntos examinados, no juntas: el denominador del porcentaje de rechazo. */
    spots: number;
    rechazados: number;
    reportes: number;
};

/** Un parámetro con que el laboratorio corrió la prueba. */
export type QalPndParametro = {
    id: number;
    clave: string;
    valor: string;
};

/**
 * Un renglón de la rejilla del informe: **un punto examinado, no una junta**.
 * `J-18-1-2` es el segundo spot de la junta `18-1`.
 */
export type QalPndJunta = {
    id: number;
    /** La marca de Producción, cuando la del laboratorio es única en el catálogo vigente de la obra. */
    concepto_id: number | null;
    /** El texto tal como lo escribió el laboratorio; se conserva siempre. */
    marca: string;
    junta: string;
    modulo: string | null;
    spot: number;
    resultado: QalResultadoPnd;
    discontinuidad: string | null;
    longitud_discontinuidad: string | null;
    espesor: string | null;
    soldador_id: number | null;
    concepto?: { id: number; marca: string; lote: string | null } | null;
};

/** Evidencia fotográfica del informe. */
export type QalPndFoto = {
    id: number;
    ruta: string;
    nombre: string | null;
};

/**
 * El informe que emite el laboratorio de pruebas no destructivas.
 *
 * `reporte_no` es el folio **del laboratorio**: se teclea, no se genera. El
 * encabezado vive una sola vez y la rejilla cuelga de él.
 */
export type QalPndReporte = {
    id: number;
    reporte_no: string;
    metodo: QalMetodoPnd;
    laboratorio_id: number;
    qal_obra_id: number;
    lugar: string | null;
    fecha_prueba: string;
    fecha_emision: string | null;
    /** La semana va siempre con su año: sola es ambigua entre ejercicios. */
    anio: number;
    semana: number;
    porcentaje_inspeccion: string | null;
    tecnico: string | null;
    material: string | null;
    norma: string | null;
    archivo_pdf: string | null;
    created_at?: string;
    updated_at?: string;
    laboratorio?: QalLaboratorio | null;
    juntas?: QalPndJunta[];
    parametros?: QalPndParametro[];
    fotos?: QalPndFoto[];
    /** Puntos examinados del informe, contados en la consulta. */
    spots?: number;
    rechazados?: number;
};

/**
 * Una obra en la hoja de PND del reporte semanal.
 *
 * Va en **acumulado del proyecto**, no de la semana: lo que se pactó con el
 * cliente es el total del contrato, así que el avance sólo significa algo
 * contra todo lo ensayado hasta la fecha.
 *
 * La unidad es el **spot** —un punto examinado—, no la junta: una junta puede
 * llevar varios puntos, y contarlas subestimaría lo ensayado.
 */
export type QalReporteSemanalPnd = {
    obra_id: number;
    obra: string;
    descripcion: string | null;
    /** Puntos examinados en toda la obra. */
    spots: number;
    rechazados: number;
    /** El numerador del avance: un punto rechazado se ensayó, pero no cumple. */
    aceptados: number;
    metodos: Record<QalMetodoPnd, { spots: number; aceptados: number; rechazados: number }>;
    /** Piezas del proyecto. `null` = no está capturado en la ficha de la obra. */
    pz_total: number | null;
    /** Spots comprometidos. `null` = la obra no tiene plan de PND todavía. */
    comprometidos: number | null;
    /** Marcas distintas con al menos un ensayo. Son piezas, no ensayos. */
    piezas_con_pnd: number;
    piezas_sin_rechazo: number;
};

/**
 * Dónde se originó una incidencia aparecida en obra.
 *
 * El taller de pintura cuenta como taller en el corte del reporte semanal:
 * también es un defecto que salió de la nave.
 */
export type QalAreaIncidencia = 'taller' | 'taller_pintura' | 'montaje';

/** A quién se le atribuye la incidencia. Son los siete del formato en Excel. */
export type QalDepartamentoIncidencia =
    | '1a'
    | '2a'
    | 'pintura_taller'
    | 'pintura_obra'
    | 'ingenieria'
    | 'logistica'
    | 'construccion';

/** Una opción de enum con la etiqueta que se enseña. */
export type QalOpcion = { valor: string; etiqueta: string };

/**
 * El avance de montaje de una obra en una semana: el **denominador**.
 *
 * `pz_montadas` en nulo es «falta el dato»; cero es «no se montó nada». La
 * pantalla los pinta distinto porque el porcentaje del segundo es calculable y
 * el del primero no.
 *
 * `sin_incidencias` es un dato, no un hueco: dice que la semana se revisó y no
 * hubo hallazgos, que no es lo mismo que una semana en blanco.
 */
export type QalObraMontaje = {
    id: number;
    qal_obra_id: number;
    anio: number;
    semana: number;
    pz_montadas: number | null;
    sin_incidencias: boolean;
    notas: string | null;
};

/**
 * Una incidencia de montaje.
 *
 * El numerador son las **piezas con defecto**, no el número de incidencias: un
 * solo hallazgo puede afectar a diez piezas. El estado sale de `cerrada_en`;
 * `abierta` es la misma verdad ya resuelta por el modelo.
 */
export type QalObraIncidencia = {
    id: number;
    qal_obra_id: number;
    anio: number;
    semana: number;
    fecha: string;
    area: QalAreaIncidencia;
    departamento: QalDepartamentoIncidencia;
    pz_defecto: number;
    folio: string | null;
    descripcion: string | null;
    cerrada_en: string | null;
    abierta: boolean;
    capturista?: { id: string; name: string } | null;
};

/** Una obra en la portada de incidencias, con su año ya sumado. */
export type QalIncidenciaObraResumen = {
    id: number;
    no: string;
    descripcion: string | null;
    pz_total: number | null;
    pz_montadas: number;
    pz_montadas_semana: number;
    semanas: number;
    incidencias: number;
    pz_defecto: number;
    abiertas: number;
    incidencias_semana: number;
    /** `null` = no hay piezas montadas capturadas, así que no hay porcentaje. */
    tasa: number | null;
};

/** Una semana en la tabla de historial de la obra. */
export type QalIncidenciaSemana = {
    semana: number;
    /** Lunes y domingo de la semana ISO, en palabras. */
    rango: string;
    montaje_id: number | null;
    pz_montadas: number | null;
    sin_incidencias: boolean;
    notas: string | null;
    incidencias: number;
    pz_defecto: number;
    tasa: number | null;
};
