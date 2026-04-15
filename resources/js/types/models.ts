export type Usuario = {
    id: string;
    empleado: number | null;
    name: string;
    email: string;
    email_verified_at: string | null;
    firma_path: string | null;
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
    porcentaje_obra?: number | null;
    cliente?: Cliente;
    partidas?: CobPartida[];
    estimaciones?: CobEstimacion[];
    anticipos?: CobAnticipo[];
    adendas?: CobAdenda[];
    comparativos?: CobComparativo[];
    deducciones?: CobDeduccion[];
    eventos?: CobEvento[];
    disputas?: CobDisputa[];
    penalizaciones?: CobPenalizacion[];
    configuracion_documentos?: CobConfiguracionDocumento[];
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
export type Concepto = {
    id: number;
    obra_id: number;
    marca: string;
    descripcion: string;
    cantidad: number;
    peso_unitario: number;
    version: number;
    activo: boolean;
    obra?: Obra;
    registros_sum_cantidad?: number;
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
    obra?: Obra;
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
    confirmada_costos: boolean;
    confirmada_costos_por: string | null;
    confirmada_costos_at: string | null;
    confirmada_contabilidad: boolean;
    confirmada_contabilidad_por: string | null;
    confirmada_contabilidad_at: string | null;
    solicitante?: Usuario;
    departamento?: Departamento;
    proveedor?: Proveedor;
    tipo_solicitud?: CostosTipoSolicitud;
    detalles?: CostosSolicitudPagoDetalle[];
    archivos?: CostosSolicitudArchivo[];
    aprobaciones?: CostosAprobacionSolicitud[];
    pago?: CostosPago;
    media?: Media;
    confirmador_costos?: Usuario;
    confirmador_contabilidad?: Usuario;
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
    aprobador_id: string | null;
    estatus: string;
    fecha_respuesta: string | null;
    observaciones: string | null;
    ip: string | null;
    hostname: string | null;
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

// Ordenes de Compra Types
export type CostosOrdenCompraEstatus = 'pendiente_factura' | 'pendiente_entrega' | 'pendiente_aprobacion' | 'pendiente_pago' | 'pagada' | 'cancelada';

export const ORDEN_COMPRA_ESTATUS_LABELS: Record<CostosOrdenCompraEstatus, string> = {
    pendiente_factura: 'Pend. Factura',
    pendiente_entrega: 'Pend. Entrega',
    pendiente_aprobacion: 'Pend. Aprobación',
    pendiente_pago: 'Pend. Pago',
    pagada: 'Pagada',
    cancelada: 'Cancelada',
};

export const ORDEN_COMPRA_ESTATUS_COLORS: Record<CostosOrdenCompraEstatus, string> = {
    pendiente_factura: 'badge-warning',
    pendiente_entrega: 'badge-info',
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
    total: number;
    fecha_entrega_esperada: string | null;
    notas: string | null;
    estatus: CostosOrdenCompraEstatus;
    proveedor?: Proveedor;
    obra?: Obra;
    departamento?: Departamento;
    creador?: Usuario;
    detalles?: CostosOrdenCompraDetalle[];
    facturas?: CostosFactura[];
    media?: Media[];
    rubros_afectados?: CostosRubroAfectado[];
    facturas_count?: number;
    entregas_count?: number;
    pagos_count?: number;
    created_at: string;
    updated_at: string;
};

export type CostosOrdenCompraDetalle = {
    id: number;
    orden_compra_id: number;
    obra_rubro_id: number;
    monto: number;
    obra_rubro?: CostosObraRubro;
    created_at: string;
    updated_at: string;
};

// Facturas Types
export type CostosFacturaEstatus = 'pendiente_entrega' | 'pendiente_aprobacion' | 'pendiente_pago' | 'pagada' | 'cancelada';

export const FACTURA_ESTATUS_LABELS: Record<CostosFacturaEstatus, string> = {
    pendiente_entrega: 'Pendiente Entrega',
    pendiente_aprobacion: 'Pendiente Aprobación',
    pendiente_pago: 'Pendiente Pago',
    pagada: 'Pagada',
    cancelada: 'Cancelada',
};

export const FACTURA_ESTATUS_COLORS: Record<CostosFacturaEstatus, string> = {
    pendiente_entrega: 'badge-warning',
    pendiente_aprobacion: 'badge-accent',
    pendiente_pago: 'badge-primary',
    pagada: 'badge-success',
    cancelada: 'badge-error',
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
    total: number;
    moneda: string;
    fecha_factura: string | null;
    estatus: CostosFacturaEstatus;
    notas: string | null;
    aprobada_costos: boolean;
    aprobada_costos_por: string | null;
    aprobada_costos_at: string | null;
    aceptada_contabilidad: boolean;
    aceptada_contabilidad_por: string | null;
    aceptada_contabilidad_at: string | null;
    orden_compra?: CostosOrdenCompra;
    proveedor?: Proveedor;
    entregas?: CostosEntrega[];
    media?: Media[];
    media_pdf?: Media | null;
    pago?: CostosPago;
    aprobada_costos_por_usuario?: Usuario;
    aceptada_contabilidad_por_usuario?: Usuario;
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
    factura_id: number;
    recibido_por: string;
    fecha_entrega: string;
    tipo: CostosEntregaTipo;
    observaciones: string | null;
    media?: Media | null;
    recibidor?: Usuario;
    created_at: string;
    updated_at: string;
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
    es_adicional: boolean;
    descripcion: string;
    monto: number;
    moneda: string;
    es_subobra: boolean;
    created_at: string;
    updated_at: string;
};

export type CobEstimacionEstado = 'pendiente' | 'generada' | 'ingresada' | 'revisada' | 'autorizada' | 'facturada' | 'pago_parcial' | 'pagado';

export const COB_ESTIMACION_ESTADO_LABELS: Record<CobEstimacionEstado, string> = {
    pendiente: 'Pendiente',
    generada: 'Generada',
    ingresada: 'Ingresada',
    revisada: 'Revisada',
    autorizada: 'Autorizada',
    facturada: 'Facturada',
    pago_parcial: 'Pago Parcial',
    pagado: 'Pagado',
};

export const COB_ESTIMACION_ESTADO_COLORS: Record<CobEstimacionEstado, string> = {
    pendiente: 'badge-ghost',
    generada: 'badge-info',
    ingresada: 'badge-warning',
    revisada: 'badge-accent',
    autorizada: 'badge-primary',
    facturada: 'badge-secondary',
    pago_parcial: 'badge-warning',
    pagado: 'badge-success',
};

export type CobEstimacion = {
    id: number;
    obra_id: number;
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
    documentos?: CobDocumentoEstimacion[];
    created_at: string;
    updated_at: string;
};

export type CobEstimacionPago = {
    id: number;
    estimacion_id: number;
    monto_pagado: number;
    fecha_pago: string;
    folio: string | null;
    media?: Media | null;
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
    obra_id: number;
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

export type CobConfiguracionDocumento = {
    id: number;
    obra_id: number;
    nombre_documento: string;
    descripcion: string | null;
    obligatorio: boolean;
    created_at: string;
    updated_at: string;
};

export type CobDocumentoEstimacion = {
    id: number;
    estimacion_id: number;
    configuracion_documento_id: number;
    ruta_archivo: string;
    fecha_subida: string | null;
    subido_por: string;
    configuracion_documento?: CobConfiguracionDocumento;
    created_at: string;
    updated_at: string;
};

export const COB_TIPO_CONTRATO_LABELS: Record<string, string> = {
    precio_alzado: 'Precio Alzado',
    precio_unitario: 'Precio Unitario',
    mixto: 'Mixto',
    administracion: 'Administracion',
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
    datos_extra?: RhDatosExtra;
    documentos?: RhPersonaDocumento[];
    periodos_laborales?: RhPeriodoLaboral[];
    candidaturas?: RhCandidatura[];
    contactos_emergencia?: RhContactoEmergencia[];
    created_at: string;
    updated_at: string;
};

export type RhContactoEmergencia = {
    id: number;
    persona_id: number;
    nombre: string;
    telefono: string;
    created_at: string;
    updated_at: string;
};

export type RhDatosExtra = {
    id: number;
    persona_id: number;
    estado_civil: string | null;
    hijos: number | null;
    localidad: string | null;
    domicilio: string | null;
    cp: string | null;
    nombre_padre: string | null;
    nombre_madre: string | null;
    cuenta_banco: string | null;
    c_infonavit: string | null;
    c_fonacot: string | null;
    imss: string | null;
    curp: string | null;
    rfc: string | null;
    numero_ine: string | null;
    banco_op: string | null;
    texto_cv: string | null;
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
    sueldo_mensual: number | null;
    tipo_contrato: string | null;
    numero_empleado: string | null;
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
    created_at: string;
    updated_at: string;
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
    subido_por_id: number;
    link_token: string | null;
    link_expira_en: string | null;
    auto_eliminar_en: string | null;
    link_publico: string | null;
    carpeta?: DriveCarpeta;
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
