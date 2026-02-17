export type Usuario = {
    id: string;
    empleado: number | null;
    name: string;
    email: string;
    email_verified_at: string | null;
    roles?: Role[];
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

export type ObraEstatus = 'planificacion' | 'en_proceso' | 'activa' | 'suspendida' | 'completada' | 'cancelada';

export const OBRA_ESTATUS_LABELS: Record<ObraEstatus, string> = {
    planificacion: 'Planificación',
    en_proceso: 'En Proceso',
    activa: 'Activa',
    suspendida: 'Suspendida',
    completada: 'Completada',
    cancelada: 'Cancelada',
};

export type Obra = {
    id: number;
    no: string;
    descripcion: string;
    fecha_inicio: string | null;
    fecha_fin: string | null;
    presupuesto_total: number;
    estatus: ObraEstatus;
    obra_rubros?: CostosObraRubro[];
    created_at: string;
    updated_at: string;
};

export type Media = {
    id: number;
    descripcion: string;
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
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
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
    media_id: number;
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
export type Concepto = {
    id: number;
    obra_id: number;
    marca: string;
    descripcion: string;
    peso_unitario: number;
    version: number;
    activo: boolean;
    obra?: Obra;
    grupo_precio_conceptos?: ProdGrupoPrecioConcepto[];
    created_at: string;
    updated_at: string;
};

export type ProdGrupoPrecio = {
    id: number;
    obra_id: number;
    descripcion: string;
    precio_kilo: number;
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
    linea: number;
    modulo: number;
    activo: boolean;
    empleados?: ProdGrupoEmpleado[];
    empleados_count?: number;
    created_at: string;
    updated_at: string;
};

export type ProdGrupoEmpleado = {
    id: number;
    grupo_trabajo_id: number;
    nombre: string;
    no_empleado: string | null;
    porcentaje: number;
    created_at: string;
    updated_at: string;
};

export type ProdRegistro = {
    id: number;
    fecha: string;
    concepto_id: number;
    grupo_trabajo_id: number;
    cantidad: number;
    concepto?: Concepto;
    grupo_trabajo?: ProdGrupoTrabajo;
    created_at: string;
    updated_at: string;
};

export type ProdCorte = {
    id: number;
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

export type ProdLiquidacion = {
    id: number;
    corte_id: number;
    grupo_trabajo_id: number;
    total_kilos: number;
    total_produccion: number;
    total_extras: number;
    total_final: number;
    generado_en: string;
    generado_por: string;
    corte?: ProdCorte;
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
    concepto_id: number;
    grupo_precio_id: number;
    cantidad: number;
    kilos: number;
    precio_kilo_aplicado: number;
    total: number;
    created_at: string;
    updated_at: string;
};

export type ProdTipoPagoExtra = {
    id: number;
    descripcion: string;
    orden: number;
    desgloce: boolean;
    created_at: string;
    updated_at: string;
};

export type ProdPagoExtra = {
    id: number;
    descripcion: string;
    tipo_id: number;
    corte_id: number;
    grupo_trabajo_id: number;
    precio: number;
    dias: number;
    personas: number;
    monto?: number;
    tipo?: ProdTipoPagoExtra;
    corte?: ProdCorte;
    grupo_trabajo?: ProdGrupoTrabajo;
    created_at: string;
    updated_at: string;
};

export type ProdLiquidacionEmpleado = {
    id: number;
    liquidacion_id: number;
    nombre: string;
    no_empleado: string | null;
    porcentaje: number;
    monto_asignado: number;
    created_at: string;
    updated_at: string;
};

// Infraestructura Types
export type InfraCompresor = {
    id: number;
    usuario_id: string | null;
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
    linea_A: number | null;
    linea_A_max: number | null;
    date_A: string | null;
    linea_B: number | null;
    linea_B_max: number | null;
    date_B: string | null;
    linea_C: number | null;
    linea_C_max: number | null;
    date_C: string | null;
    total_1: number | null;
    total_5: number | null;
    lectura_5y5: number | null;
    lectura_301: number | null;
    lectura_302: number | null;
    lectura_303: number | null;
    lectura_310: number | null;
    observaciones: string | null;
    usuario?: Usuario;
    created_at: string;
    updated_at: string;
};

export type InfraTanque = {
    id: number;
    usuario_id: string | null;
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
    presion_tanque_lp: number | null;
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
    soplador_activa: boolean;
    bomba_activa: boolean;
    nivel_cloro: number | null;
    trampa_solida: boolean;
    observaciones: string | null;
    usuario?: Usuario;
    created_at: string;
    updated_at: string;
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
export type Proveedor = {
    id: number;
    codigo: string;
    razon_social: string;
    nombre_comercial: string | null;
    rfc: string;
    direccion: string | null;
    telefono: string | null;
    email: string | null;
    contacto_nombre: string | null;
    tiene_acceso_portal: boolean;
    maneja_credito: boolean;
    limite_credito: number;
    dias_credito_default: number;
    departamento_id: number | null;
    tipo_proveedor: string | null;
    activo: boolean;
    departamento?: Departamento;
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

export type CostosRubro = {
    id: number;
    codigo: string;
    descripcion: string;
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
    texto: string | null;
    texto_adicional: boolean;
    created_at: string;
    updated_at: string;
};

export type CostosObraRubro = {
    id: number;
    obra_id: number;
    rubro_id: number;
    presupuestado: number;
    acumulado: number;
    rubro?: CostosRubro;
    created_at: string;
    updated_at: string;
};

export type CostosPermiso = {
    id: number;
    descripcion: string;
    nivel: number;
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

export type CostosSolicitudPago = {
    id: number;
    folio: string;
    solicitante_id: string;
    departamento_id: number;
    proveedor_id: number | null;
    tipo_solicitud_id: number;
    concepto: string;
    monto_total: number;
    tipo_pago: string;
    tipo_moneda: CostosTipoMoneda;
    fecha_pago_solicitada: string | null;
    fecha_pago_realizada: string | null;
    referencia_pago: string | null;
    estatus: CostosSolicitudPagoEstatus;
    solicitante?: Usuario;
    departamento?: Departamento;
    proveedor?: Proveedor;
    tipo_solicitud?: CostosTipoSolicitud;
    detalles?: CostosSolicitudPagoDetalle[];
    archivos?: CostosSolicitudArchivo[];
    aprobaciones?: CostosAprobacionSolicitud[];
    created_at: string;
    updated_at: string;
};

export type CostosSolicitudPagoDetalle = {
    id: number;
    solicitud_id: number;
    obra_rubro_id: number;
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
    archivo_id: number;
    ruta_archivo: string;
    nombre_original: string;
    texto_adicional: string | null;
    tags: Record<string, string> | null;
    documento?: CostosDocumento;
    created_at: string;
    updated_at: string;
};

export type CostosAprobacionSolicitud = {
    id: number;
    solicitud_id: number;
    nivel: number;
    aprobador_id: string | null;
    estatus: string;
    fecha_respuesta: string | null;
    observaciones: string | null;
    aprobador?: Usuario;
    solicitud?: CostosSolicitudPago;
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
    departamento_id: number;
    creado_por: string | Usuario;
    aprobado_por: string | Usuario | null;
    fecha_aprobacion: string | null;
    pdf_formato_path: string | null;
    pdf_firmado_path: string | null;
    departamento?: Departamento;
    proveedor?: Proveedor;
    detalles?: CostosAfectacionDetalle[];
    historial?: CostosAfectacionHistorial[];
    rubros_afectados?: CostosRubroAfectado[];
    created_at: string;
    updated_at: string;
};

export type CostosAfectacionDetalle = {
    id: number;
    afectacion_id: number;
    obra_rubro_id: number;
    concepto: string;
    cantidad: number;
    precio_unitario: number;
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

export type CostosRubroAfectado = {
    id: number;
    entrada_type: string;
    entrada_id: number;
    obra_rubro_id: number;
    monto: number;
    sobre_giro: boolean;
    descripcion: string | null;
    tipo_movimiento: string;
    estatus: string;
    usuario_aplica_id: string | null;
    fecha_aplicacion: string | null;
    obra_rubro?: CostosObraRubro;
    created_at: string;
    updated_at: string;
};
